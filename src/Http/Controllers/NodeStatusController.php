<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Pterodactyl\Models\Node;
use Pterodactyl\Repositories\Wings\DaemonConfigurationRepository;
use Throwable;

class NodeStatusController extends Controller
{
    public function __construct(private DaemonConfigurationRepository $repository)
    {
    }

    public function view(Node $node): JsonResponse
    {
        try {
            $this->repository->setNode($node)->getSystemInformation();
            $online = true;
        } catch (Throwable) {
            $online = false;
        }

        return response()
            ->json([
                'online' => $online,
                'server_count' => $node->servers()->count(),
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function information(Node $node): JsonResponse
    {
        try {
            $information = $this->repository->setNode($node)->getSystemInformation();

            return response()
                ->json([
                    'online' => true,
                    'version' => $information['version'] ?? null,
                    'os' => $information['os'] ?? null,
                    'architecture' => $information['architecture'] ?? null,
                    'kernel_version' => $information['kernel_version'] ?? null,
                    'cpu_count' => $information['cpu_count'] ?? null,
                    'server_count' => $node->servers()->count(),
                ])
                ->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return response()
                ->json(['online' => false])
                ->header('Cache-Control', 'no-store');
        }
    }
}
