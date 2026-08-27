<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetImportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EqtImportController;
use App\Http\Controllers\ManpowerController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PjctBudgetController;
use App\Http\Controllers\PjctDocController;
use App\Http\Controllers\ProjectImportController;
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

    // Project sub-pages: open to every role (M-03 Asset, M-04 Manpower — all ✓)
    Route::get('/project/assets', [AssetController::class, 'index'])->name('project.assets');
    Route::get('/project/manpower', [ManpowerController::class, 'index'])->name('project.manpower');

    // Bulk insert aset dari Excel — superadmin only
    Route::middleware('role:superadmin')->prefix('/project/assets/import')->name('project.assets.import.')->group(function (): void {
        Route::get('/template', [AssetImportController::class, 'downloadTemplate'])->name('template');
        Route::post('/preview', [AssetImportController::class, 'preview'])->name('preview');
        Route::post('/confirm', [AssetImportController::class, 'confirm'])->name('confirm');
        Route::post('/cancel', [AssetImportController::class, 'cancel'])->name('cancel');
    });

    // Report Issues (M-07 — all ✓)
    Route::get('/report/issues', fn () => view('report.issues'))->name('report.issues');

    // Project monitoring view (M-02 Project Main — all ✓)
    Route::prefix('/monitoring')->name('monitoring.')->group(function (): void {
        Route::get('/', [MonitoringController::class, 'index'])->name('index');
        Route::get('/docs/{pjctDoc}', [PjctDocController::class, 'show'])->name('docs.show');
    });

    Route::middleware('role:superadmin,admin,siteadmin,vip')->group(function (): void {
        // M-05/M-06 Vendor, M-08 Report Expenses — User (client-tier) excluded
        Route::get('/vendor', fn () => view('vendor.main'))->name('vendor.main');
        Route::get('/vendor/contracts', fn () => view('vendor.contracts'))->name('vendor.contracts');
        Route::get('/report/expenses', fn () => view('report.expenses'))->name('report.expenses');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/csv', [ReportController::class, 'export'])->name('reports.export');
    });

    Route::prefix('/monitoring')->name('monitoring.')->group(function (): void {
        // Bulk insert project dari Excel — superadmin only.
        // Harus terdaftar sebelum `/{type}/{id}` di bawah, yang kalau tidak akan menelan
        // `/monitoring/projects/import/...` sebagai {type}/{id}.
        Route::middleware('role:superadmin')->prefix('/projects/import')->name('projects.import.')->group(function (): void {
            Route::get('/template', [ProjectImportController::class, 'downloadTemplate'])->name('template');
            Route::post('/preview', [ProjectImportController::class, 'preview'])->name('preview');
            Route::post('/confirm', [ProjectImportController::class, 'confirm'])->name('confirm');
            Route::post('/cancel', [ProjectImportController::class, 'cancel'])->name('cancel');
        });

        // T-17 Edit proyek: Site Admin can also edit
        Route::middleware('role:superadmin,admin,siteadmin')->group(function (): void {
            Route::put('/{type}/{id}', [MonitoringController::class, 'update'])->name('update');
        });

        // T-16/18/19 Create/Delete/Restore proyek: Site Admin excluded
        Route::middleware('role:superadmin,admin')->group(function (): void {
            Route::post('/{type}', [MonitoringController::class, 'store'])->name('store');
            Route::delete('/{type}/{id}', [MonitoringController::class, 'destroy'])->name('destroy');
            Route::post('/{type}/{id}/restore', [MonitoringController::class, 'restore'])->name('restore');

            Route::get('/import', [EqtImportController::class, 'showImportPage'])->name('import.show');
            Route::post('/import/preview', [EqtImportController::class, 'preview'])->name('import.preview');
            Route::post('/import/confirm', [EqtImportController::class, 'confirm'])->name('import.confirm');
            Route::get('/import/template', [EqtImportController::class, 'downloadTemplate'])->name('import.template');
            Route::get('/import/template/{type}', [EqtImportController::class, 'downloadTemplateByType'])
                ->name('import.template.type')
                ->whereIn('type', ['project_eq', 'project_tech', 'handover', 'vehicle', 'maintenance']);
            Route::get('/export', [EqtImportController::class, 'export'])->name('export');

            Route::post('/projects/{project}/boq', [PjctBudgetController::class, 'store'])->name('boq.store');
            Route::post('/projects/{project}/docs', [PjctDocController::class, 'store'])->name('docs.store');
        });
    });

    Route::prefix('/admin')->name('admin.')->group(function (): void {
        Route::middleware('role:superadmin')->group(function (): void {
            Route::get('/', fn () => redirect()->route('admin.users.index'))->name('index');

            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::patch('/users/{managedUser}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{managedUser}', [UserController::class, 'destroy'])->name('users.destroy');
        });

        // T-24 View users (VIP read-only) + M-11/12/13 view for Admin tier too
        Route::middleware('role:superadmin,vip')->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
        });
    });
});
