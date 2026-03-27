<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'create'])->name('home');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store');

Route::middleware(['auth', 'idle'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

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
    });

    Route::middleware('role:admin')->prefix('/admin')->name('admin.')->group(function (): void {
        Route::get('/', fn () => redirect()->route('admin.users.index'))->name('index');
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::get('/sla-policies', [AdminController::class, 'slaPolicies'])->name('sla.index');
        Route::get('/categories', [AdminController::class, 'categories'])->name('categories.index');
        Route::get('/projects', [AdminController::class, 'projects'])->name('projects.index');

        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::patch('/users/{managedUser}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{managedUser}', [AdminController::class, 'destroyUser'])->name('users.destroy');

        Route::post('/projects', [AdminController::class, 'storeProject'])->name('projects.store');
        Route::patch('/projects/{team}', [AdminController::class, 'updateProject'])->name('projects.update');
        Route::delete('/projects/{team}', [AdminController::class, 'destroyProject'])->name('projects.destroy');

        Route::post('/categories', [AdminController::class, 'storeCategory'])->name('categories.store');
        Route::patch('/categories/{category}', [AdminController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminController::class, 'destroyCategory'])->name('categories.destroy');

        Route::post('/sla-policies', [AdminController::class, 'storeSla'])->name('sla.store');
        Route::patch('/sla-policies/{slaPolicy}', [AdminController::class, 'updateSla'])->name('sla.update');
        Route::delete('/sla-policies/{slaPolicy}', [AdminController::class, 'destroySla'])->name('sla.destroy');
    });
});
