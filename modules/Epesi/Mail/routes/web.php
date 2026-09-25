<?php

use Epesi\Modules\Mail\Http\Controllers\AttachmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')
    ->get('mail/{mail}/attachments/{attachment}', AttachmentController::class)
    ->whereNumber(['mail', 'attachment'])
    ->name('epesi.mail.attachment');
