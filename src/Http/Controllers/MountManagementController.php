<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Mount;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Node;
use Ramsey\Uuid\Uuid;

class MountManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $mounts = Mount::query()
            ->withCount(['eggs', 'nodes', 'servers'])
            ->orderBy('id')
            ->get()
            ->map(fn (Mount $mount): array => $this->transform($mount));

        return response()->json(['mounts' => $mounts])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $values = $this->validatevalues($request);

        $mount = new Mount();
        $mount->fill($values);
        $mount->forceFill(['uuid' => Uuid::uuid4()->toString()]);
        $mount->saveOrFail();
        $mount->loadCount(['eggs', 'nodes', 'servers']);

        return response()->json(['mount' => $this->transform($mount)], 201);
    }

    public function view(Mount $mount): JsonResponse
    {
        $mount->load(['eggs' => fn ($query) => $query->with('nest')->orderBy('name'), 'nodes' => fn ($query) => $query->with('location')->orderBy('name')]);
        $mount->loadCount(['eggs', 'nodes', 'servers']);

        $data = $this->transform($mount);
        $data['egg_list'] = $mount->eggs->map(fn (Egg $egg): array => [
            'id' => $egg->id,
            'name' => $egg->name,
            'nest' => $egg->nest?->name,
        ])->values();
        $data['node_list'] = $mount->nodes->map(fn (Node $node): array => [
            'id' => $node->id,
            'name' => $node->name,
            'fqdn' => $node->fqdn,
            'location' => $node->location?->long,
        ])->values();
        $data['nest_options'] = Nest::query()
            ->with(['eggs' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Nest $nest): array => [
                'id' => $nest->id,
                'name' => $nest->name,
                'eggs' => $nest->eggs->map(fn (Egg $egg): array => [
                    'id' => $egg->id,
                    'name' => $egg->name,
                    'assigned' => $mount->eggs->contains('id', $egg->id),
                ])->values(),
            ])->values();
        $data['location_options'] = Location::query()
            ->with(['nodes' => fn ($query) => $query->orderBy('name')])
            ->orderBy('long')
            ->get()
            ->map(fn (Location $location): array => [
                'id' => $location->id,
                'name' => $location->long,
                'nodes' => $location->nodes->map(fn (Node $node): array => [
                    'id' => $node->id,
                    'name' => $node->name,
                    'fqdn' => $node->fqdn,
                    'assigned' => $mount->nodes->contains('id', $node->id),
                ])->values(),
            ])->values();

        return response()->json(['mount' => $data])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Mount $mount): JsonResponse
    {
        $mount->forceFill($this->validatevalues($request, $mount))->saveOrFail();

        return $this->view($mount->refresh());
    }

    public function destroy(Mount $mount): JsonResponse
    {
        $mount->delete();

        return response()->json([]);
    }

    public function addegg(Request $request, Mount $mount): JsonResponse
    {
        $values = $request->validate(['egg_id' => ['required', 'integer', 'exists:eggs,id']]);
        $mount->eggs()->syncWithoutDetaching([(int) $values['egg_id']]);

        return $this->view($mount->refresh());
    }

    public function removeegg(Mount $mount, Egg $egg): JsonResponse
    {
        $mount->eggs()->detach($egg->id);

        return $this->view($mount->refresh());
    }

    public function addnode(Request $request, Mount $mount): JsonResponse
    {
        $values = $request->validate(['node_id' => ['required', 'integer', 'exists:nodes,id']]);
        $mount->nodes()->syncWithoutDetaching([(int) $values['node_id']]);

        return $this->view($mount->refresh());
    }

    public function removenode(Mount $mount, Node $node): JsonResponse
    {
        $mount->nodes()->detach($node->id);

        return $this->view($mount->refresh());
    }

    private function validatevalues(Request $request, ?Mount $mount = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:64', Rule::unique('mounts', 'name')->ignore($mount?->id)],
            'description' => ['nullable', 'string', 'max:191'],
            'source' => ['required', 'string', 'max:191', Rule::notIn(Mount::$invalidSourcePaths)],
            'target' => ['required', 'string', 'max:191', Rule::notIn(Mount::$invalidTargetPaths)],
            'read_only' => ['required', 'boolean'],
            'user_mountable' => ['required', 'boolean'],
        ]);
    }

    private function transform(Mount $mount): array
    {
        return [
            'id' => $mount->id,
            'uuid' => $mount->uuid,
            'name' => $mount->name,
            'description' => $mount->description,
            'source' => $mount->source,
            'target' => $mount->target,
            'read_only' => (bool) $mount->read_only,
            'user_mountable' => (bool) $mount->user_mountable,
            'eggs' => (int) ($mount->eggs_count ?? 0),
            'nodes' => (int) ($mount->nodes_count ?? 0),
            'servers' => (int) ($mount->servers_count ?? 0),
        ];
    }
}
