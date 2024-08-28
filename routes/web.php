<?php

use App\Http\Controllers\LineBotController;

Route::post('/webhook', [LineBotController::class, 'webhook']);
