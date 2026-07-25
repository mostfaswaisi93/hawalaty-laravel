<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\BackupController;
use App\Http\Controllers\Api\V1\Admin\BoxController;
use App\Http\Controllers\Api\V1\Admin\ExpenseController as AdminExpenseController;
use App\Http\Controllers\Api\V1\Admin\FinanceController;
use App\Http\Controllers\Api\V1\Admin\LicenseController;
use App\Http\Controllers\Api\V1\Admin\PackageController;
use App\Http\Controllers\Api\V1\Admin\PartnerController;
use App\Http\Controllers\Api\V1\Admin\PaymentController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Admin\WithdrawalController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\BankController;
use App\Http\Controllers\Api\V1\ConstantListController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\MenuCategoryController;
use App\Http\Controllers\Api\V1\MenuItemController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use App\Http\Controllers\Api\V1\TransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('auth/signup', [AuthController::class, 'signup']);
    Route::middleware(['tenant.header', 'auth:sanctum'])->post('auth/logout', [AuthController::class, 'logout']);

    // Tenant-space domain routes - every route here requires the
    // X-License-Code header (tenant.header) resolved BEFORE auth:sanctum
    // checks the bearer token against the correct tenant database, see
    // BACKEND_PROMPT.md section 8.1. `throttle:api` is inherited from the
    // global `api` middleware group (see bootstrap/app.php's
    // $middleware->throttleApi() call), not added again here.
    //
    // Reserved for the admin/platform task: nothing under this prefix may
    // collide with `/admin/*` (Super Admin) routes, which never pass
    // through tenant.header and stay on the platform's default connection.
    Route::middleware(['tenant.header', 'auth:sanctum'])->group(function () {
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('dashboard/weekly-activity', [DashboardController::class, 'weeklyActivity']);

        Route::get('transfers', [TransferController::class, 'index']);
        Route::post('transfers', [TransferController::class, 'store']);
        Route::get('transfers/{transfer}', [TransferController::class, 'show']);
        Route::put('transfers/{transfer}', [TransferController::class, 'update']);
        Route::delete('transfers/{transfer}', [TransferController::class, 'destroy']);
        Route::get('history', [TransferController::class, 'index']);

        Route::get('banks', [BankController::class, 'index']);
        Route::post('banks', [BankController::class, 'store']);
        Route::put('banks/{bank}', [BankController::class, 'update']);
        Route::delete('banks/{bank}', [BankController::class, 'destroy']);

        Route::get('menu-items', [MenuItemController::class, 'index']);
        Route::post('menu-items', [MenuItemController::class, 'store']);
        Route::put('menu-items/{menuItem}', [MenuItemController::class, 'update']);
        Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy']);

        Route::get('menu-categories', [MenuCategoryController::class, 'index']);
        Route::post('menu-categories', [MenuCategoryController::class, 'store']);
        Route::delete('menu-categories/{menuCategory}', [MenuCategoryController::class, 'destroy']);

        Route::get('expenses', [ExpenseController::class, 'index']);
        Route::post('expenses', [ExpenseController::class, 'store']);
        Route::put('expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);

        Route::get('constant-lists', [ConstantListController::class, 'index']);
        Route::post('constant-lists', [ConstantListController::class, 'store']);
        Route::delete('constant-lists/{constantList}', [ConstantListController::class, 'destroy']);
        Route::post('constant-lists/{constantList}/items', [ConstantListController::class, 'storeItem']);
        Route::delete('constant-lists/{constantList}/items/{item}', [ConstantListController::class, 'destroyItem']);

        Route::get('team', [TeamMemberController::class, 'index']);
        Route::post('team', [TeamMemberController::class, 'store']);
        Route::put('team/{teamMember}', [TeamMemberController::class, 'update']);
        Route::delete('team/{teamMember}', [TeamMemberController::class, 'destroy']);

        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::put('roles/{role}', [RoleController::class, 'update']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);

        Route::get('devices', [DeviceController::class, 'index']);
        Route::delete('devices/{device}', [DeviceController::class, 'destroy']);

        Route::get('activity-log', [ActivityLogController::class, 'index']);

        Route::get('reports', [ReportController::class, 'index']);

        Route::post('sync/push', [SyncController::class, 'push']);
        Route::get('sync/pull', [SyncController::class, 'pull']);
    });

    // Admin/platform routes - see BACKEND_PROMPT.md sections 3 and 8. These
    // NEVER pass through tenant.header and always stay on the platform's
    // default `mysql` connection (LicenseController::show is the one
    // deliberate, explicit exception - it resolves a tenant connection
    // read-only for a single peek and restores it immediately after, see
    // App\Services\Admin\LicenseService::show()).
    Route::prefix('admin')->group(function () {
        Route::post('auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:6,1');

        Route::middleware(['auth:sanctum', 'admin'])->group(function () {
            Route::post('auth/logout', [AdminAuthController::class, 'logout']);

            Route::get('packages', [PackageController::class, 'index']);
            Route::post('packages', [PackageController::class, 'store']);
            Route::put('packages/{package}', [PackageController::class, 'update']);
            Route::delete('packages/{package}', [PackageController::class, 'destroy']);

            Route::get('licenses', [LicenseController::class, 'index']);
            Route::post('licenses', [LicenseController::class, 'store']);
            Route::get('licenses/{license}', [LicenseController::class, 'show']);
            Route::post('licenses/{license}/suspend', [LicenseController::class, 'suspend']);
            Route::post('licenses/{license}/activate', [LicenseController::class, 'activate']);
            Route::post('licenses/{license}/change-package', [LicenseController::class, 'changePackage']);

            Route::get('boxes', [BoxController::class, 'index']);
            Route::post('boxes', [BoxController::class, 'store']);
            Route::put('boxes/{box}', [BoxController::class, 'update']);
            Route::delete('boxes/{box}', [BoxController::class, 'destroy']);

            Route::get('partners', [PartnerController::class, 'index']);
            Route::post('partners', [PartnerController::class, 'store']);
            Route::put('partners/{partner}', [PartnerController::class, 'update']);
            Route::delete('partners/{partner}', [PartnerController::class, 'destroy']);

            Route::get('withdrawals', [WithdrawalController::class, 'index']);
            Route::post('withdrawals', [WithdrawalController::class, 'store']);

            Route::get('payments', [PaymentController::class, 'index']);
            Route::post('payments', [PaymentController::class, 'store']);

            Route::get('expenses', [AdminExpenseController::class, 'index']);
            Route::post('expenses', [AdminExpenseController::class, 'store']);

            Route::get('settings', [SettingController::class, 'index']);
            Route::put('settings/{key}', [SettingController::class, 'update']);

            Route::get('activity-log', [AdminActivityLogController::class, 'index']);

            Route::get('finance/summary', [FinanceController::class, 'summary']);

            Route::get('backups', [BackupController::class, 'index']);
            Route::post('backups', [BackupController::class, 'store']);
            Route::get('backups/{backup}/download', [BackupController::class, 'download']);
            Route::delete('backups/{backup}', [BackupController::class, 'destroy']);
        });
    });
});
