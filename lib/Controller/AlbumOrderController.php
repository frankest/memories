<?php

declare(strict_types=1);

namespace OCA\Memories\Controller;

use OCA\Memories\AppInfo\Application;
use OCA\Memories\Service\AlbumOrder;
use OCA\Memories\Util;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

final class AlbumOrderController extends Controller
{
    public function __construct(IRequest $request, private AlbumOrder $order)
    {
        parent::__construct(Application::APPNAME, $request);
    }

    #[NoAdminRequired]
    public function show(string $albums, bool $ids = false): Response
    {
        return Util::guardEx(function () use ($albums, $ids) {
            $state = $this->order->state($this->order->resolve($albums, true));

            /** @var array<string, mixed> $response */
            $response = [
                'manual' => $state['manual'],
                'revision' => $state['revision'],
            ];
            // Only the order itself is needed for reordering in the timeline
            if ($ids) {
                $response['fileIds'] = array_column($state['photos'], 'fileid');
            } else {
                $response['photos'] = $state['photos'];
            }

            return new JSONResponse($response);
        });
    }

    #[NoAdminRequired]
    public function save(string $albums, array $fileIds, string $revision, bool $manual = true): Response
    {
        return Util::guardEx(function () use ($albums, $fileIds, $revision, $manual) {
            return new JSONResponse([
                'revision' => $this->order->save($albums, $fileIds, $revision, $manual),
            ]);
        });
    }

    #[NoAdminRequired]
    public function reset(string $albums): Response
    {
        return Util::guardEx(function () use ($albums) {
            $this->order->reset($albums);

            return new JSONResponse([]);
        });
    }
}
