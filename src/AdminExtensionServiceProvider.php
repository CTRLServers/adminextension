<?php

namespace CTRLServers\AdminExtension;

use CTRLServers\AdminExtension\Http\Controllers\AdvancedSettingsController;
use CTRLServers\AdminExtension\Http\Controllers\ApplicationApiController;
use CTRLServers\AdminExtension\Http\Controllers\DatabaseHostController;
use CTRLServers\AdminExtension\Http\Controllers\GeneralSettingsController;
use CTRLServers\AdminExtension\Http\Controllers\MailSettingsController;
use CTRLServers\AdminExtension\Http\Controllers\MountManagementController;
use CTRLServers\AdminExtension\Http\Controllers\NestManagementController;
use CTRLServers\AdminExtension\Http\Controllers\NodeConfigurationController;
use CTRLServers\AdminExtension\Http\Controllers\NodeResourcesController;
use CTRLServers\AdminExtension\Http\Controllers\NodeStatusController;
use CTRLServers\AdminExtension\Http\Controllers\PanelInformationController;
use CTRLServers\AdminExtension\Http\Controllers\ServerManagementController;
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
                Route::get('/getsysteminformation', [PanelInformationController::class, 'system'])
                    ->name('ctrlservers.adminextension.system');

                Route::get('/ctrlservers/application-api', [ApplicationApiController::class, 'index'])
                    ->name('ctrlservers.adminextension.application-api.index');
                Route::post('/ctrlservers/application-api', [ApplicationApiController::class, 'store'])
                    ->name('ctrlservers.adminextension.application-api.store');
                Route::delete('/ctrlservers/application-api/{apikey:id}', [ApplicationApiController::class, 'destroy'])
                    ->name('ctrlservers.adminextension.application-api.destroy');

                Route::get('/ctrlservers/database-hosts', [DatabaseHostController::class, 'index'])
                    ->name('ctrlservers.adminextension.database-hosts.index');
                Route::post('/ctrlservers/database-hosts', [DatabaseHostController::class, 'store'])
                    ->name('ctrlservers.adminextension.database-hosts.store');
                Route::get('/ctrlservers/database-hosts/{host:id}', [DatabaseHostController::class, 'view'])
                    ->name('ctrlservers.adminextension.database-hosts.view');
                Route::patch('/ctrlservers/database-hosts/{host:id}', [DatabaseHostController::class, 'update'])
                    ->name('ctrlservers.adminextension.database-hosts.update');

                Route::get('/ctrlservers/mounts', [MountManagementController::class, 'index'])
                    ->name('ctrlservers.adminextension.mounts.index');
                Route::post('/ctrlservers/mounts', [MountManagementController::class, 'store'])
                    ->name('ctrlservers.adminextension.mounts.store');
                Route::get('/ctrlservers/mounts/{mount:id}', [MountManagementController::class, 'view'])
                    ->name('ctrlservers.adminextension.mounts.view');
                Route::patch('/ctrlservers/mounts/{mount:id}', [MountManagementController::class, 'update'])
                    ->name('ctrlservers.adminextension.mounts.update');
                Route::delete('/ctrlservers/mounts/{mount:id}', [MountManagementController::class, 'destroy'])
                    ->name('ctrlservers.adminextension.mounts.destroy');
                Route::post('/ctrlservers/mounts/{mount:id}/eggs', [MountManagementController::class, 'addegg'])
                    ->name('ctrlservers.adminextension.mounts.eggs.add');
                Route::delete('/ctrlservers/mounts/{mount:id}/eggs/{egg:id}', [MountManagementController::class, 'removeegg'])
                    ->name('ctrlservers.adminextension.mounts.eggs.remove');
                Route::post('/ctrlservers/mounts/{mount:id}/nodes', [MountManagementController::class, 'addnode'])
                    ->name('ctrlservers.adminextension.mounts.nodes.add');
                Route::delete('/ctrlservers/mounts/{mount:id}/nodes/{node:id}', [MountManagementController::class, 'removenode'])
                    ->name('ctrlservers.adminextension.mounts.nodes.remove');

                Route::get('/ctrlservers/servers', [ServerManagementController::class, 'index'])
                    ->name('ctrlservers.adminextension.servers.index');
                Route::get('/ctrlservers/server-users', [ServerManagementController::class, 'users'])
                    ->name('ctrlservers.adminextension.servers.users');
                Route::get('/ctrlservers/servers/{server:id}', [ServerManagementController::class, 'view'])
                    ->name('ctrlservers.adminextension.servers.view');
                Route::patch('/ctrlservers/servers/{server:id}/details', [ServerManagementController::class, 'details'])
                    ->name('ctrlservers.adminextension.servers.details');
                Route::patch('/ctrlservers/servers/{server:id}/build', [ServerManagementController::class, 'build'])
                    ->name('ctrlservers.adminextension.servers.build');
                Route::patch('/ctrlservers/servers/{server:id}/startup', [ServerManagementController::class, 'startup'])
                    ->name('ctrlservers.adminextension.servers.startup');
                Route::post('/ctrlservers/servers/{server:id}/databases', [ServerManagementController::class, 'databasecreate'])
                    ->name('ctrlservers.adminextension.servers.databases.create');
                Route::delete('/ctrlservers/servers/{server:id}/databases/{database:id}', [ServerManagementController::class, 'databasedelete'])
                    ->name('ctrlservers.adminextension.servers.databases.delete');
                Route::post('/ctrlservers/servers/{server:id}/mounts/{mountid}', [ServerManagementController::class, 'mount'])
                    ->name('ctrlservers.adminextension.servers.mounts.mount');
                Route::delete('/ctrlservers/servers/{server:id}/mounts/{mountid}', [ServerManagementController::class, 'unmount'])
                    ->name('ctrlservers.adminextension.servers.mounts.unmount');
                Route::post('/ctrlservers/servers/{server:id}/reinstall', [ServerManagementController::class, 'reinstall'])
                    ->name('ctrlservers.adminextension.servers.reinstall');
                Route::post('/ctrlservers/servers/{server:id}/install', [ServerManagementController::class, 'install'])
                    ->name('ctrlservers.adminextension.servers.install');
                Route::post('/ctrlservers/servers/{server:id}/suspension', [ServerManagementController::class, 'suspension'])
                    ->name('ctrlservers.adminextension.servers.suspension');
                Route::post('/ctrlservers/servers/{server:id}/transfer', [ServerManagementController::class, 'transfer'])
                    ->name('ctrlservers.adminextension.servers.transfer');

                Route::get('/ctrlservers/nodes/{node:id}/status', [NodeStatusController::class, 'view'])
                    ->name('ctrlservers.adminextension.nodes.status');
                Route::get('/ctrlservers/nodes/{node:id}/information', [NodeStatusController::class, 'information'])
                    ->name('ctrlservers.adminextension.nodes.information');
                Route::get('/ctrlservers/nodes/{node:id}/configuration', [NodeConfigurationController::class, 'view'])
                    ->name('ctrlservers.adminextension.nodes.configuration');
                Route::post('/ctrlservers/nodes/{node:id}/deployment', [NodeConfigurationController::class, 'deployment'])
                    ->name('ctrlservers.adminextension.nodes.deployment');
                Route::get('/ctrlservers/nodes/{node:id}/servers', [NodeResourcesController::class, 'servers'])
                    ->name('ctrlservers.adminextension.nodes.servers');
                Route::get('/ctrlservers/nodes/{node:id}/allocations', [NodeResourcesController::class, 'allocations'])
                    ->name('ctrlservers.adminextension.nodes.allocations');

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

                Route::prefix('/ctrlservers/nests')->group(function (): void {
                    Route::get('/', [NestManagementController::class, 'index']);
                    Route::get('/{nest:id}', [NestManagementController::class, 'view']);
                    Route::patch('/{nest:id}', [NestManagementController::class, 'update']);
                    Route::delete('/{nest:id}', [NestManagementController::class, 'destroy']);
                    Route::get('/{nest:id}/eggs/{egg:id}', [NestManagementController::class, 'egg']);
                    Route::patch('/{nest:id}/eggs/{egg:id}/configuration', [NestManagementController::class, 'eggconfiguration']);
                    Route::patch('/{nest:id}/eggs/{egg:id}/install-script', [NestManagementController::class, 'eggscript']);
                    Route::post('/{nest:id}/eggs/{egg:id}/variables', [NestManagementController::class, 'variablecreate']);
                    Route::patch('/{nest:id}/eggs/{egg:id}/variables/{variable:id}', [NestManagementController::class, 'variableupdate']);
                    Route::delete('/{nest:id}/eggs/{egg:id}/variables/{variable:id}', [NestManagementController::class, 'variabledelete']);
                    Route::get('/{nest:id}/eggs/{egg:id}/export', [NestManagementController::class, 'export']);
                    Route::post('/{nest:id}/eggs/{egg:id}/import', [NestManagementController::class, 'import']);
                });
            });
    }
}
