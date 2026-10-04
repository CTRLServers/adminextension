<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class PanelInformationController extends Controller
{
    public function title(): JsonResponse
    {
        return response()
            ->json(['title' => (string) config('app.name', 'Pterodactyl')])
            ->header('Cache-Control', 'no-store');
    }

    public function version(): JsonResponse
    {
        $version = (string) config('app.version', 'unknown');
        $latestversion = $this->latestversion();
        $normalizedversion = ltrim($version, 'vV');
        $uptodate = null;

        if ($latestversion !== null && preg_match('/^\d+(?:\.\d+)+(?:[-+][0-9A-Za-z.-]+)?$/', $normalizedversion)) {
            $uptodate = version_compare($normalizedversion, $latestversion, '>=');
        }

        return response()
            ->json([
                'version' => $version,
                'latest_version' => $latestversion,
                'up_to_date' => $uptodate,
                'update_check_available' => $latestversion !== null,
            ])
            ->header('Cache-Control', 'no-store');
    }

    private function latestversion(): ?string
    {
        try {
            return Cache::remember('ctrlservers.adminextension.latest_panel_version', now()->addHour(), function (): ?string {
                $response = Http::acceptJson()
                    ->withHeaders(['User-Agent' => 'CTRLServers Admin Extension'])
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->get('https://api.github.com/repos/pterodactyl/panel/releases/latest');

                if (!$response->successful()) {
                    return null;
                }

                $latestversion = ltrim((string) $response->json('tag_name'), 'vV');

                return $latestversion !== '' ? $latestversion : null;
            });
        } catch (Throwable) {
            return null;
        }
    }
}
