<?php

use Epesi\Modules\Attachments\Http\Controllers\DownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')
    ->get('attachments/{attachment}/files/{file}', DownloadController::class)
    ->whereNumber(['attachment', 'file'])
    ->name('epesi.attachments.download');
