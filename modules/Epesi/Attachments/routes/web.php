<?php

use Epesi\Modules\Attachments\Http\Controllers\DownloadController;
use Epesi\Modules\Attachments\Http\Controllers\HtmlController;
use Epesi\Modules\Attachments\Http\Controllers\MarkdownController;
use Epesi\Modules\Attachments\Http\Controllers\SharedFileController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')
    ->get('attachments/{attachment}/files/{file}', DownloadController::class)
    ->whereNumber(['attachment', 'file'])
    ->name('epesi.attachments.download');

Route::middleware('web')
    ->get('attachments/{attachment}/files/{file}/markdown', MarkdownController::class)
    ->whereNumber(['attachment', 'file'])
    ->name('epesi.attachments.markdown');

Route::middleware('web')
    ->get('attachments/{attachment}/files/{file}/html', HtmlController::class)
    ->whereNumber(['attachment', 'file'])
    ->name('epesi.attachments.html');

// The "Get link" action's target: a signed URL good for anyone who has it,
// login or not, for as long as the signature is valid — no ownership or
// permission check, by design (see SharedFileController).
Route::middleware(['web', 'signed'])
    ->get('attachments/{attachment}/files/{file}/shared', SharedFileController::class)
    ->whereNumber(['attachment', 'file'])
    ->name('epesi.attachments.shared');
