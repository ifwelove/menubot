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

// 測試定位找店功能
Route::get('/test-location', function () {
    $controller = app(LineBotController::class);
    
    // 測試座標（您提供的位置）
    $testLat = 25.052810405584;
    $testLng = 121.53793227898;
    
    try {
        // 測試時不發送 LINE 訊息，只回傳結果
        $reflection = new ReflectionClass($controller);
        
        // 呼叫 findNearbyShops 但修改為回傳結果而非發送訊息
        $storesPath = base_path('stores');
        $shops = config('menu.shops.drink', []);
        $nearbyShops = [];
        
        $startTime = microtime(true);
        $processedBrands = 0;
        $totalStoresChecked = 0;
        
        // 遍歷所有品牌
        foreach ($shops as $shopCode => $shopName) {
            $jsonFile = "{$storesPath}/{$shopCode}.json";
            
            if (!file_exists($jsonFile)) {
                continue;
            }
            
            $processedBrands++;
            
            try {
                $data = json_decode(file_get_contents($jsonFile), true);
                
                if (!isset($data['stores']) || !is_array($data['stores'])) {
                    continue;
                }
                
                $totalStoresChecked += count($data['stores']);
                
                foreach ($data['stores'] as $store) {
                    if (!isset($store['latitude']) || !isset($store['longitude'])) {
                        continue;
                    }
                    
                    // 使用 calculateDistance 方法
                    $method = $reflection->getMethod('calculateDistance');
                    $method->setAccessible(true);
                    
                    $distance = $method->invoke(
                        $controller,
                        $testLat, $testLng,
                        (float)$store['latitude'],
                        (float)$store['longitude']
                    );
                    
                    if ($distance <= 2.0) {
                        $nearbyShops[] = [
                            'shop_code' => $shopCode,
                            'shop_name' => $shopName,
                            'branch_name' => $store['name'] ?? $store['name_short'] ?? '分店',
                            'address' => $store['address'] ?? '',
                            'distance' => round($distance, 2),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // 記錄錯誤但繼續處理
            }
            
            // 每處理 50 個品牌就檢查一次時間
            if ($processedBrands % 50 == 0) {
                $elapsed = microtime(true) - $startTime;
                if ($elapsed > 25) { // 避免超時
                    break;
                }
            }
        }
        
        // 排序
        usort($nearbyShops, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });
        
        // 取得所有找到的數量
        $totalFound = count($nearbyShops);
        
        // 只取前 10 家顯示
        $displayShops = array_slice($nearbyShops, 0, 10);
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        return response()->json([
            'status' => 'success',
            'test_location' => [
                'lat' => $testLat,
                'lng' => $testLng,
                'address' => '台北市中山區建國北路二段3巷13弄4之3號',
            ],
            'stats' => [
                'total_brands' => count($shops),
                'processed_brands' => $processedBrands,
                'total_stores_checked' => $totalStoresChecked,
                'nearby_shops_found' => $totalFound,
                'displayed_shops' => count($displayShops),
                'execution_time' => $executionTime . ' seconds',
                'memory_peak' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
            ],
            'nearby_shops_sample' => $displayShops,
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
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
