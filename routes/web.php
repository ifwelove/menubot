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
// 測試 Flex Message 大小
Route::get('/test-flex-size', function () {
    // 模擬 5 家店的資料
    $shops = [
        ['shop_code' => 'shop1', 'shop_name' => '測試店1', 'branch_name' => '分店1', 'address' => '測試地址1', 'tel' => '02-12345678', 'distance' => 0.1],
        ['shop_code' => 'shop2', 'shop_name' => '測試店2', 'branch_name' => '分店2', 'address' => '測試地址2', 'tel' => '02-12345678', 'distance' => 0.2],
        ['shop_code' => 'shop3', 'shop_name' => '測試店3', 'branch_name' => '分店3', 'address' => '測試地址3', 'tel' => '02-12345678', 'distance' => 0.3],
        ['shop_code' => 'shop4', 'shop_name' => '測試店4', 'branch_name' => '分店4', 'address' => '測試地址4', 'tel' => '02-12345678', 'distance' => 0.4],
        ['shop_code' => 'shop5', 'shop_name' => '測試店5', 'branch_name' => '分店5', 'address' => '測試地址5', 'tel' => '02-12345678', 'distance' => 0.5],
    ];
    
    // 建立 Flex Message（與 displayNearbyShops 相同邏輯）
    $shopComponents = [];
    
    // 標題
    $shopComponents[] = LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder::builder()
        ->setText('📍 附近的飲料店')
        ->setWeight(LINE\LINEBot\Constant\Flex\ComponentFontWeight::BOLD)
        ->setSize(LINE\LINEBot\Constant\Flex\ComponentFontSize::LG)
        ->setMargin(LINE\LINEBot\Constant\Flex\ComponentMargin::MD);
    
    // 計算組件數量
    $componentCount = 1; // 標題
    
    foreach ($shops as $index => $shop) {
        // 每家店的組件數：店名(1) + 地址(1) + 電話(1) + 按鈕(1) + 分隔線(1) = 5
        $componentCount += 5;
    }
    
    // 建立完整的 Flex Message
    $flexMessageBuilder = LINE\LINEBot\MessageBuilder\FlexMessageBuilder::builder()
        ->setAltText('附近的飲料店')
        ->setContents(LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder::builder()
            ->setBody(LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder::builder()
                ->setLayout(LINE\LINEBot\Constant\Flex\ComponentLayout::VERTICAL)
                ->setContents($shopComponents)));
    
    // 轉換為陣列以計算大小
    $messageArray = $flexMessageBuilder->buildMessage();
    $jsonSize = strlen(json_encode($messageArray));
    
    return response()->json([
        'shops_count' => count($shops),
        'component_count' => $componentCount,
        'estimated_component_count_for_109_shops' => 1 + (109 * 5), // 546 個組件
        'json_size' => $jsonSize . ' bytes',
        'json_size_kb' => round($jsonSize / 1024, 2) . ' KB',
        'estimated_size_for_109_shops' => round(($jsonSize / 5) * 109 / 1024, 2) . ' KB',
        'line_limit' => '300 KB',
        'message_preview' => $messageArray,
    ]);
});

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

// 測試 Carousel 附近店家
Route::get('/test-carousel-shops', function () {
    // 模擬 10 家店的資料（使用字串格式的距離）
    $shops = [
        [
            'shop_code' => '50lantea',
            'shop_name' => '50嵐',
            'branch_name' => '伊通店',
            'address' => '台北市中山區伊通街66-2號',
            'tel' => '02-2502-6131',
            'distance' => '0.45'
        ],
        [
            'shop_code' => 'milkshoptea',
            'shop_name' => '迷客夏',
            'branch_name' => '臺北伊通店',
            'address' => '台北市中山區伊通街68-3號',
            'tel' => '02-25185358',
            'distance' => '0.45'
        ],
        [
            'shop_code' => '50lantea',
            'shop_name' => '50嵐',
            'branch_name' => '遼寧店',
            'address' => '台北市中山區遼寧街28號1樓',
            'tel' => '02-2773-7808',
            'distance' => '0.68'
        ],
        [
            'shop_code' => 'milkshoptea',
            'shop_name' => '迷客夏',
            'branch_name' => '臺北長春店',
            'address' => '台北市中山區長春路66號',
            'tel' => '02-25613117',
            'distance' => '0.92'
        ],
        [
            'shop_code' => '50lantea',
            'shop_name' => '50嵐',
            'branch_name' => '林森北店',
            'address' => '台北市中山區林森北路487號',
            'tel' => '02-2537-5088',
            'distance' => '1.20'
        ],
        [
            'shop_code' => 'comebuy2001',
            'shop_name' => 'ComeBy啡',
            'branch_name' => '中山北店',
            'address' => '台北市中山區中山北路二段45號',
            'tel' => '02-2511-9988',
            'distance' => '1.35'
        ],
        [
            'shop_code' => 'coco',
            'shop_name' => 'CoCo都可',
            'branch_name' => '南京松江店',
            'address' => '台北市中山區南京東路二段115號',
            'tel' => '02-2511-7788',
            'distance' => '1.50'
        ],
        [
            'shop_code' => 'kebuke',
            'shop_name' => '可不可熟成紅茶',
            'branch_name' => '建國北店',
            'address' => '台北市中山區建國北路二段120號',
            'tel' => '02-2502-5566',
            'distance' => '1.65'
        ],
        [
            'shop_code' => 'tigersugar',
            'shop_name' => '老虎堂',
            'branch_name' => '中山店',
            'address' => '台北市中山區中山北路二段16號',
            'tel' => '02-2563-1122',
            'distance' => '1.80'
        ],
        [
            'shop_code' => 'tenren',
            'shop_name' => '天仁茗茶',
            'branch_name' => '南京東店',
            'address' => '台北市中山區南京東路二段95號',
            'tel' => '02-2511-3366',
            'distance' => '1.95'
        ]
    ];
    
    try {
        // 建立 Carousel 卡片
        $columns = [];
        
        foreach ($shops as $index => $shop) {
            // 準備距離字串
            $distanceStr = $shop['distance'];
            
            // 標題（最多40字元）
            $title = mb_substr($shop['shop_name'] . ' ' . $shop['branch_name'], 0, 40);
            
            // 描述文字（最多60字元）
            $text = "📍 {$distanceStr} km\n";
            $text .= mb_substr($shop['address'], 0, 50);
            
            // 建立動作按鈕
            $actions = [];
            
            // 查看菜單按鈕
            $postbackData = http_build_query(['action' => 'select', 'shop' => $shop['shop_code']]);
            $actions[] = [
                'type' => 'postback',
                'label' => '查看菜單',
                'data' => $postbackData
            ];
            
            // 撥打電話按鈕
            if (!empty($shop['tel'])) {
                $actions[] = [
                    'type' => 'uri',
                    'label' => '撥打電話',
                    'uri' => 'tel:' . $shop['tel']
                ];
            }
            
            // 取得店家圖片
            $imageUrl = null;
            if (!empty($shop['shop_code'])) {
                $shopConfig = config("menus.{$shop['shop_code']}");
                if ($shopConfig && isset($shopConfig['image_url'])) {
                    $imageUrl = $shopConfig['image_url'];
                }
            }
            
            // 如果沒有圖片，使用預設圖片
            if (!$imageUrl) {
                $imageUrl = 'https://via.placeholder.com/300x200?text=' . urlencode($shop['shop_name']);
            }
            
            // 建立 Carousel Column 結構
            $column = [
                'thumbnailImageUrl' => $imageUrl,
                'imageBackgroundColor' => '#FFFFFF',
                'title' => $title,
                'text' => $text,
                'defaultAction' => [
                    'type' => 'postback',
                    'label' => '查看詳情',
                    'data' => $postbackData
                ],
                'actions' => array_slice($actions, 0, 3) // 最多3個按鈕
            ];
            
            $columns[] = $column;
        }
        
        // 建立完整的 Carousel 訊息結構
        $carouselMessage = [
            'type' => 'template',
            'altText' => '📍 附近的飲料店',
            'template' => [
                'type' => 'carousel',
                'columns' => $columns,
                'imageAspectRatio' => 'rectangle',
                'imageSize' => 'cover'
            ]
        ];
        
        return response()->json([
            'status' => 'success',
            'shops_count' => count($shops),
            'columns_count' => count($columns),
            'message_structure' => $carouselMessage,
            'json_size' => strlen(json_encode($carouselMessage)) . ' bytes',
            'json_size_kb' => round(strlen(json_encode($carouselMessage)) / 1024, 2) . ' KB',
            'note' => 'Carousel 卡片選單格式，可左右滑動'
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

// 測試 displayNearbyShops 的 Flex Message 建構
Route::get('/test-display-shops', function () {
    // 模擬 5 家店的資料（使用字串格式的距離）
    $shops = [
        [
            'shop_code' => '50lantea',
            'shop_name' => '50嵐',
            'branch_name' => '伊通店',
            'address' => '台北市中山區伊通街66-2號',
            'tel' => '02-2502-6131',
            'distance' => '0.45'  // 使用字串
        ],
        [
            'shop_code' => 'milkshoptea',
            'shop_name' => '迷客夏',
            'branch_name' => '臺北伊通店',
            'address' => '台北市中山區伊通街68-3號',
            'tel' => '02-25185358',
            'distance' => '0.45'  // 使用字串
        ],
        [
            'shop_code' => '50lantea',
            'shop_name' => '50嵐',
            'branch_name' => '遼寧店',
            'address' => '台北市中山區遼寧街28號1樓',
            'tel' => '02-2773-7808',
            'distance' => '0.68'  // 使用字串
        ],
        [
            'shop_code' => 'milkshoptea',
            'shop_name' => '迷客夏',
            'branch_name' => '臺北長春店',
            'address' => '台北市中山區長春路66號',
            'tel' => '02-25613117',
            'distance' => '0.92'  // 使用字串
        ],
        [
            'shop_code' => '50lantea',
            'shop_name' => '50嵐',
            'branch_name' => '林森北店',
            'address' => '台北市中山區林森北路487號',
            'tel' => '02-2537-5088',
            'distance' => '1.20'  // 使用字串
        ]
    ];
    
    try {
        // 建立 Flex Message 組件
        $shopComponents = [];
        
        // 標題
        $shopComponents[] = LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder::builder()
            ->setText('📍 附近的飲料店')
            ->setWeight(LINE\LINEBot\Constant\Flex\ComponentFontWeight::BOLD)
            ->setSize(LINE\LINEBot\Constant\Flex\ComponentFontSize::LG)
            ->setMargin(LINE\LINEBot\Constant\Flex\ComponentMargin::MD);
        
        // 分隔線
        $shopComponents[] = LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\SeparatorComponentBuilder::builder()
            ->setMargin(LINE\LINEBot\Constant\Flex\ComponentMargin::MD);
        
        // 店家資訊
        foreach ($shops as $index => $shop) {
            // 店名
            $shopComponents[] = LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder::builder()
                ->setText($shop['shop_name'] . ' - ' . $shop['branch_name'])
                ->setWeight(LINE\LINEBot\Constant\Flex\ComponentFontWeight::BOLD)
                ->setSize(LINE\LINEBot\Constant\Flex\ComponentFontSize::SM)
                ->setMargin(LINE\LINEBot\Constant\Flex\ComponentMargin::MD);
            
            // 距離和地址
            $shopComponents[] = LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder::builder()
                ->setText('📍 ' . $shop['distance'] . ' km | ' . $shop['address'])
                ->setSize(LINE\LINEBot\Constant\Flex\ComponentFontSize::XXS)
                ->setColor('#666666')
                ->setWrap(true)
                ->setMargin(LINE\LINEBot\Constant\Flex\ComponentMargin::SM);
            
            // 電話
            if (!empty($shop['tel'])) {
                $shopComponents[] = LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder::builder()
                    ->setText('📞 ' . $shop['tel'])
                    ->setSize(LINE\LINEBot\Constant\Flex\ComponentFontSize::XXS)
                    ->setColor('#666666')
                    ->setMargin(LINE\LINEBot\Constant\Flex\ComponentMargin::SM);
            }
            
            // 檢視菜單按鈕
            $shopComponents[] = LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder::builder()
                ->setStyle(LINE\LINEBot\Constant\Flex\ComponentButtonStyle::LINK)
                ->setHeight(LINE\LINEBot\Constant\Flex\ComponentButtonHeight::SM)
                ->setAction(new LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder(
                    '查看菜單',
                    '查看' . $shop['shop_name'] . '菜單'
                ))
                ->setColor('#1976D2')
                ->setMargin(LINE\LINEBot\Constant\Flex\ComponentMargin::SM);
            
            // 加入分隔線（最後一個不加）
            if ($index < count($shops) - 1) {
                $shopComponents[] = LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\SeparatorComponentBuilder::builder()
                    ->setMargin(LINE\LINEBot\Constant\Flex\ComponentMargin::MD);
            }
        }
        
        // 建立 Flex Message
        $flexMessageBuilder = LINE\LINEBot\MessageBuilder\FlexMessageBuilder::builder()
            ->setAltText('附近的飲料店')
            ->setContents(LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder::builder()
                ->setBody(LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder::builder()
                    ->setLayout(LINE\LINEBot\Constant\Flex\ComponentLayout::VERTICAL)
                    ->setContents($shopComponents)));
        
        // 轉換為陣列以檢查結構
        $messageArray = $flexMessageBuilder->buildMessage();
        
        return response()->json([
            'status' => 'success',
            'shops_count' => count($shops),
            'components_count' => count($shopComponents),
            'message_structure' => $messageArray,
            'json_size' => strlen(json_encode($messageArray)) . ' bytes',
            'note' => '距離值已改為字串格式'
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
