<?php

use Illuminate\Support\Facades\Route;
use Unipay\BD\Http\Controllers\CallbackController;

$prefix = config('unipay.routes.prefix', 'unipay');
$middleware = config('unipay.routes.middleware', ['web']);

Route::group([
    'prefix' => $prefix,
    'middleware' => $middleware,
], function () {
    Route::any('/callback/{gateway}', [CallbackController::class, 'handle'])
        ->name('unipay.callback');
});
