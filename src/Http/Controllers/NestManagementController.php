<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Nest;
use Pterodactyl\Services\Eggs\EggUpdateService;
use Pterodactyl\Services\Eggs\Scripts\InstallScriptService;
use Pterodactyl\Services\Eggs\Sharing\EggExporterService;
use Pterodactyl\Services\Eggs\Sharing\EggUpdateImporterService;
use Pterodactyl\Services\Eggs\Variables\VariableCreationService;
use Pterodactyl\Services\Eggs\Variables\VariableUpdateService;
use Pterodactyl\Services\Nests\NestDeletionService;
use Pterodactyl\Services\Nests\NestUpdateService;

class NestManagementController extends Controller
{
    public function __construct(
        private NestUpdateService $nestupdate,
        private NestDeletionService $nestdelete,
        private EggUpdateService $eggupdate,
        private InstallScriptService $scriptupdate,
        private EggExporterService $eggexport,
        private EggUpdateImporterService $eggimport,
        private VariableCreationService $variablecreate,
        private VariableUpdateService $variableupdate,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perpage = max(1, min(100, (int) $request->query('per_page', 25)));
        $query = Nest::query()->withCount(['eggs', 'servers'])->orderBy('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $nests = $query->paginate($perpage);

        return response()->json([
            'data' => collect($nests->items())->map(fn (Nest $nest): array => $this->nestdata($nest))->values(),
            'meta' => [
                'pagination' => [
                    'total' => $nests->total(),
                    'count' => $nests->count(),
                    'per_page' => $nests->perPage(),
                    'current_page' => $nests->currentPage(),
                    'total_pages' => $nests->lastPage(),
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function view(Nest $nest): JsonResponse
    {
        $nest->loadCount(['eggs', 'servers']);
        $nest->load(['eggs' => fn ($query) => $query->withCount('servers')->orderBy('id')]);

        return response()->json([
            'nest' => $this->nestdata($nest),
            'eggs' => $nest->eggs->map(fn (Egg $egg): array => $this->eggsummary($egg))->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Nest $nest): JsonResponse
    {
        $values = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
        ]);

        $this->nestupdate->handle($nest->id, $values);

        return $this->view($nest->fresh());
    }

    public function destroy(Nest $nest): JsonResponse
    {
        if ($nest->servers()->exists()) {
            return response()->json([
                'message' => 'A Nest with active servers attached to it cannot be deleted from the Panel.',
            ], 409);
        }

        $this->nestdelete->handle($nest->id);

        return response()->json([]);
    }

    public function egg(Nest $nest, Egg $egg): JsonResponse
    {
        $this->ensureegg($nest, $egg);
        $egg->load('variables')->loadCount('servers');
        $options = Egg::query()->where('nest_id', $nest->id)->orderBy('name')->get();

        return response()->json([
            'egg' => $this->eggdata($egg),
            'variables' => $egg->variables->map(fn (EggVariable $variable): array => $this->variabledata($variable))->values(),
            'options' => $options->map(fn (Egg $option): array => [
                'id' => $option->id,
                'name' => $option->name,
            ])->values(),
            'containers' => $options->pluck('script_container')->filter()->unique()->values(),
            'entries' => $options->pluck('script_entry')->filter()->unique()->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function eggconfiguration(Request $request, Nest $nest, Egg $egg): JsonResponse
    {
        $this->ensureegg($nest, $egg);
        $values = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'docker_images' => ['required', 'array', 'min:1'],
            'docker_images.*' => ['required', 'string', 'max:191'],
            'force_outgoing_ip' => ['required', 'boolean'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:191'],
            'startup' => ['required', 'string'],
            'config_from' => ['nullable', 'integer', 'exists:eggs,id'],
            'config_stop' => ['nullable', 'string', 'max:191'],
            'config_startup' => ['nullable', 'json'],
            'config_logs' => ['nullable', 'json'],
            'config_files' => ['nullable', 'json'],
        ]);
        $values['features'] = $values['features'] ?? [];
        $this->eggupdate->handle($egg, $values);

        return $this->egg($nest, $egg->fresh());
    }

    public function eggscript(Request $request, Nest $nest, Egg $egg): JsonResponse
    {
        $this->ensureegg($nest, $egg);
        $values = $request->validate([
            'script_install' => ['nullable', 'string'],
            'script_is_privileged' => ['required', 'boolean'],
            'script_entry' => ['required', 'string'],
            'script_container' => ['required', 'string'],
            'copy_script_from' => ['nullable', 'integer', 'exists:eggs,id'],
        ]);
        $this->scriptupdate->handle($egg, $values);

        return $this->egg($nest, $egg->fresh());
    }

    public function variablecreate(Request $request, Nest $nest, Egg $egg): JsonResponse
    {
        $this->ensureegg($nest, $egg);
        $values = $this->variablevalues($request);
        $variable = $this->variablecreate->handle($egg->id, $values);

        return response()->json(['variable' => $this->variabledata($variable)], 201);
    }

    public function variableupdate(Request $request, Nest $nest, Egg $egg, EggVariable $variable): JsonResponse
    {
        $this->ensureegg($nest, $egg);
        $this->ensurevariable($egg, $variable);
        $this->variableupdate->handle($variable, $this->variablevalues($request));

        return response()->json(['variable' => $this->variabledata($variable->fresh())]);
    }

    public function variabledelete(Nest $nest, Egg $egg, EggVariable $variable): JsonResponse
    {
        $this->ensureegg($nest, $egg);
        $this->ensurevariable($egg, $variable);
        $variable->delete();

        return response()->json([]);
    }

    public function export(Nest $nest, Egg $egg): JsonResponse
    {
        $this->ensureegg($nest, $egg);

        return response()->json([
            'filename' => 'egg-' . Str::slug($egg->name) . '.json',
            'content' => $this->eggexport->handle($egg->id),
        ]);
    }

    public function import(Request $request, Nest $nest, Egg $egg): JsonResponse
    {
        $this->ensureegg($nest, $egg);
        $values = $request->validate(['egg' => ['required', 'array']]);
        $path = tempnam(sys_get_temp_dir(), 'ctrlservers-egg-');

        if ($path === false) {
            return response()->json(['message' => 'Unable to prepare the Egg file.'], 500);
        }

        file_put_contents($path, json_encode($values['egg'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $file = new UploadedFile($path, 'egg.json', 'application/json', null, true);

        try {
            $updated = $this->eggimport->handle($egg, $file);
        } finally {
            @unlink($path);
        }

        return $this->egg($nest, $updated);
    }

    private function variablevalues(Request $request): array
    {
        $values = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'env_variable' => ['required', 'regex:/^[\w]{1,191}$/'],
            'default_value' => ['present'],
            'rules' => ['required', 'string'],
            'user_viewable' => ['required', 'boolean'],
            'user_editable' => ['required', 'boolean'],
        ]);
        $values['options'] = [];
        if ($values['user_viewable']) $values['options'][] = 'user_viewable';
        if ($values['user_editable']) $values['options'][] = 'user_editable';

        return $values;
    }

    private function nestdata(Nest $nest): array
    {
        return [
            'id' => $nest->id,
            'uuid' => $nest->uuid,
            'author' => $nest->author,
            'name' => $nest->name,
            'description' => $nest->description,
            'eggs' => (int) ($nest->eggs_count ?? 0),
            'servers' => (int) ($nest->servers_count ?? 0),
        ];
    }

    private function eggsummary(Egg $egg): array
    {
        return [
            'id' => $egg->id,
            'uuid' => $egg->uuid,
            'name' => $egg->name,
            'description' => $egg->description,
            'servers' => (int) ($egg->servers_count ?? 0),
        ];
    }

    private function eggdata(Egg $egg): array
    {
        return array_merge($this->eggsummary($egg), [
            'author' => $egg->author,
            'docker_images' => $egg->docker_images,
            'features' => $egg->features ?? [],
            'force_outgoing_ip' => (bool) $egg->force_outgoing_ip,
            'startup' => $egg->startup,
            'config_from' => $egg->config_from,
            'config_stop' => $egg->config_stop,
            'config_startup' => $egg->config_startup,
            'config_logs' => $egg->config_logs,
            'config_files' => $egg->config_files,
            'script_is_privileged' => (bool) $egg->script_is_privileged,
            'script_install' => $egg->script_install,
            'script_entry' => $egg->script_entry,
            'script_container' => $egg->script_container,
            'copy_script_from' => $egg->copy_script_from,
        ]);
    }

    private function variabledata(EggVariable $variable): array
    {
        return [
            'id' => $variable->id,
            'name' => $variable->name,
            'description' => $variable->description,
            'env_variable' => $variable->env_variable,
            'default_value' => $variable->default_value,
            'rules' => $variable->rules,
            'user_viewable' => (bool) $variable->user_viewable,
            'user_editable' => (bool) $variable->user_editable,
        ];
    }

    private function ensureegg(Nest $nest, Egg $egg): void
    {
        abort_unless($egg->nest_id === $nest->id, 404);
    }

    private function ensurevariable(Egg $egg, EggVariable $variable): void
    {
        abort_unless($variable->egg_id === $egg->id, 404);
    }
}
