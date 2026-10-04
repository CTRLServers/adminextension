<?php

namespace CTRLServers\AdminExtension;

use CTRLServers\AdminExtension\Http\Controllers\AdvancedSettingsController;
use CTRLServers\AdminExtension\Http\Controllers\GeneralSettingsController;
use CTRLServers\AdminExtension\Http\Controllers\MailSettingsController;
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

                Route::prefix('/ctrlservers/settings')->group(function (): void {
                    Route::get('/general', [GeneralSettingsController::class, 'show'])
                        ->name('ctrlservers.adminextension.settings.general.show');
                    Route::patch('/general', [GeneralSettingsController::class, 'update'])
                        ->name('ctrlservers.adminextension.settings.general.update');
                    Route::get('/mail', [MailSettingsController::class, 'show'])
                        ->name('ctrlservers.adminextension.settings.mail.show');
                    Route::patch('/mail', [MailSettingsController::class, 'update'])
                        ->name('ctrlservers.adminextension.settings.mail.update');
                    Route::get('/advanced', [AdvancedSettingsController::class, 'show'])
                        ->name('ctrlservers.adminextension.settings.advanced.show');
                    Route::patch('/advanced', [AdvancedSettingsController::class, 'update'])
                        ->name('ctrlservers.adminextension.settings.advanced.update');
                });
            });
    }
}
