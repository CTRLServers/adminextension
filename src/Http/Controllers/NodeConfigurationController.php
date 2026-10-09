<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\Api\KeyCreationService;

class NodeConfigurationController extends Controller
{
    public function __construct(
        private Encrypter $encrypter,
        private KeyCreationService $keycreationservice,
    ) {
    }

    public function view(Node $node): JsonResponse
    {
        return response()
            ->json(['config_yml' => $node->getYamlConfiguration()])
            ->header('Cache-Control', 'no-store');
    }

    public function deployment(Request $request, Node $node): JsonResponse
    {
        $key = ApiKey::query()
            ->where('user_id', $request->user()->id)
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->where('r_nodes', 3)
            ->first();

        if (!$key) {
            $key = $this->keycreationservice
                ->setKeyType(ApiKey::TYPE_APPLICATION)
                ->handle([
                    'user_id' => $request->user()->id,
                    'memo' => 'Automatically generated node deployment key.',
                    'allowed_ips' => [],
                ], ['r_nodes' => 3]);
        }

        $token = $key->identifier . $this->encrypter->decrypt($key->token);
        $panelurl = rtrim((string) config('app.url'), '/');
        $command = sprintf(
            'cd /etc/pterodactyl && sudo wings configure --panel-url %s --token %s --node %d%s',
            $panelurl,
            $token,
            $node->id,
            config('app.debug') ? ' --allow-insecure' : '',
        );

        return response()
            ->json(['command' => $command])
            ->header('Cache-Control', 'no-store');
    }
}
