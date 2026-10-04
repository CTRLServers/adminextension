<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use CTRLServers\AdminExtension\Services\PanelSettingsService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Pterodactyl\Traits\Helpers\AvailableLanguages;

class GeneralSettingsController extends Controller
{
    use AvailableLanguages;

    public function __construct(
        private ConfigRepository $config,
        private PanelSettingsService $settings,
    ) {
    }

    public function show(): JsonResponse
    {
        $languages = collect($this->getAvailableLanguages(true))
            ->map(fn (string $label, string $value): array => compact('value', 'label'))
            ->values();

        return response()->json([
            'fields' => [
                'company_name' => (string) $this->config->get('app.name', 'Pterodactyl'),
                'two_factor_requirement' => (int) $this->config->get('pterodactyl.auth.2fa_required', 0),
                'default_language' => (string) $this->config->get('app.locale', 'en'),
            ],
            'options' => [
                'two_factor_requirement' => [
                    ['value' => 0, 'label' => 'Not Required'],
                    ['value' => 1, 'label' => 'Admin Only'],
                    ['value' => 2, 'label' => 'All Users'],
                ],
                'languages' => $languages,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request): JsonResponse
    {
        $languages = array_keys($this->getAvailableLanguages());
        $values = $request->validate([
            'company_name' => ['required', 'string', 'max:191'],
            'two_factor_requirement' => ['required', 'integer', Rule::in([0, 1, 2])],
            'default_language' => ['required', 'string', Rule::in($languages)],
        ]);

        $this->settings->update([
            'app:name' => $values['company_name'],
            'pterodactyl:auth:2fa_required' => $values['two_factor_requirement'],
            'app:locale' => $values['default_language'],
        ]);

        return $this->show();
    }
}
