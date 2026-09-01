<?php

declare(strict_types=1);

use App\Http\Controllers\StoredFileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/files');

Route::controller(StoredFileController::class)
    ->prefix('files')
    ->name('files.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:30,1')->name('store');
        Route::get('/{publicId}/download', 'download')->name('download');
        Route::delete('/{publicId}', 'destroy')->middleware('throttle:60,1')->name('destroy');
    });
