<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use CTRLServers\AdminExtension\Services\PanelSettingsService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MailSettingsController extends Controller
{
    public function __construct(
        private ConfigRepository $config,
        private PanelSettingsService $settings,
    ) {
    }

    public function show(): JsonResponse
    {
        $password = $this->config->get('mail.mailers.smtp.password');

        return response()->json([
            'smtp_available' => $this->config->get('mail.default') === 'smtp',
            'mail_driver' => (string) $this->config->get('mail.default', ''),
            'fields' => [
                'smtp_host' => (string) $this->config->get('mail.mailers.smtp.host', ''),
                'smtp_username' => $this->config->get('mail.mailers.smtp.username'),
                'smtp_port' => (int) $this->config->get('mail.mailers.smtp.port', 25),
                'encryption' => $this->config->get('mail.mailers.smtp.encryption'),
                'smtp_password' => null,
                'smtp_password_configured' => $password !== null && $password !== '',
                'mail_from' => (string) $this->config->get('mail.from.address', ''),
                'mail_from_name' => $this->config->get('mail.from.name'),
            ],
            'options' => [
                'encryption' => [
                    ['value' => 'tls', 'label' => 'TLS'],
                    ['value' => 'ssl', 'label' => 'SSL'],
                ],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request): JsonResponse
    {
        if ($this->config->get('mail.default') !== 'smtp') {
            throw ValidationException::withMessages([
                'mail_driver' => 'The panel must use the SMTP mail driver before these settings can be changed.',
            ]);
        }

        $values = $request->validate([
            'smtp_host' => ['required', 'string'],
            'smtp_username' => ['present', 'nullable', 'string', 'max:191'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl'])],
            'smtp_password' => ['sometimes', 'nullable', 'string', 'max:191'],
            'mail_from' => ['required', 'string', 'email'],
            'mail_from_name' => ['present', 'nullable', 'string', 'max:191'],
        ]);

        $settings = [
            'mail:mailers:smtp:host' => $values['smtp_host'],
            'mail:mailers:smtp:username' => $values['smtp_username'],
            'mail:mailers:smtp:port' => $values['smtp_port'],
            'mail:mailers:smtp:encryption' => $values['encryption'],
            'mail:from:address' => $values['mail_from'],
            'mail:from:name' => $values['mail_from_name'],
        ];

        if (isset($values['smtp_password']) && $values['smtp_password'] !== '') {
            $settings['mail:mailers:smtp:password'] = $values['smtp_password'];
        }

        $this->settings->update($settings, ['mail:mailers:smtp:password']);

        return $this->show();
    }
}
