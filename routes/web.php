<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\ClientInvoiceController;
use App\Http\Controllers\ClientProjectController;
use App\Http\Controllers\ClientQuoteController;
use App\Http\Controllers\ClientRequestController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\MagicLinkController;
use App\Models\Setting;
use App\Support\Brand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => auth()->check() ? redirect(auth()->user()->homeRoute()) : redirect()->route('login'));

// The uploaded logo. Public, because it shows on the sign-in page and in emails.
Route::get('/brand/logo', function () {
    abort_unless(Brand::hasLogo(), 404);

    return Storage::disk('local')->response(Setting::get('logo_path'), null, [
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->name('brand.logo');

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
    Route::post('/requests/{id}/files', [ClientRequestController::class, 'addFiles'])->whereUuid('id')->middleware('throttle:20,60')->name('requests.files');

    Route::get('/quotes', [ClientQuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/{id}', [ClientQuoteController::class, 'show'])->whereUuid('id')->name('quotes.show');
    Route::get('/quotes/{id}/pdf', [ClientQuoteController::class, 'pdf'])->whereUuid('id')->name('quotes.pdf');
    Route::post('/quotes/{id}/accept', [ClientQuoteController::class, 'accept'])->whereUuid('id')->name('quotes.accept');
    Route::post('/quotes/{id}/decline', [ClientQuoteController::class, 'decline'])->whereUuid('id')->name('quotes.decline');
    Route::post('/quotes/{id}/renew', [ClientQuoteController::class, 'renew'])->whereUuid('id')->middleware('throttle:3,60')->name('quotes.renew');
    Route::post('/quotes/{id}/changes', [ClientQuoteController::class, 'changes'])->whereUuid('id')->name('quotes.changes');

    Route::get('/invoices', [ClientInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{id}', [ClientInvoiceController::class, 'show'])->whereUuid('id')->name('invoices.show');
    Route::get('/invoices/{id}/pdf', [ClientInvoiceController::class, 'pdf'])->whereUuid('id')->name('invoices.pdf');
    Route::post('/invoices/{id}/pay', [ClientInvoiceController::class, 'pay'])->whereUuid('id')->middleware('throttle:10,60')->name('invoices.pay');

    Route::get('/projects', [ClientProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{id}', [ClientProjectController::class, 'show'])->whereUuid('id')->name('projects.show');
    Route::get('/projects/{id}/messages', [ClientProjectController::class, 'messages'])->whereUuid('id')->middleware('throttle:120,1')->name('projects.messages');
    Route::post('/projects/{id}/messages', [ClientProjectController::class, 'postMessage'])->whereUuid('id')->middleware('throttle:30,1')->name('projects.messages.store');
    Route::post('/projects/{id}/milestones/{milestone}/approve', [ClientProjectController::class, 'approve'])->whereUuid('id')->name('projects.milestones.approve');
    Route::post('/projects/{id}/milestones/{milestone}/changes', [ClientProjectController::class, 'changes'])->whereUuid('id')->name('projects.milestones.changes');
    Route::post('/projects/{id}/checklist/{item}', [ClientProjectController::class, 'submitItem'])->whereUuid('id')->middleware('throttle:30,60')->name('projects.checklist.submit');
    Route::post('/projects/{id}/changes', [ClientProjectController::class, 'requestChange'])->whereUuid('id')->middleware('throttle:10,60')->name('projects.changes.store');
    Route::post('/projects/{id}/handover', [ClientProjectController::class, 'confirmHandover'])->whereUuid('id')->name('projects.handover.confirm');
    Route::post('/projects/{id}/testimonial', [ClientProjectController::class, 'testimonial'])->whereUuid('id')->middleware('throttle:5,60')->name('projects.testimonial');
    Route::post('/projects/{id}/files', [ClientProjectController::class, 'uploadFiles'])->whereUuid('id')->middleware('throttle:20,60')->name('projects.files');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/activity', [Admin\ActivityController::class, 'index'])->name('activity');

    Route::get('/projects', [Admin\ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [Admin\ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [Admin\ProjectController::class, 'show'])->name('projects.show');
    Route::put('/projects/{project}', [Admin\ProjectController::class, 'update'])->name('projects.update');
    Route::get('/projects/{project}/messages', [Admin\ProjectController::class, 'messages'])->name('projects.messages');
    Route::post('/projects/{project}/messages', [Admin\ProjectController::class, 'postMessage'])->name('projects.messages.store');
    Route::post('/projects/{project}/updates', [Admin\ProjectController::class, 'postUpdate'])->name('projects.updates.store');
    Route::post('/projects/{project}/milestones', [Admin\ProjectController::class, 'addMilestone'])->name('projects.milestones.store');
    Route::put('/projects/{project}/milestones/{milestone}', [Admin\ProjectController::class, 'updateMilestone'])->name('projects.milestones.update');
    Route::delete('/projects/{project}/milestones/{milestone}', [Admin\ProjectController::class, 'destroyMilestone'])->name('projects.milestones.destroy');
    Route::post('/projects/{project}/checklist', [Admin\ProjectController::class, 'addChecklist'])->name('projects.checklist.store');
    Route::patch('/projects/{project}/checklist/{item}', [Admin\ProjectController::class, 'checklistStatus'])->name('projects.checklist.status');
    Route::delete('/projects/{project}/checklist/{item}', [Admin\ProjectController::class, 'destroyChecklist'])->name('projects.checklist.destroy');
    Route::post('/projects/{project}/files', [Admin\ProjectController::class, 'uploadFiles'])->name('projects.files');
    Route::post('/projects/{project}/changes/{change}', [Admin\ProjectController::class, 'answerChange'])->name('projects.changes.answer');
    Route::post('/projects/{project}/handover', [Admin\ProjectController::class, 'handover'])->name('projects.handover');
    Route::post('/projects/{project}/testimonial', [Admin\ProjectController::class, 'requestTestimonial'])->name('projects.testimonial');

    Route::get('/care-plans', [Admin\CarePlanController::class, 'index'])->name('care-plans.index');
    Route::post('/care-plans', [Admin\CarePlanController::class, 'store'])->name('care-plans.store');
    Route::put('/care-plans/{carePlan}', [Admin\CarePlanController::class, 'update'])->name('care-plans.update');
    Route::post('/care-plans/{carePlan}/bill', [Admin\CarePlanController::class, 'bill'])->name('care-plans.bill');

    Route::get('/testimonials', [Admin\TestimonialController::class, 'index'])->name('testimonials.index');
    Route::post('/testimonials/{testimonial}/feature', [Admin\TestimonialController::class, 'feature'])->name('testimonials.feature');

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
    Route::post('/quotes/{quote}/extend', [Admin\QuoteController::class, 'extend'])->name('quotes.extend');
    Route::delete('/quotes/{quote}', [Admin\QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::get('/quotes/{quote}/pdf', [Admin\QuoteController::class, 'pdf'])->name('quotes.pdf');

    Route::post('/quotes/{quote}/invoices', [Admin\InvoiceController::class, 'storeFromQuote'])->name('quotes.invoices.store');

    Route::get('/invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('/invoices', [Admin\InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [Admin\InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/invoices/{invoice}', [Admin\InvoiceController::class, 'update'])->name('invoices.update');
    Route::post('/invoices/{invoice}/remind', [Admin\InvoiceController::class, 'remind'])->name('invoices.remind');
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
    Route::post('/clients/{client}/archive', [Admin\ClientController::class, 'archive'])->name('clients.archive');
    Route::post('/clients/{client}/restore', [Admin\ClientController::class, 'restore'])->name('clients.restore');
    Route::get('/clients/{client}/export', [Admin\ClientController::class, 'export'])->middleware('throttle:10,1')->name('clients.export');

    Route::get('/services', [Admin\CatalogueController::class, 'index'])->name('services.index');
    Route::get('/services/export', [Admin\ServiceImportController::class, 'export'])->name('services.export');
    Route::get('/services/import', [Admin\ServiceImportController::class, 'create'])->name('services.import');
    Route::post('/services/import', [Admin\ServiceImportController::class, 'preview'])->name('services.import.preview');
    Route::post('/services/import/apply', [Admin\ServiceImportController::class, 'apply'])->name('services.import.apply');
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
