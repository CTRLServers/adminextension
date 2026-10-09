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
        $latestrelease = $this->latestrelease();
        $latestversion = $latestrelease['version'] ?? null;
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
                'release_url' => $latestrelease['url'] ?? null,
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function system(): JsonResponse
    {
        $cpu = $this->cpudetails();
        $memory = $this->memorydetails();
        $disk = $this->diskdetails();

        return response()
            ->json([
                'cpu' => $cpu,
                'memory' => $memory,
                'disk' => $disk,
            ])
            ->header('Cache-Control', 'no-store');
    }

    private function cpudetails(): array
    {
        $information = @file_get_contents('/proc/cpuinfo') ?: '';
        preg_match('/^model name\s*:\s*(.+)$/mi', $information, $model);
        preg_match_all('/^processor\s*:/mi', $information, $processors);
        $cores = max(1, count($processors[0] ?? []));
        $first = $this->cputimes();
        usleep(100000);
        $second = $this->cputimes();
        $used = null;

        if ($first !== null && $second !== null) {
            $total = $second['total'] - $first['total'];
            $idle = $second['idle'] - $first['idle'];
            if ($total > 0) {
                $used = round(max(0, min(100, (($total - $idle) / $total) * 100)), 1);
            }
        }

        if ($used === null) {
            $load = sys_getloadavg();
            $used = round(max(0, min(100, ((float) ($load[0] ?? 0) / $cores) * 100)), 1);
        }

        return [
            'name' => trim($model[1] ?? php_uname('m')),
            'cores' => $cores,
            'used_percent' => $used,
        ];
    }

    private function cputimes(): ?array
    {
        $statistics = @file_get_contents('/proc/stat');
        if ($statistics === false || !preg_match('/^cpu\s+(.+)$/m', $statistics, $match)) {
            return null;
        }

        $values = array_map('intval', preg_split('/\s+/', trim($match[1])) ?: []);
        if (count($values) < 4) {
            return null;
        }

        return [
            'total' => array_sum($values),
            'idle' => ($values[3] ?? 0) + ($values[4] ?? 0),
        ];
    }

    private function memorydetails(): array
    {
        $information = @file_get_contents('/proc/meminfo') ?: '';
        preg_match('/^MemTotal:\s+(\d+)\s+kB$/mi', $information, $totalmatch);
        preg_match('/^MemAvailable:\s+(\d+)\s+kB$/mi', $information, $availablematch);
        $total = (int) ($totalmatch[1] ?? 0) * 1024;
        $available = (int) ($availablematch[1] ?? 0) * 1024;

        return [
            'used' => max(0, $total - $available),
            'total' => $total,
        ];
    }

    private function diskdetails(): array
    {
        $total = (int) (@disk_total_space(base_path()) ?: 0);
        $free = (int) (@disk_free_space(base_path()) ?: 0);

        return [
            'used' => max(0, $total - $free),
            'total' => $total,
        ];
    }

    private function latestrelease(): ?array
    {
        try {
            return Cache::remember('ctrlservers.adminextension.latest_panel_release', now()->addHour(), function (): ?array {
                $response = Http::acceptJson()
                    ->withHeaders(['User-Agent' => 'CTRLServers Admin Extension'])
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->get('https://api.github.com/repos/pterodactyl/panel/releases/latest');

                if (!$response->successful()) {
                    return null;
                }

                $latestversion = ltrim((string) $response->json('tag_name'), 'vV');

                if ($latestversion === '') {
                    return null;
                }

                return [
                    'version' => $latestversion,
                    'url' => (string) $response->json('html_url'),
                ];
            });
        } catch (Throwable) {
            return null;
        }
    }
}
