<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ProjectDeviceController;
use App\Http\Controllers\Admin\SlaPolicyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EqtImportController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'create'])->name('home');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store')->middleware('throttle:5,1');

Route::middleware(['auth', 'idle'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('/tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->name('tickets.messages.store');
    Route::post('/tickets/{ticket}/merge', [TicketController::class, 'merge'])->name('tickets.merge');
    Route::post('/tickets/{ticket}/split', [TicketController::class, 'split'])->name('tickets.split');
    Route::get('/tickets/{ticket}/attachments/{attachment}', [TicketController::class, 'download'])->name('tickets.attachments.download');

    Route::middleware('role:supervisor,admin')->group(function (): void {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/csv', [ReportController::class, 'export'])->name('reports.export');

        Route::prefix('/monitoring')->name('monitoring.')->group(function (): void {
            Route::get('/', [MonitoringController::class, 'index'])->name('index');

            Route::middleware('role:admin')->group(function (): void {
                Route::post('/{type}', [MonitoringController::class, 'store'])->name('store');
                Route::put('/{type}/{id}', [MonitoringController::class, 'update'])->name('update');
                Route::delete('/{type}/{id}', [MonitoringController::class, 'destroy'])->name('destroy');
                Route::post('/{type}/{id}/restore', [MonitoringController::class, 'restore'])->name('restore');

                Route::get('/import', [EqtImportController::class, 'showImportPage'])->name('import.show');
                Route::post('/import/preview', [EqtImportController::class, 'preview'])->name('import.preview');
                Route::post('/import/confirm', [EqtImportController::class, 'confirm'])->name('import.confirm');
                Route::get('/import/template', [EqtImportController::class, 'downloadTemplate'])->name('import.template');
                Route::get('/import/template/{type}', [EqtImportController::class, 'downloadTemplateByType'])
                    ->name('import.template.type')
                    ->whereIn('type', ['project_eq','project_tech','handover','vehicle','maintenance']);
                Route::get('/export', [EqtImportController::class, 'export'])->name('export');
            });
        });
    });

    Route::middleware('role:admin')->prefix('/admin')->name('admin.')->group(function (): void {
        Route::get('/', fn () => redirect()->route('admin.users.index'))->name('index');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/sla-policies', [SlaPolicyController::class, 'index'])->name('sla.index');
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{team}/devices', [ProjectDeviceController::class, 'show'])->name('projects.devices');
        Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::get('/devices/template', [DeviceController::class, 'downloadTemplate'])->name('devices.template');
        Route::get('/devices/export/csv', [DeviceController::class, 'export'])->name('devices.export');

        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{managedUser}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{managedUser}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::patch('/projects/{team}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{team}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::post('/devices/import', [DeviceController::class, 'import'])->name('devices.import');
        Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::patch('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::post('/sla-policies', [SlaPolicyController::class, 'store'])->name('sla.store');
        Route::patch('/sla-policies/{slaPolicy}', [SlaPolicyController::class, 'update'])->name('sla.update');
        Route::delete('/sla-policies/{slaPolicy}', [SlaPolicyController::class, 'destroy'])->name('sla.destroy');
    });
});

