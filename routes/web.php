<?php

use App\Http\Controllers\ExamPreviewPdfController;
use App\Http\Controllers\LabBrandMarkController;
use App\Http\Controllers\OrderReportPdfController;
use App\Http\Controllers\PublicOrderReportPdfController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): RedirectResponse => redirect()->route('filament.admin.pages.dashboard'))
    ->name('home');

Route::get('/marca', LabBrandMarkController::class)->name('lab.brand-mark');

if (file_exists(__DIR__.'/plantillas.php')) {
    require __DIR__.'/plantillas.php';
}

Route::get('/ordenes/{order}/reporte', OrderReportPdfController::class)
    ->middleware(['auth', 'can:generateReport,order'])
    ->name('orders.report');

Route::get('/catalogo/examenes/{exam}/vista-previa', ExamPreviewPdfController::class)
    ->middleware(['auth', 'can:view,exam'])
    ->name('exams.preview');

Route::get('/consulta/{token}', PublicOrderReportPdfController::class)
    ->middleware('throttle:30,1')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('reports.public');
