<?php

use App\Http\Controllers\LineBotController;

Route::post('/webhook', [LineBotController::class, 'webhook']);

use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'message' => 'This is a test route',
        'status' => 'success'
    ]);
});

// 測試使用說明功能
Route::get('/test-instructions', function () {
    $controller = app(LineBotController::class);
    
    // 模擬一個測試用的 replyToken
    $testReplyToken = 'test_' . time() . '_token';
    
    try {
        // 直接呼叫 showInstructions 方法
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('showInstructions');
        $method->setAccessible(true);
        
        // 執行方法
        $method->invoke($controller, $testReplyToken);
        
        return response()->json([
            'status' => 'success',
            'message' => '已嘗試發送使用說明訊息',
            'replyToken' => $testReplyToken,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
