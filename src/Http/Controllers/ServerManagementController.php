<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Pterodactyl\Contracts\Repository\AllocationRepositoryInterface;
use Pterodactyl\Contracts\Repository\ServerRepositoryInterface;
use Pterodactyl\Enum\JwtScope;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\DatabaseHost;
use Pterodactyl\Models\Mount;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerTransfer;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Models\User;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Repositories\Wings\DaemonTransferRepository;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Services\Databases\DatabaseManagementService;
use Pterodactyl\Services\Servers\BuildModificationService;
use Pterodactyl\Services\Servers\DetailsModificationService;
use Pterodactyl\Services\Servers\ReinstallServerService;
use Pterodactyl\Services\Servers\StartupModificationService;
use Pterodactyl\Services\Servers\SuspensionService;

class ServerManagementController extends Controller
{
    public function __construct(
        private AllocationRepositoryInterface $allocationrepository,
        private BuildModificationService $buildservice,
        private ConnectionInterface $connection,
        private DatabaseManagementService $databaseservice,
        private DaemonTransferRepository $daemontransferrepository,
        private DetailsModificationService $detailsservice,
        private NodeJWTService $nodejwtservice,
        private NodeRepository $noderepository,
        private ReinstallServerService $reinstallservice,
        private ServerRepositoryInterface $serverrepository,
        private StartupModificationService $startupservice,
        private SuspensionService $suspensionservice,
    ) {
    }

    public function index(): JsonResponse
    {
        $servers = Server::query()
            ->with(['user', 'node', 'allocation', 'egg.nest', 'transfer'])
            ->orderBy('id')
            ->get()
            ->map(fn (Server $server): array => $this->transform($server));

        return response()->json(['servers' => $servers])->header('Cache-Control', 'no-store');
    }

    public function view(Server $server): JsonResponse
    {
        $server->load(['user', 'node', 'allocation', 'allocations', 'egg.nest', 'transfer']);

        $data = $this->transform($server);
        $data['assigned_allocations'] = $server->allocations
            ->sortBy(fn ($allocation): string => $allocation->ip . ':' . str_pad((string) $allocation->port, 5, '0', STR_PAD_LEFT))
            ->values()
            ->map(fn ($allocation): array => $this->transformallocation($allocation));
        $data['available_allocations'] = $server->node->allocations()
            ->whereNull('server_id')
            ->orderBy('ip')
            ->orderBy('port')
            ->get()
            ->map(fn ($allocation): array => $this->transformallocation($allocation));
        $serverenvironment = ServerVariable::query()
            ->where('server_id', $server->id)
            ->pluck('variable_value', 'variable_id');
        $data['startup'] = [
            'command' => $server->startup,
            'image' => $server->image,
            'skip_scripts' => (bool) $server->skip_scripts,
            'nest_id' => $server->nest_id,
            'egg_id' => $server->egg_id,
        ];
        $data['startup_nests'] = Nest::query()
            ->with(['eggs' => fn ($query) => $query->with('variables')->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Nest $nest): array => [
                'id' => $nest->id,
                'name' => $nest->name,
                'eggs' => $nest->eggs->map(fn ($egg): array => [
                    'id' => $egg->id,
                    'name' => $egg->name,
                    'startup' => $egg->startup,
                    'docker_images' => $egg->docker_images,
                    'variables' => $egg->variables->map(fn ($variable): array => [
                        'id' => $variable->id,
                        'name' => $variable->name,
                        'description' => $variable->description,
                        'env_variable' => $variable->env_variable,
                        'default_value' => $variable->default_value,
                        'server_value' => $egg->id === $server->egg_id ? $serverenvironment->get($variable->id) : null,
                        'rules' => $variable->rules,
                    ])->values(),
                ])->values(),
            ])->values();
        $data['databases'] = $server->databases()
            ->with('host')
            ->orderBy('id')
            ->get()
            ->map(fn (Database $database): array => [
                'id' => $database->id,
                'database' => $database->database,
                'username' => $database->username,
                'remote' => $database->remote,
                'max_connections' => $database->max_connections,
                'host' => $database->host ? [
                    'id' => $database->host->id,
                    'name' => $database->host->name,
                    'host' => $database->host->host,
                    'port' => $database->host->port,
                ] : null,
            ])->values();
        $data['database_hosts'] = DatabaseHost::query()
            ->orderBy('name')
            ->get()
            ->map(fn (DatabaseHost $host): array => [
                'id' => $host->id,
                'name' => $host->name,
                'host' => $host->host,
                'port' => $host->port,
            ])->values();
        $data['mounts'] = Mount::query()
            ->whereHas('eggs', fn ($query) => $query->where('eggs.id', $server->egg_id))
            ->whereHas('nodes', fn ($query) => $query->where('nodes.id', $server->node_id))
            ->with(['servers' => fn ($query) => $query->where('servers.id', $server->id)])
            ->orderBy('id')
            ->get()
            ->map(fn (Mount $mount): array => [
                'id' => $mount->id,
                'name' => $mount->name,
                'source' => $mount->source,
                'target' => $mount->target,
                'mounted' => $mount->servers->isNotEmpty(),
            ])->values();
        $data['transfer_nodes'] = Node::query()
            ->where('id', '!=', $server->node_id)
            ->orderBy('name')
            ->get()
            ->map(function (Node $node): array {
                return [
                    'id' => $node->id,
                    'name' => $node->name,
                    'allocations' => $node->allocations()
                        ->whereNull('server_id')
                        ->orderBy('ip')
                        ->orderBy('port')
                        ->get()
                        ->map(fn ($allocation): array => [
                            'id' => $allocation->id,
                            'ip' => $allocation->ip,
                            'alias' => $allocation->ip_alias,
                            'port' => $allocation->port,
                        ]),
                ];
            });

        return response()->json(['server' => $data])->header('Cache-Control', 'no-store');
    }

    public function users(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['users' => []]);
        }

        $users = User::query()
            ->where(function ($query) use ($search): void {
                $query->where('username', 'like', "%{$search}%")
                    ->orWhere('name_first', 'like', "%{$search}%")
                    ->orWhere('name_last', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('username')
            ->limit(10)
            ->get()
            ->map(fn (User $user): array => $this->transformuser($user));

        return response()->json(['users' => $users])->header('Cache-Control', 'no-store');
    }

    public function details(Request $request, Server $server): JsonResponse
    {
        $values = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'external_id' => ['nullable', 'string', 'max:191', 'unique:servers,external_id,' . $server->id],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string'],
        ]);

        $this->detailsservice->handle($server, $values);

        return $this->view($server->refresh());
    }

    public function build(Request $request, Server $server): JsonResponse
    {
        $values = $request->validate([
            'cpu' => ['required', 'numeric', 'min:0'],
            'threads' => ['nullable', 'string', 'regex:/^[0-9,-]+$/'],
            'memory' => ['required', 'integer', 'min:0'],
            'swap' => ['required', 'integer', 'min:-1'],
            'disk' => ['required', 'integer', 'min:0'],
            'io' => ['required', 'integer', 'between:10,1000'],
            'oom_disabled' => ['required', 'boolean'],
            'database_limit' => ['required', 'integer', 'min:0'],
            'allocation_limit' => ['required', 'integer', 'min:0'],
            'backup_limit' => ['required', 'integer', 'min:0'],
            'allocation_id' => ['required', 'integer', 'exists:allocations,id'],
            'add_allocations' => ['array'],
            'add_allocations.*' => ['integer', 'exists:allocations,id'],
            'remove_allocations' => ['array'],
            'remove_allocations.*' => ['integer', 'exists:allocations,id'],
        ]);

        $assignedids = $server->allocations()->pluck('id')->all();
        if (!in_array((int) $values['allocation_id'], $assignedids, true)) {
            return response()->json(['message' => 'The game port must already be assigned to this server.'], 422);
        }

        $availableids = $server->node->allocations()->whereNull('server_id')->pluck('id')->all();
        foreach ($values['add_allocations'] ?? [] as $id) {
            if (!in_array((int) $id, $availableids, true)) {
                return response()->json(['message' => 'One or more selected ports are no longer available.'], 422);
            }
        }

        $removableids = array_values(array_diff($assignedids, [(int) $values['allocation_id']]));
        foreach ($values['remove_allocations'] ?? [] as $id) {
            if (!in_array((int) $id, $removableids, true)) {
                return response()->json(['message' => 'The game port cannot be removed from the server.'], 422);
            }
        }

        $this->buildservice->handle($server, $values);

        return $this->view($server->refresh());
    }

    public function startup(Request $request, Server $server): JsonResponse
    {
        $values = $request->validate([
            'startup' => ['required', 'string'],
            'egg_id' => ['required', 'integer', 'exists:eggs,id'],
            'skip_scripts' => ['required', 'boolean'],
            'docker_image' => ['required', 'string', 'max:191'],
            'environment' => ['required', 'array'],
        ]);

        $reinstall = $server->egg_id !== (int) $values['egg_id']
            || (bool) $server->skip_scripts !== (bool) $values['skip_scripts'];

        $this->startupservice
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle($server, $values);

        if ($reinstall) {
            $this->reinstallservice->handle($server->refresh());
        }

        return $this->view($server->refresh());
    }

    public function databasecreate(Request $request, Server $server): JsonResponse
    {
        $values = $request->validate([
            'database_host_id' => ['required', 'integer', 'exists:database_hosts,id'],
            'database' => ['required', 'string', 'max:48', 'regex:/^[a-zA-Z0-9_]+$/'],
            'remote' => ['required', 'string', 'max:191'],
            'max_connections' => ['nullable', 'integer', 'min:0'],
        ]);

        $values['database'] = DatabaseManagementService::generateUniqueDatabaseName($values['database'], $server->id);
        $values['max_connections'] = $values['max_connections'] ?? 0;
        $this->databaseservice->create($server, $values);

        return $this->view($server->refresh());
    }

    public function databasedelete(Server $server, Database $database): JsonResponse
    {
        abort_unless($database->server_id === $server->id, 404);
        $this->databaseservice->delete($database);

        return $this->view($server->refresh());
    }

    public function mount(Server $server, int $mountid): JsonResponse
    {
        $mount = Mount::query()->findOrFail($mountid);
        $eligible = $mount->eggs()->whereKey($server->egg_id)->exists()
            && $mount->nodes()->whereKey($server->node_id)->exists();

        if (!$eligible) {
            return response()->json(['message' => 'This mount is not available for the server Egg and Node.'], 422);
        }

        $mount->servers()->syncWithoutDetaching([$server->id]);

        return $this->view($server->refresh());
    }

    public function unmount(Server $server, int $mountid): JsonResponse
    {
        $mount = Mount::query()->findOrFail($mountid);
        $mount->servers()->detach($server->id);

        return $this->view($server->refresh());
    }

    public function reinstall(Server $server): JsonResponse
    {
        $this->reinstallservice->handle($server);

        return $this->view($server->refresh());
    }

    public function install(Server $server): JsonResponse
    {
        if ($server->status === Server::STATUS_INSTALL_FAILED) {
            return response()->json(['message' => 'A failed installation cannot be toggled manually.'], 422);
        }

        $this->serverrepository->update($server->id, [
            'status' => $server->isInstalled() ? Server::STATUS_INSTALLING : null,
        ], true, true);

        return $this->view($server->refresh());
    }

    public function suspension(Request $request, Server $server): JsonResponse
    {
        $values = $request->validate(['action' => ['required', 'in:suspend,unsuspend']]);
        $this->suspensionservice->toggle($server, $values['action']);

        return $this->view($server->refresh());
    }

    public function transfer(Request $request, Server $server): JsonResponse
    {
        $values = $request->validate([
            'node_id' => ['required', 'integer', 'exists:nodes,id', 'not_in:' . $server->node_id],
            'allocation_id' => ['required', 'integer', 'exists:allocations,id'],
        ]);

        $allocation = Node::query()
            ->findOrFail($values['node_id'])
            ->allocations()
            ->whereKey($values['allocation_id'])
            ->whereNull('server_id')
            ->firstOrFail();

        $nodeid = (int) $values['node_id'];
        $node = $this->noderepository->getNodeWithResourceUsage($nodeid);

        if (!$node->isViable($server->memory, $server->disk)) {
            return response()->json(['message' => 'The selected node does not have enough resources for this server.'], 422);
        }

        $server->validateTransferState();

        $this->connection->transaction(function () use ($server, $nodeid, $allocation): void {
            $transfer = new ServerTransfer();
            $transfer->server_id = $server->id;
            $transfer->old_node = $server->node_id;
            $transfer->new_node = $nodeid;
            $transfer->old_allocation = $server->allocation_id;
            $transfer->new_allocation = $allocation->id;
            $transfer->old_additional_allocations = $server->allocations
                ->where('id', '!=', $server->allocation_id)
                ->pluck('id')
                ->values()
                ->toArray();
            $transfer->new_additional_allocations = [];
            $transfer->save();

            $unassigned = $this->allocationrepository->getUnassignedAllocationIds($nodeid);
            if (in_array($allocation->id, $unassigned, true)) {
                $this->allocationrepository->updateWhereIn('id', [$allocation->id], ['server_id' => $server->id]);
            }

            $token = $this->nodejwtservice
                ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
                ->setSubject($server->uuid)
                ->setScopes(JwtScope::ServerTransfer)
                ->handle($transfer->newNode, $server->uuid);

            $this->daemontransferrepository->setServer($server)->notify($transfer->newNode, $token);
        });

        return $this->view($server->refresh());
    }

    private function transform(Server $server): array
    {
        return [
            'id' => $server->id,
            'external_id' => $server->external_id,
            'uuid' => $server->uuid,
            'identifier' => $server->uuidShort,
            'name' => $server->name,
            'description' => $server->description,
            'status' => $server->transfer ? 'transferring' : $server->status,
            'suspended' => $server->status === Server::STATUS_SUSPENDED,
            'installed' => $server->isInstalled(),
            'installed_at' => $server->installed_at?->toIso8601String(),
            'limits' => [
                'cpu' => $server->cpu,
                'threads' => $server->threads,
                'memory' => $server->memory,
                'swap' => $server->swap,
                'disk' => $server->disk,
                'io' => $server->io,
                'oom_disabled' => $server->oom_disabled,
                'database_limit' => $server->database_limit,
                'allocation_limit' => $server->allocation_limit,
                'backup_limit' => $server->backup_limit,
            ],
            'owner' => $server->user ? [
                ...$this->transformuser($server->user),
            ] : null,
            'node' => $server->node ? [
                'id' => $server->node->id,
                'name' => $server->node->name,
            ] : null,
            'egg' => $server->egg ? [
                'id' => $server->egg->id,
                'name' => $server->egg->name,
                'nest' => $server->egg->nest?->name,
            ] : null,
            'allocation' => $server->allocation ? [
                'id' => $server->allocation->id,
                'ip' => $server->allocation->ip,
                'alias' => $server->allocation->ip_alias,
                'port' => $server->allocation->port,
            ] : null,
        ];
    }

    private function transformuser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->name_first,
            'last_name' => $user->name_last,
        ];
    }

    private function transformallocation($allocation): array
    {
        return [
            'id' => $allocation->id,
            'ip' => $allocation->ip,
            'alias' => $allocation->ip_alias,
            'port' => $allocation->port,
        ];
    }
}
