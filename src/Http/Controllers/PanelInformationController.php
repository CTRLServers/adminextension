<?php

namespace CTRLServers\AdminExtension\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

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
        return response()
            ->json(['version' => (string) config('app.version', 'unknown')])
            ->header('Cache-Control', 'no-store');
    }
}
