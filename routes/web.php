<?php

use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\AllergenController;
use App\Http\Controllers\Web\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Web\Admin\BackupController as AdminBackupController;
use App\Http\Controllers\Web\Admin\BoxController as AdminBoxController;
use App\Http\Controllers\Web\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Web\Admin\LicenseController as AdminLicenseController;
use App\Http\Controllers\Web\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Web\Admin\PartnerController as AdminPartnerController;
use App\Http\Controllers\Web\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Web\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Web\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Web\Admin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Web\Auth\AdminAuthController;
use App\Http\Controllers\Web\Auth\TenantAuthController;
use App\Http\Controllers\Web\BalanceController;
use App\Http\Controllers\Web\BatchController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\ConstantController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\CustomerController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\ExpenseController;
use App\Http\Controllers\Web\HistoryController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\MenuController;
use App\Http\Controllers\Web\ModifierController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\OrderConfirmationController;
use App\Http\Controllers\Web\ProductionController;
use App\Http\Controllers\Web\PurchaseController;
use App\Http\Controllers\Web\ReceiptController;
use App\Http\Controllers\Web\RecipeController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\RobotsController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\ServiceWorkerController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\StockCountController;
use App\Http\Controllers\Web\SupplierController;
use App\Http\Controllers\Web\SyncController as WebSyncController;
use App\Http\Controllers\Web\TeamController;
use App\Http\Controllers\Web\TransferController;
use App\Http\Controllers\Web\VariantController;
use App\Http\Controllers\Web\WhatsAppSettingsController;
use App\Http\Controllers\Web\WhatsAppWebhookController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('locale/{locale}', function (string $locale) {
    if (in_array($locale, SetLocale::SUPPORTED, true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

Route::view('privacy', 'privacy')->name('privacy');
Route::view('terms', 'terms')->name('terms');
Route::view('security', 'security')->name('security');

Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:6,60')
    ->name('contact.store');

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('robots.txt', RobotsController::class)->name('robots');

// Generated rather than served from public/, so its cache name carries the
// build id - see ServiceWorkerController.
Route::get('sw.js', ServiceWorkerController::class)->name('sw');

Route::view('help', 'help.index')->middleware('tenant.session')->name('help.index');

Route::middleware(['tenant.session', 'guest:tenant'])->group(function () {
    Route::get('login', [TenantAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [TenantAuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('signup', [TenantAuthController::class, 'showSignup'])->name('signup');
    Route::post('signup', [TenantAuthController::class, 'signup']);
});

Route::get('license-inactive', function () {
    return view('auth.license-inactive');
})->name('license.inactive');

Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('webhooks.whatsapp.receive');

Route::get('order/confirm/{license}/{token}', [OrderConfirmationController::class, 'show'])->name('order.confirm');
Route::post('order/confirm/{license}/{token}', [OrderConfirmationController::class, 'act'])
    ->middleware('throttle:10,1')
    ->name('order.confirm.act');

Route::middleware(['tenant.session', 'auth:tenant'])->group(function () {
    Route::post('logout', [TenantAuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');

    Route::get('transfers/{transfer}/created', [TransferController::class, 'created'])->name('transfers.created')->whereUlid('transfer');

    Route::post('sync/push', [WebSyncController::class, 'push'])->name('sync.push');

    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('transfers/{transfer}/receipt', [ReceiptController::class, 'show'])->name('transfers.receipt');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    Route::get('search', [SearchController::class, 'index'])->name('search.index');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    $catalogKeys = 'currencies|banks|wallets|account-types|wallet-types|cash-boxes';
    Route::get('catalogs', [CatalogController::class, 'index'])->name('catalogs.index');
    Route::post('catalogs/{catalog}', [CatalogController::class, 'store'])->name('catalogs.store')->where('catalog', $catalogKeys);
    Route::put('catalogs/{catalog}/{id}', [CatalogController::class, 'update'])->name('catalogs.update')->where('catalog', $catalogKeys);
    Route::delete('catalogs/{catalog}/{id}', [CatalogController::class, 'destroy'])->name('catalogs.destroy')->where('catalog', $catalogKeys);

    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}', [AccountController::class, 'show'])->name('accounts.show')->whereUlid('account');
    Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit')->whereUlid('account');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update')->whereUlid('account');
    Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy')->whereUlid('account');
    Route::post('accounts/{account}/entries', [AccountController::class, 'storeEntry'])->name('accounts.entries.store')->whereUlid('account');

    Route::middleware('package:menu')->group(function () {
        Route::get('menu', [MenuController::class, 'index'])->name('menu.index');
        Route::get('menu/export', [MenuController::class, 'export'])->name('menu.export');
        Route::post('menu/import', [MenuController::class, 'import'])->name('menu.import');
        Route::post('menu/categories', [MenuController::class, 'storeCategory'])->name('menu.categories.store');
        Route::delete('menu/categories/{category}', [MenuController::class, 'destroyCategory'])->name('menu.categories.destroy');
        Route::post('menu/items', [MenuController::class, 'storeItem'])->name('menu.items.store');
        Route::put('menu/items/{item}', [MenuController::class, 'updateItem'])->name('menu.items.update');
        Route::delete('menu/items/{item}', [MenuController::class, 'destroyItem'])->name('menu.items.destroy');
    });

    /*
     * Inventory and purchasing exist only for verticals that stock goods. The
     * capability gate is separate from the package gate on purpose: a pharmacy
     * without batch tracking is not a cheaper plan, it is the wrong system.
     */
    Route::middleware('capability:inventory')->group(function () {
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/{item}', [InventoryController::class, 'show'])->name('inventory.show')->whereUlid('item');
        Route::post('inventory/movements', [InventoryController::class, 'store'])->name('inventory.movements.store');

        Route::middleware('capability:batches')->group(function () {
            Route::get('batches', [BatchController::class, 'index'])->name('batches.index');
            Route::get('batches/{batch}', [BatchController::class, 'show'])->name('batches.show')->whereUlid('batch');
            Route::post('batches/{batch}/write-off', [BatchController::class, 'writeOff'])->name('batches.write-off')->whereUlid('batch');
        });

        Route::get('stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index');
        Route::post('stock-counts', [StockCountController::class, 'store'])->name('stock-counts.store');
        Route::get('stock-counts/{stockCount}', [StockCountController::class, 'show'])->name('stock-counts.show')->whereUlid('stockCount');
        Route::put('stock-counts/{stockCount}', [StockCountController::class, 'update'])->name('stock-counts.update')->whereUlid('stockCount');
        Route::post('stock-counts/{stockCount}/post', [StockCountController::class, 'post'])->name('stock-counts.post')->whereUlid('stockCount');
        Route::post('stock-counts/{stockCount}/cancel', [StockCountController::class, 'cancel'])->name('stock-counts.cancel')->whereUlid('stockCount');
    });

    /*
     * Composition. Recipes serve both food verticals; production runs only make
     * sense where the finished item is stocked, so the screen is gated on
     * inventory as well.
     */
    Route::middleware('capability:recipes')->group(function () {
        Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
        Route::get('recipes/{item}', [RecipeController::class, 'edit'])->name('recipes.edit')->whereUlid('item');
        Route::put('recipes/{item}', [RecipeController::class, 'update'])->name('recipes.update')->whereUlid('item');

        Route::middleware('capability:inventory')->group(function () {
            Route::get('production', [ProductionController::class, 'index'])->name('production.index');
            Route::post('production', [ProductionController::class, 'store'])->name('production.store');
            Route::get('production/{production}', [ProductionController::class, 'show'])->name('production.show')->whereUlid('production');
            Route::post('production/{production}/post', [ProductionController::class, 'post'])->name('production.post')->whereUlid('production');
            Route::post('production/{production}/cancel', [ProductionController::class, 'cancel'])->name('production.cancel')->whereUlid('production');
        });
    });

    Route::middleware('capability:variants')->group(function () {
        Route::get('variants', [VariantController::class, 'index'])->name('variants.index');
        Route::get('variants/{item}', [VariantController::class, 'edit'])->name('variants.edit')->whereUlid('item');
        Route::post('variants/{item}/generate', [VariantController::class, 'generate'])->name('variants.generate')->whereUlid('item');
        Route::put('variants/{item}', [VariantController::class, 'update'])->name('variants.update')->whereUlid('item');
        Route::delete('variants/{item}/{variant}', [VariantController::class, 'destroy'])->name('variants.destroy')->whereUlid('item')->whereUlid('variant');
    });

    Route::middleware('capability:allergens')->group(function () {
        Route::get('allergens', [AllergenController::class, 'index'])->name('allergens.index');
        Route::get('allergens/{item}', [AllergenController::class, 'edit'])->name('allergens.edit')->whereUlid('item');
        Route::put('allergens/{item}', [AllergenController::class, 'update'])->name('allergens.update')->whereUlid('item');
    });

    Route::middleware('capability:modifiers')->group(function () {
        Route::get('modifiers', [ModifierController::class, 'index'])->name('modifiers.index');
        Route::post('modifiers/groups', [ModifierController::class, 'storeGroup'])->name('modifiers.groups.store');
        Route::put('modifiers/groups/{group}', [ModifierController::class, 'updateGroup'])->name('modifiers.groups.update')->whereUlid('group');
        Route::delete('modifiers/groups/{group}', [ModifierController::class, 'destroyGroup'])->name('modifiers.groups.destroy')->whereUlid('group');
        Route::post('modifiers/groups/{group}/options', [ModifierController::class, 'storeModifier'])->name('modifiers.options.store')->whereUlid('group');
        Route::put('modifiers/options/{modifier}', [ModifierController::class, 'updateModifier'])->name('modifiers.options.update')->whereUlid('modifier');
        Route::delete('modifiers/options/{modifier}', [ModifierController::class, 'destroyModifier'])->name('modifiers.options.destroy')->whereUlid('modifier');
        Route::put('modifiers/items/{item}', [ModifierController::class, 'syncItem'])->name('modifiers.items.sync')->whereUlid('item');
    });

    Route::middleware('capability:purchasing')->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->whereUlid('supplier');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->whereUlid('supplier');

        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show')->whereUlid('purchase');
        Route::put('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update')->whereUlid('purchase');
        Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive')->whereUlid('purchase');
        Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel')->whereUlid('purchase');
        Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy')->whereUlid('purchase');
    });

    Route::middleware('package:expenses')->group(function () {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    Route::get('balance', [BalanceController::class, 'index'])->name('balance.index');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('constants', [ConstantController::class, 'index'])->name('constants.index');
    Route::put('constants/naming', [ConstantController::class, 'updateNaming'])
        ->middleware('package:menu')->name('constants.naming.update');

    Route::middleware('package:constants')->group(function () {
        Route::post('constants', [ConstantController::class, 'store'])->name('constants.store');
        Route::delete('constants/{list}', [ConstantController::class, 'destroy'])->name('constants.destroy');
        Route::post('constants/{list}/items', [ConstantController::class, 'storeItem'])->name('constants.items.store');
        Route::delete('constants/{list}/items/{item}', [ConstantController::class, 'destroyItem'])->name('constants.items.destroy');
    });

    Route::middleware('package:team')->group(function () {
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::put('team/{teamMember}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('team/{teamMember}', [TeamController::class, 'destroy'])->name('team.destroy');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::put('team/{teamMember}/salary', [TeamController::class, 'updateSalary'])->name('team.salary.update');
        Route::post('team/{teamMember}/salary/pay', [TeamController::class, 'paySalary'])->name('team.salary.pay');
    });

    Route::middleware('package:devices')->group(function () {
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });

    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/profile', [SettingsController::class, 'update'])->name('settings.profile.update');

    Route::post('settings/logo', [SettingsController::class, 'updateLogo'])->name('settings.logo.update');
    Route::delete('settings/logo', [SettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');

    Route::get('settings/whatsapp', [WhatsAppSettingsController::class, 'index'])->name('whatsapp.settings');
    Route::put('settings/whatsapp', [WhatsAppSettingsController::class, 'update'])->name('whatsapp.settings.update');
    Route::post('settings/whatsapp/test', [WhatsAppSettingsController::class, 'test'])->name('whatsapp.settings.test');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:6,1');
    });

    Route::post('logout', [AdminAuthController::class, 'logout'])
        ->middleware('auth:admin')
        ->name('logout');

    Route::middleware('auth:admin')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('licenses', [AdminLicenseController::class, 'index'])->name('licenses.index');
        Route::post('licenses', [AdminLicenseController::class, 'store'])->name('licenses.store');
        Route::get('licenses/{license}', [AdminLicenseController::class, 'show'])->name('licenses.show');
        Route::post('licenses/{license}/suspend', [AdminLicenseController::class, 'suspend'])->name('licenses.suspend');
        Route::post('licenses/{license}/activate', [AdminLicenseController::class, 'activate'])->name('licenses.activate');

        Route::post('licenses/{license}/migrate', [AdminLicenseController::class, 'migrate'])->name('licenses.migrate');
        Route::post('licenses/{license}/change-package', [AdminLicenseController::class, 'changePackage'])->name('licenses.change-package');
        Route::post('licenses/{license}/change-business-type', [AdminLicenseController::class, 'changeBusinessType'])->name('licenses.change-business-type');

        Route::get('packages', [AdminPackageController::class, 'index'])->name('packages.index');
        Route::post('packages', [AdminPackageController::class, 'store'])->name('packages.store');
        Route::put('packages/{package}', [AdminPackageController::class, 'update'])->name('packages.update');
        Route::delete('packages/{package}', [AdminPackageController::class, 'destroy'])->name('packages.destroy');

        Route::get('boxes', [AdminBoxController::class, 'index'])->name('boxes.index');
        Route::post('boxes', [AdminBoxController::class, 'store'])->name('boxes.store');
        Route::put('boxes/{box}', [AdminBoxController::class, 'update'])->name('boxes.update');
        Route::delete('boxes/{box}', [AdminBoxController::class, 'destroy'])->name('boxes.destroy');

        Route::get('partners', [AdminPartnerController::class, 'index'])->name('partners.index');
        Route::post('partners', [AdminPartnerController::class, 'store'])->name('partners.store');
        Route::put('partners/{partner}', [AdminPartnerController::class, 'update'])->name('partners.update');
        Route::delete('partners/{partner}', [AdminPartnerController::class, 'destroy'])->name('partners.destroy');

        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::post('payments', [AdminPaymentController::class, 'store'])->name('payments.store');

        Route::get('finance', [AdminFinanceController::class, 'index'])->name('finance');
        Route::post('finance/withdrawals', [AdminFinanceController::class, 'storeWithdrawal'])->name('finance.withdrawals.store');
        Route::post('finance/expenses', [AdminFinanceController::class, 'storeExpense'])->name('finance.expenses.store');

        Route::get('settlements', [AdminSettlementController::class, 'index'])->name('settlements.index');
        Route::post('settlements', [AdminSettlementController::class, 'store'])->name('settlements.store');
        Route::get('settlements/{settlement}', [AdminSettlementController::class, 'show'])->name('settlements.show');
        Route::post('settlements/{settlement}/confirm', [AdminSettlementController::class, 'confirm'])->name('settlements.confirm');
        Route::delete('settlements/{settlement}', [AdminSettlementController::class, 'destroy'])->name('settlements.destroy');

        Route::get('backups', [AdminBackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [AdminBackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{backup}/download', [AdminBackupController::class, 'download'])->name('backups.download');
        Route::delete('backups/{backup}', [AdminBackupController::class, 'destroy'])->name('backups.destroy');

        Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
        Route::post('contact-messages/{message}/read', [AdminContactMessageController::class, 'markRead'])->name('contact-messages.read');
        Route::delete('contact-messages/{message}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');

        Route::get('logs', [AdminActivityLogController::class, 'index'])->name('logs.index');

        Route::get('settings', [AdminSettingController::class, 'index'])->name('settings.index');

        Route::put('settings', [AdminSettingController::class, 'update'])->name('settings.update');
        Route::post('settings', [AdminSettingController::class, 'upsert'])->name('settings.upsert');
        Route::delete('settings/{key}', [AdminSettingController::class, 'destroy'])->name('settings.destroy');

        Route::get('profile', [AdminProfileController::class, 'index'])->name('profile.index');
        Route::put('profile', [AdminProfileController::class, 'update'])->name('profile.update');
    });
});
