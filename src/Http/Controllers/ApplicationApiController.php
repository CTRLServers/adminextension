<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Services\Api\KeyCreationService;

class ApplicationApiController extends Controller
{
    private const RESOURCES = [
        'allocations',
        'database_hosts',
        'eggs',
        'locations',
        'nests',
        'nodes',
        'server_databases',
        'servers',
        'users',
    ];

    public function __construct(private KeyCreationService $creationservice)
    {
    }

    public function index(): JsonResponse
    {
        $keys = ApiKey::query()
            ->with('user')
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->latest('id')
            ->get()
            ->map(fn (ApiKey $key): array => $this->transform($key));

        return response()->json(['keys' => $keys])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $rules = ['description' => ['required', 'string', 'max:500']];
        foreach (self::RESOURCES as $resource) {
            $rules['permissions.' . $resource] = ['required', 'integer', Rule::in([0, 1, 3])];
        }

        $values = $request->validate($rules);
        $permissions = [];
        foreach (self::RESOURCES as $resource) {
            $permissions['r_' . $resource] = $values['permissions'][$resource];
        }

        $key = $this->creationservice
            ->setKeyType(ApiKey::TYPE_APPLICATION)
            ->handle([
                'user_id' => $request->user()->id,
                'memo' => $values['description'],
                'allowed_ips' => [],
            ], $permissions);
        $key->load('user');

        return response()->json([
            'key' => $this->transform($key),
            'value' => $key->identifier . decrypt($key->token),
        ], 201);
    }

    public function destroy(ApiKey $apikey): JsonResponse
    {
        abort_unless($apikey->key_type === ApiKey::TYPE_APPLICATION, 404);
        $apikey->delete();

        return response()->json([], 204);
    }

    private function transform(ApiKey $key): array
    {
        $permissions = [];
        foreach (self::RESOURCES as $resource) {
            $permissions[$resource] = (int) $key->getAttribute('r_' . $resource);
        }

        return [
            'id' => $key->id,
            'value' => $key->identifier . decrypt($key->token),
            'note' => $key->memo,
            'last_used_at' => $key->last_used_at?->toIso8601String(),
            'created_at' => $key->created_at?->toIso8601String(),
            'created_by' => $key->user ? [
                'id' => $key->user->id,
                'username' => $key->user->username,
                'first_name' => $key->user->name_first,
                'last_name' => $key->user->name_last,
            ] : null,
            'permissions' => $permissions,
        ];
    }
}
