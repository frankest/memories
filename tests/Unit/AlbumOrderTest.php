<?php

declare(strict_types=1);

namespace OCA\Memories\Tests\Unit;

use OCA\Memories\Db\AlbumsQuery;
use OCA\Memories\Db\TimelineQuery;
use OCA\Memories\HttpResponseException;
use OCA\Memories\Service\AlbumOrder;
use OCA\Photos\Album\AlbumMapper;
use OCP\IDBConnection;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AlbumOrderTest extends TestCase
{
    public function testRejectsInvalidPermutations(): void
    {
        AlbumOrder::validate([3, 1, 2], [1, 2, 3]);
        foreach ([[1, 1, 3], [1, 2], [1, 2, 99], [1, 2, '3'], [0, 1, 2]] as $ids) {
            try {
                AlbumOrder::validate($ids, [1, 2, 3]);
                self::fail('Invalid order accepted');
            } catch (HttpResponseException) {
                self::assertTrue(true);
            }
        }
    }

    public function testSharedOrderPermissionsAndConcurrentChanges(): void
    {
        $db = \OC::$server->get(IDBConnection::class);
        $users = \OC::$server->get(\OCP\IUserManager::class);
        $mapper = \OC::$server->get(AlbumMapper::class);
        $prefix = 'mem-order-test-'.bin2hex(random_bytes(5));
        $createdUsers = [];
        $albumId = null;
        $files = [];
        $group = null;

        try {
            foreach (['owner', 'collaborator', 'visitor'] as $role) {
                $createdUsers[$role] = $users->createUser($prefix.'-'.$role, bin2hex(random_bytes(24)));
                self::assertNotFalse($createdUsers[$role]);
            }
            $owner = $createdUsers['owner'];
            $collaborator = $createdUsers['collaborator'];
            $visitor = $createdUsers['visitor'];
            $info = $mapper->create($owner->getUID(), 'Order test');
            $albumId = $info->getId();
            $identifier = $owner->getUID().'/Order test';
            $album = ['album_id' => $albumId, 'user' => $owner->getUID()];
            $session = self::createStub(IUserSession::class);
            $current = $owner;
            $session->method('getUser')->willReturnCallback(static function () use (&$current) { return $current; });
            $order = new AlbumOrder($db, \OC::$server->get(AlbumsQuery::class), \OC::$server->get(TimelineQuery::class), $session);
            $folder = \OC::$server->get(\OCP\Files\IRootFolder::class)->getUserFolder($owner->getUID());

            for ($i = 1; $i <= 3; ++$i) {
                $file = $folder->newFile('order-fixture-'.$i.'.txt', 'Isolated album order test fixture');
                $files[] = $file->getId();
                $q = $db->getQueryBuilder();
                $q->insert('memories')->values([
                    'fileid' => $q->createNamedParameter($file->getId()),
                    'datetaken' => $q->createNamedParameter('2020-01-0'.$i.' 12:00:00'),
                    'dayid' => $q->createNamedParameter(18261 + $i),
                    'mtime' => $q->createNamedParameter(1),
                ])->executeStatement();
                $mapper->addFile($albumId, $file->getId(), $owner->getUID());
            }

            self::assertNull($order->timeline($identifier));
            $state = $order->state($album);
            self::assertSame($files, array_column($state['photos'], 'fileid'));
            $desired = [$files[2], $files[0], $files[1]];
            $order->save($identifier, $desired, $state['revision'], true);
            self::assertSame($desired, array_column($order->state($album)['photos'], 'fileid'));
            $this->assertDenied(static fn () => $order->save($identifier, $files, $state['revision'], true), 409);

            $mapper->setCollaborators($albumId, [
                ['id' => $collaborator->getUID(), 'type' => AlbumMapper::TYPE_USER],
                ['id' => '', 'type' => AlbumMapper::TYPE_LINK],
            ]);
            $links = array_values(array_filter($mapper->getCollaborators($albumId), static fn ($c) => AlbumMapper::TYPE_LINK === $c['type']));
            $token = $links[0]['id'];

            $current = $collaborator;
            self::assertSame($albumId, (int) $order->resolve($identifier, true)['album_id']);
            $state = $order->state($album);
            $order->save($identifier, $files, $state['revision'], true);
            $current = null;
            self::assertSame($files, array_column($order->timeline($token)[0]['detail'], 'fileid'));
            $this->assertDenied(static fn () => $order->save($token, $files, $state['revision'], true), 403);
            $current = $visitor;
            $this->assertDenied(static fn () => $order->resolve($identifier, true), 404);
            $this->assertDenied(static fn () => $order->resolve($token, true), 403);

            $groups = \OC::$server->get(\OCP\IGroupManager::class);
            $group = $groups->createGroup($prefix.'-group');
            $group->addUser($collaborator);
            $mapper->setCollaborators($albumId, [['id' => $group->getGID(), 'type' => AlbumMapper::TYPE_GROUP]]);
            $current = $collaborator;
            self::assertSame($albumId, (int) $order->resolve($identifier, true)['album_id']);

            // Removing and re-adding a file creates a new membership: it must go last.
            $mapper->removeFile($albumId, $files[0]);
            $mapper->addFile($albumId, $files[0], $owner->getUID());
            self::assertSame([$files[1], $files[2], $files[0]], array_column($order->state($album)['photos'], 'fileid'));
            $state = $order->state($album);
            $mapper->removeFile($albumId, $files[2]);
            $this->assertDenied(static fn () => $order->save($identifier, $files, $state['revision'], true), 409);
            $state = $order->state($album);
            $order->save($identifier, array_column($state['photos'], 'fileid'), $state['revision'], false);
            self::assertNull($order->timeline($identifier));
        } finally {
            if (null !== $albumId) {
                $mapper->delete($albumId);
                $q = $db->getQueryBuilder();
                $q->delete('memories_album_order')->where($q->expr()->eq('album_id', $q->createNamedParameter($albumId)))->executeStatement();
            }
            foreach ($files as $fileId) {
                $q = $db->getQueryBuilder();
                $q->delete('memories')->where($q->expr()->eq('fileid', $q->createNamedParameter($fileId)))->executeStatement();
            }
            $group?->delete();
            foreach ($createdUsers as $user) {
                if ($user) {
                    $user->delete();
                }
            }
        }
    }

    private function assertDenied(callable $action, int $status): void
    {
        try {
            $action();
            self::fail('Unauthorized or stale update accepted');
        } catch (HttpResponseException $e) {
            self::assertSame($status, $e->response->getStatus());
        }
    }
}
