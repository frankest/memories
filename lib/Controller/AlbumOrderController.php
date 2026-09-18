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
    public function show(string $albums): Response
    {
        return Util::guardEx(function () use ($albums) {
            $state = $this->order->state($this->order->resolve($albums, true));
            unset($state['memberships'], $state['storedRevision']);

            return new JSONResponse($state);
        });
    }

    #[NoAdminRequired]
    public function save(string $albums, array $fileIds, string $revision, bool $manual = true): Response
    {
        return Util::guardEx(function () use ($albums, $fileIds, $revision, $manual) {
            $this->order->save($albums, $fileIds, $revision, $manual);

            return new JSONResponse([]);
        });
    }
}
