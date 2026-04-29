<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name'    => config('app.name'),
        'status'  => 'ok',
        'docs'    => url('/docs'),
        'version' => '1.0.0',
    ]);
});
