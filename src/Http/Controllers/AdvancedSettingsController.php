<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use CTRLServers\AdminExtension\Services\PanelSettingsService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class AdvancedSettingsController extends Controller
{
    public function __construct(
        private ConfigRepository $config,
        private PanelSettingsService $settings,
    ) {
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'fields' => [
                'recaptcha_enabled' => (bool) $this->config->get('recaptcha.enabled', false),
                'recaptcha_site_key' => (string) $this->config->get('recaptcha.website_key', ''),
                'recaptcha_secret_key' => (string) $this->config->get('recaptcha.secret_key', ''),
                'connection_timeout' => (int) $this->config->get('pterodactyl.guzzle.connect_timeout', 5),
                'request_timeout' => (int) $this->config->get('pterodactyl.guzzle.timeout', 15),
                'automatic_allocation_enabled' => (bool) $this->config->get('pterodactyl.client_features.allocations.enabled', false),
                'starting_port' => $this->nullableinteger($this->config->get('pterodactyl.client_features.allocations.range_start')),
                'ending_port' => $this->nullableinteger($this->config->get('pterodactyl.client_features.allocations.range_end')),
            ],
            'options' => [
                'recaptcha_enabled' => [
                    ['value' => true, 'label' => 'Enabled'],
                    ['value' => false, 'label' => 'Disabled'],
                ],
                'automatic_allocation_enabled' => [
                    ['value' => false, 'label' => 'Disabled'],
                    ['value' => true, 'label' => 'Enabled'],
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request): JsonResponse
    {
        $allocationenabled = $request->boolean('automatic_allocation_enabled');
        $values = $request->validate([
            'recaptcha_enabled' => ['required', 'boolean'],
            'recaptcha_site_key' => ['required', 'string', 'max:191'],
            'recaptcha_secret_key' => ['required', 'string', 'max:191'],
            'connection_timeout' => ['required', 'integer', 'between:1,60'],
            'request_timeout' => ['required', 'integer', 'between:1,60'],
            'automatic_allocation_enabled' => ['required', 'boolean'],
            'starting_port' => [
                'nullable',
                Rule::requiredIf($allocationenabled),
                'integer',
                'between:1024,65535',
            ],
            'ending_port' => [
                'nullable',
                Rule::requiredIf($allocationenabled),
                'integer',
                'between:1024,65535',
                'gt:starting_port',
            ],
        ]);

        $this->settings->update([
            'recaptcha:enabled' => $values['recaptcha_enabled'],
            'recaptcha:website_key' => $values['recaptcha_site_key'],
            'recaptcha:secret_key' => $values['recaptcha_secret_key'],
            'pterodactyl:guzzle:connect_timeout' => $values['connection_timeout'],
            'pterodactyl:guzzle:timeout' => $values['request_timeout'],
            'pterodactyl:client_features:allocations:enabled' => $values['automatic_allocation_enabled'],
            'pterodactyl:client_features:allocations:range_start' => $values['starting_port'] ?? null,
            'pterodactyl:client_features:allocations:range_end' => $values['ending_port'] ?? null,
        ]);

        return $this->show();
    }

    private function nullableinteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
