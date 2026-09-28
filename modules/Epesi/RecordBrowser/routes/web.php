<?php

use Epesi\Modules\RecordBrowser\Http\Controllers\RecordFileController;
use Illuminate\Support\Facades\Route;

// One file of a record's file field — see RecordFileController for who may
// fetch it. {type} is the record's morph alias.
Route::middleware('web')
    ->get('records/{type}/{id}/files/{field}/{file}', RecordFileController::class)
    ->where(['type' => '[a-z0-9_]+', 'field' => '[a-z0-9_]+'])
    ->whereNumber(['id', 'file'])
    ->name('epesi.records.file');
