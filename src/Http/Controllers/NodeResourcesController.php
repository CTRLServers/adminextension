<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Pterodactyl\Models\Node;

class NodeResourcesController extends Controller
{
    public function allocations(Node $node): JsonResponse
    {
        $allocations = $node->allocations()
            ->with('server')
            ->orderBy('ip')
            ->orderBy('port')
            ->get()
            ->map(function ($allocation): array {
                return [
                    'id' => $allocation->id,
                    'ip' => $allocation->ip,
                    'alias' => $allocation->ip_alias,
                    'port' => $allocation->port,
                    'assigned' => $allocation->server_id !== null,
                    'server' => $allocation->server ? [
                        'id' => $allocation->server->id,
                        'name' => $allocation->server->name,
                    ] : null,
                ];
            });

        return response()
            ->json(['allocations' => $allocations])
            ->header('Cache-Control', 'no-store');
    }

    public function servers(Node $node): JsonResponse
    {
        $servers = $node->servers()
            ->with(['user', 'nest', 'egg'])
            ->orderBy('id')
            ->get()
            ->map(function ($server): array {
                return [
                    'id' => $server->id,
                    'uuid' => $server->uuid,
                    'identifier' => $server->uuidShort,
                    'name' => $server->name,
                    'owner' => $server->user ? [
                        'id' => $server->user->id,
                        'email' => $server->user->email,
                        'username' => $server->user->username,
                        'first_name' => $server->user->name_first,
                        'last_name' => $server->user->name_last,
                    ] : null,
                    'service' => $server->egg?->name ?? $server->nest?->name,
                ];
            });

        return response()
            ->json(['servers' => $servers])
            ->header('Cache-Control', 'no-store');
    }
}
