<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\ClientInvoiceController;
use App\Http\Controllers\ClientQuoteController;
use App\Http\Controllers\ClientRequestController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MagicLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect(auth()->user()->homeRoute()) : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login/link', [MagicLinkController::class, 'send'])->middleware('throttle:4,1')->name('login.link.send');
    Route::get('/login/link/{token}', [MagicLinkController::class, 'consume'])->middleware('throttle:10,1')->name('login.link');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', [AccountController::class, 'edit'])->name('account');
    Route::put('/account', [AccountController::class, 'update']);
    Route::put('/account/password', [AccountController::class, 'password'])->name('account.password');
    Route::get('/files/{file}', [FileController::class, 'download'])->name('files.download');
});

Route::middleware(['auth', 'role:client'])->group(function () {
    Route::get('/dashboard', ClientDashboardController::class)->name('dashboard');
    Route::get('/requests', [ClientRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/new', [ClientRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [ClientRequestController::class, 'store'])->middleware('throttle:10,60')->name('requests.store');
    Route::get('/requests/{id}', [ClientRequestController::class, 'show'])->whereUuid('id')->name('requests.show');

    Route::get('/quotes', [ClientQuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/{id}', [ClientQuoteController::class, 'show'])->whereUuid('id')->name('quotes.show');
    Route::get('/quotes/{id}/pdf', [ClientQuoteController::class, 'pdf'])->whereUuid('id')->name('quotes.pdf');
    Route::post('/quotes/{id}/accept', [ClientQuoteController::class, 'accept'])->whereUuid('id')->name('quotes.accept');
    Route::post('/quotes/{id}/decline', [ClientQuoteController::class, 'decline'])->whereUuid('id')->name('quotes.decline');
    Route::post('/quotes/{id}/changes', [ClientQuoteController::class, 'changes'])->whereUuid('id')->name('quotes.changes');

    Route::get('/invoices', [ClientInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{id}', [ClientInvoiceController::class, 'show'])->whereUuid('id')->name('invoices.show');
    Route::get('/invoices/{id}/pdf', [ClientInvoiceController::class, 'pdf'])->whereUuid('id')->name('invoices.pdf');
    Route::post('/invoices/{id}/pay', [ClientInvoiceController::class, 'pay'])->whereUuid('id')->middleware('throttle:10,60')->name('invoices.pay');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/requests', [Admin\RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/new', [Admin\RequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [Admin\RequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{serviceRequest}', [Admin\RequestController::class, 'show'])->whereUuid('serviceRequest')->name('requests.show');
    Route::patch('/requests/{serviceRequest}/status', [Admin\RequestController::class, 'status'])->name('requests.status');
    Route::post('/requests/{serviceRequest}/notes', [Admin\RequestController::class, 'note'])->name('requests.notes');

    Route::post('/requests/{serviceRequest}/quotes', [Admin\QuoteController::class, 'storeFromRequest'])->name('requests.quotes.store');

    Route::get('/quotes', [Admin\QuoteController::class, 'index'])->name('quotes.index');
    Route::post('/quotes', [Admin\QuoteController::class, 'store'])->name('quotes.store');
    Route::get('/quotes/{quote}', [Admin\QuoteController::class, 'show'])->name('quotes.show');
    Route::get('/quotes/{quote}/edit', [Admin\QuoteController::class, 'edit'])->name('quotes.edit');
    Route::put('/quotes/{quote}', [Admin\QuoteController::class, 'update'])->name('quotes.update');
    Route::post('/quotes/{quote}/revise', [Admin\QuoteController::class, 'revise'])->name('quotes.revise');
    Route::post('/quotes/{quote}/answer', [Admin\QuoteController::class, 'recordAnswer'])->name('quotes.answer');
    Route::delete('/quotes/{quote}', [Admin\QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::get('/quotes/{quote}/pdf', [Admin\QuoteController::class, 'pdf'])->name('quotes.pdf');

    Route::post('/quotes/{quote}/invoices', [Admin\InvoiceController::class, 'storeFromQuote'])->name('quotes.invoices.store');

    Route::get('/invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('/invoices', [Admin\InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [Admin\InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/invoices/{invoice}', [Admin\InvoiceController::class, 'update'])->name('invoices.update');
    Route::post('/invoices/{invoice}/issue', [Admin\InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('/invoices/{invoice}/cancel', [Admin\InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::delete('/invoices/{invoice}', [Admin\InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('/invoices/{invoice}/pdf', [Admin\InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('/invoices/{invoice}/payments', [Admin\PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::post('/payments/{payment}/confirm', [Admin\PaymentController::class, 'confirm'])->name('payments.confirm');
    Route::post('/payments/{payment}/reject', [Admin\PaymentController::class, 'reject'])->name('payments.reject');

    Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings');
    Route::put('/settings', [Admin\SettingController::class, 'update']);

    Route::get('/clients', [Admin\ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/new', [Admin\ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [Admin\ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [Admin\ClientController::class, 'show'])->whereUuid('client')->name('clients.show');
    Route::put('/clients/{client}', [Admin\ClientController::class, 'update'])->whereUuid('client')->name('clients.update');
    Route::post('/clients/{client}/access', [Admin\ClientController::class, 'grantAccess'])->whereUuid('client')->name('clients.access');

    Route::get('/services', [Admin\CatalogueController::class, 'index'])->name('services.index');
    Route::post('/services/categories', [Admin\CatalogueController::class, 'storeCategory'])->name('categories.store');
    Route::get('/services/categories/{category}', [Admin\CatalogueController::class, 'show'])->name('categories.show');
    Route::put('/services/categories/{category}', [Admin\CatalogueController::class, 'updateCategory'])->name('categories.update');
    Route::post('/services/categories/{category}/services', [Admin\CatalogueController::class, 'storeService'])->name('services.store');
    Route::put('/services/{service}', [Admin\CatalogueController::class, 'updateService'])->name('services.update');
    Route::post('/services/categories/{category}/questions', [Admin\CatalogueController::class, 'storeQuestion'])->name('questions.store');
    Route::put('/services/questions/{question}', [Admin\CatalogueController::class, 'updateQuestion'])->name('questions.update');
    Route::delete('/services/questions/{question}', [Admin\CatalogueController::class, 'destroyQuestion'])->name('questions.destroy');

    Route::get('/packages', [Admin\PackageController::class, 'index'])->name('packages.index');
    Route::post('/packages', [Admin\PackageController::class, 'store'])->name('packages.store');
    Route::put('/packages/{package}', [Admin\PackageController::class, 'update'])->name('packages.update');
});
