<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Pterodactyl\Models\DatabaseHost;
use Pterodactyl\Services\Databases\Hosts\HostCreationService;
use Pterodactyl\Services\Databases\Hosts\HostUpdateService;

class DatabaseHostController extends Controller
{
    public function __construct(
        private HostCreationService $creationservice,
        private HostUpdateService $updateservice,
    ) {
    }

    public function index(): JsonResponse
    {
        $hosts = DatabaseHost::query()
            ->with('node')
            ->withCount('databases')
            ->orderBy('id')
            ->get()
            ->map(fn (DatabaseHost $host): array => $this->transform($host));

        return response()->json(['hosts' => $hosts])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $values = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'host' => ['required', 'string', 'regex:/^[\w\-.]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string'],
            'node_id' => ['required', 'integer', 'exists:nodes,id'],
        ]);

        $host = $this->creationservice->handle($values);
        $host->load('node')->loadCount('databases');

        return response()->json(['host' => $this->transform($host)], 201);
    }

    public function view(DatabaseHost $host): JsonResponse
    {
        $host->load(['node', 'databases.server'])->loadCount('databases');

        return response()->json(['host' => $this->transform($host, true)])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, DatabaseHost $host): JsonResponse
    {
        $values = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'host' => ['required', 'string', 'regex:/^[\w\-.]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:32'],
            'password' => ['nullable', 'string'],
            'node_id' => ['required', 'integer', 'exists:nodes,id'],
        ]);

        $host = $this->updateservice->handle($host->id, $values);
        $host->load(['node', 'databases.server'])->loadCount('databases');

        return response()->json(['host' => $this->transform($host, true)]);
    }

    private function transform(DatabaseHost $host, bool $details = false): array
    {
        $data = [
            'id' => $host->id,
            'name' => $host->name,
            'host' => $host->host,
            'port' => $host->port,
            'username' => $host->username,
            'databases' => $host->databases_count ?? $host->databases()->count(),
            'node' => $host->node ? [
                'id' => $host->node->id,
                'name' => $host->node->name,
            ] : null,
        ];

        if ($details) {
            $data['database_list'] = $host->databases->map(fn ($database): array => [
                'id' => $database->id,
                'server' => $database->server?->name,
                'database' => $database->database,
                'username' => $database->username,
                'remote' => $database->remote,
                'max_connections' => $database->max_connections,
            ]);
        }

        return $data;
    }
}
