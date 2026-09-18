<?php

declare(strict_types=1);

namespace OCA\Memories\Service;

use OCA\Memories\Db\AlbumsQuery;
use OCA\Memories\Db\TimelineQuery;
use OCA\Memories\Exceptions;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserSession;

final class AlbumOrder
{
    public function __construct(
        private IDBConnection $connection,
        private AlbumsQuery $albums,
        private TimelineQuery $timeline,
        private IUserSession $session,
    ) {}

    public function resolve(string $identifier, bool $write = false): array
    {
        $uid = $this->session->getUser()?->getUID();
        $album = $uid ? $this->albums->getIfAllowed($uid, $identifier) : $this->albums->getAlbumByLink($identifier);
        if (!$album) {
            throw Exceptions::NotFound('album');
        }
        if ($write && (!$uid || ($album['user'] !== $uid
            && !$this->albums->userIsCollaborator($uid, (int) $album['album_id'])))) {
            throw Exceptions::Forbidden('album order');
        }

        return $album;
    }

    public function stored(int $albumId): ?array
    {
        $q = $this->connection->getQueryBuilder();
        $row = $q->select('*')->from('memories_album_order')
            ->where($q->expr()->eq('album_id', $q->createNamedParameter($albumId, IQueryBuilder::PARAM_INT)))
            ->executeQuery()->fetch()
        ;

        return $row ?: null;
    }

    /** @return array{manual: bool, revision: string, photos: array, memberships: array<int, int>, storedRevision: ?string} */
    public function state(array $album): array
    {
        $albumId = (int) $album['album_id'];
        $stored = $this->stored($albumId);
        $q = $this->connection->getQueryBuilder();
        $members = $q->select('album_file_id', 'file_id')->from('photos_albums_files')
            ->where($q->expr()->eq('album_id', $q->createNamedParameter($albumId, IQueryBuilder::PARAM_INT)))
            ->orderBy('album_file_id', 'ASC')->executeQuery()->fetchAll()
        ;
        $memberships = [];
        foreach ($members as $member) {
            $memberships[(int) $member['file_id']] = (int) $member['album_file_id'];
        }

        $photos = $this->timeline->getAlbumOrderPhotos($albumId);
        $manual = (bool) ($stored['manual'] ?? false);
        if ($manual && null !== $stored) {
            $positions = array_flip(json_decode($stored['items'], true, 512, JSON_THROW_ON_ERROR));
            usort($photos, static function (array $a, array $b) use ($positions, $memberships): int {
                $aId = $memberships[$a['fileid']];
                $bId = $memberships[$b['fileid']];

                return ($positions[$aId] ?? PHP_INT_MAX) <=> ($positions[$bId] ?? PHP_INT_MAX) ?: $aId <=> $bId;
            });
        }

        return [
            'manual' => $manual,
            'revision' => hash('sha256', ($stored['revision'] ?? '').json_encode($memberships, JSON_THROW_ON_ERROR).json_encode(array_column($photos, 'fileid'), JSON_THROW_ON_ERROR)),
            'photos' => $photos,
            'memberships' => $memberships,
            'storedRevision' => $stored['revision'] ?? null,
        ];
    }

    /** A complete permutation prevents partial, duplicate or foreign-file updates. */
    public static function validate(array $fileIds, array $currentIds): void
    {
        foreach ($fileIds as $id) {
            if (!\is_int($id) || $id <= 0) {
                throw Exceptions::BadRequest('invalid file ID');
            }
        }
        if (\count(array_unique($fileIds)) !== \count($fileIds)) {
            throw Exceptions::BadRequest('duplicate file IDs');
        }
        sort($fileIds);
        sort($currentIds);
        if ($fileIds !== $currentIds) {
            throw Exceptions::BadRequest('album membership changed or invalid file IDs');
        }
    }

    public function save(string $identifier, array $fileIds, string $revision, bool $manual): void
    {
        $album = $this->resolve($identifier, true);
        $state = $this->state($album);
        if (!hash_equals($state['revision'], $revision)) {
            $this->conflict();
        }
        self::validate($fileIds, array_column($state['photos'], 'fileid'));
        $items = array_map(static fn (int $id): int => $state['memberships'][$id], $fileIds);
        $q = $this->connection->getQueryBuilder();
        $values = [
            'album_id' => $q->createNamedParameter((int) $album['album_id'], IQueryBuilder::PARAM_INT),
            'items' => $q->createNamedParameter(json_encode($items, JSON_THROW_ON_ERROR)),
            'revision' => $q->createNamedParameter(bin2hex(random_bytes(16))),
            'manual' => $q->createNamedParameter($manual, IQueryBuilder::PARAM_BOOL),
        ];
        if (null === $state['storedRevision']) {
            try {
                $q->insert('memories_album_order')->values($values)->executeStatement();
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                $this->conflict();
            }
        } else {
            $changed = $q->update('memories_album_order')
                ->set('items', $values['items'])->set('revision', $values['revision'])->set('manual', $values['manual'])
                ->where($q->expr()->eq('album_id', $values['album_id']))
                ->andWhere($q->expr()->eq('revision', $q->createNamedParameter($state['storedRevision'])))
                ->executeStatement()
            ;
            if (1 !== $changed) {
                $this->conflict();
            }
        }
    }

    /** null preserves the existing chronological timeline for unconfigured albums. */
    public function timeline(string $identifier, ?array $dayIds = null): ?array
    {
        $album = $this->resolve($identifier);
        if (!($this->stored((int) $album['album_id'])['manual'] ?? false)) {
            return null;
        }
        $state = $this->state($album);
        $photos = $state['photos'];
        foreach ($photos as &$photo) {
            $photo['dayid'] = 0;
        }
        unset($photo);
        if (null !== $dayIds) {
            return \in_array(0, $dayIds, true) ? $photos : [];
        }

        return $photos ? [['dayid' => 0, 'count' => \count($photos), 'manualOrder' => true, 'detail' => $photos]] : [];
    }

    private function conflict(): never
    {
        throw Exceptions::Generic(new \RuntimeException('The album changed. Reload before saving.'), 409);
    }
}
