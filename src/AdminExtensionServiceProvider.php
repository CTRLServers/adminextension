<?php

namespace CTRLServers\AdminExtension;

use CTRLServers\AdminExtension\Http\Controllers\PanelInformationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdminExtensionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'application-api', 'throttle:api.application'])
            ->prefix('/admin')
            ->group(function (): void {
                Route::get('/gettitle', [PanelInformationController::class, 'title'])
                    ->name('ctrlservers.adminextension.title');
                Route::get('/getpanelversion', [PanelInformationController::class, 'version'])
                    ->name('ctrlservers.adminextension.version');
            });
    }
}
