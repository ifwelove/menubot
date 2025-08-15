<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LINE\LINEBot;
use LINE\LINEBot\Constant\Flex\ComponentButtonHeight;
use LINE\LINEBot\Constant\Flex\ComponentButtonStyle;
use LINE\LINEBot\Constant\Flex\ComponentFontSize;
use LINE\LINEBot\Constant\Flex\ComponentFontWeight;
use LINE\LINEBot\Constant\Flex\ComponentIconSize;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
use LINE\LINEBot\Constant\Flex\ComponentImageSize;
use LINE\LINEBot\Constant\Flex\ComponentLayout;
use LINE\LINEBot\Constant\Flex\ComponentMargin;
use LINE\LINEBot\Constant\Flex\ComponentSpacing;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\SeparatorComponentBuilder;
use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\IconComponentBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
use LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder;
use LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder;
use LINE\LINEBot\TemplateActionBuilder\LocationTemplateActionBuilder;
use Illuminate\Support\Facades\Cache;
use App\Services\ShopSearchService;
use App\Services\MenuService;

class LineBotController extends Controller
{
    private $bot;
    private $shopSearchService;
    private $menuService;
    private $tgToken;
    private $tgClient;
    private $tgChatId;

    public function __construct()
    {
        $httpClient = new CurlHTTPClient(config('line.LINE_CHANNEL_ACCESS_TOKEN'));
        $this->bot  = new LINEBot($httpClient, ['channelSecret' => config('line.LINE_CHANNEL_SECRET')]);
        $this->shopSearchService = new ShopSearchService();
        $this->menuService = new MenuService();

        // 初始化 Telegram
        $this->tgToken = env('TELEGRAM_TOKEN', '');
        $this->tgChatId = env('TELEGRAM_CHAT_ID', '7989823638');
        $this->tgClient = new Client();
    }

    protected function dd($data)
    {
        // 發送到 Telegram
        $this->sendToTelegram(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 保留原本的 LINE Notify（如果需要可以啟用）
        // $owen_token = config('app.line_owen_token');
        // $client     = new Client();
        // $headers    = [
        //     'Authorization' => sprintf('Bearer %s', $owen_token),
        //     'Content-Type'  => 'application/x-www-form-urlencoded'
        // ];
        // $options    = [
        //     'form_params' => [
        //         'message' => json_encode($data)
        //     ]
        // ];
        // $response   = $client->request('POST', 'https://notify-api.line.me/api/notify', [
        //     'headers'     => $headers,
        //     'form_params' => $options['form_params']
        // ]);
    }

    /**
     * 發送訊息到 Telegram
     */
    protected function sendToTelegram($text)
    {
        if (empty($this->tgToken)) {
            return; // 如果沒有設定 Token 就不發送
        }

        $url = "https://api.telegram.org/bot{$this->tgToken}/sendMessage";

        $params = [
            'chat_id' => $this->tgChatId,
            'text'    => $text,
        ];

        try {
            $res = $this->tgClient->post($url, ['form_params' => $params, 'timeout' => 5,]);
            return json_decode($res->getBody()->getContents(), true);
        } catch (\Exception $e) {
            // 錯誤紀錄
            \Log::error('Telegram send error', ['error' => $e->getMessage()]);
        }
    }

    public function webhook(Request $request)
    {
        // 設定錯誤處理器以捕捉 Fatal Error
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                $this->sendToTelegram("💥 Fatal Error: " . $error['message'] . "\n" . 
                                    "File: " . $error['file'] . ":" . $error['line']);
            }
        });
        
        // 記錄收到的請求
        $this->sendToTelegram("🔵 收到 LINE Webhook 請求\n" . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        try {
            $events = $request->events;

            foreach ($events as $event) {
                if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
                    $userMessage = $event['message']['text'];

                    // 記錄收到的訊息
                    $this->sendToTelegram("📨 收到訊息: {$userMessage}");
                    
                    // 正規化訊息：將日文「説」替換為中文「說」
                    $normalizedMessage = str_replace('説', '說', $userMessage);

                    if ($normalizedMessage == '使用說明') {
                        $this->showInstructions($event['replyToken']);
                    } elseif ($normalizedMessage == '店家清單') {
                        $this->replyWithShopList($event['replyToken']);
                    } elseif ($normalizedMessage == 'TOP名店') {
                        $this->showTopShops($event['replyToken']);
                    } elseif ($normalizedMessage == '隨機飲料店') {
                        $this->showRandomShop($event['replyToken']);
                    } elseif ($normalizedMessage == '找茶') {
                        $this->showTeaShops($event['replyToken']);
                    } elseif ($normalizedMessage == '奶類') {
                        $this->showMilkShops($event['replyToken']);
                    } elseif ($normalizedMessage == '飲料標籤') {
                        $this->showShopTags($event['replyToken']);
                    } elseif ($normalizedMessage == '菜單') {
                        $message = "請選擇功能：\n\n";
                        $message .= "輸入「飲料店」- 查看所有飲料店\n";
                        $message .= "輸入「喝什麼」- 隨機推薦飲料店\n";
                        $message .= "或直接輸入飲料店名稱查看菜單";

                        $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder($message));
                    } elseif ($userMessage == '喝什麼') {
                        $shops = config('menu.shops.drink');

                        if (empty($shops)) {
                            $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder('抱歉，目前沒有可用的飲料店資訊。'));
                            return;
                        }

                        // 随机选择最多10个店铺
                        $randomKeys = (count($shops) > 10) ? array_rand($shops, 10) : array_keys($shops);
                        shuffle($randomKeys); // 随机排序选出的店铺键
                        $columns = [];
                        foreach ($randomKeys as $key) {
                            $shop = $this->menuService->getMenuByBrandCode($key);
                            if (is_null($shop)) {
                                continue;
                            }
                            $shopName     = $shop['shop_name']; // 使用 $key 作为店铺名称
                            $postbackData = http_build_query(['action' => 'select', 'shop' => $key]);
                            $action       = new PostbackTemplateActionBuilder($shopName, $postbackData);

                            $column    = new CarouselColumnTemplateBuilder($shopName, // title
                                '點擊看菜單', // text
                                $shop['image_url'], // image url (optional)
                                [$action] // actions
                            );
                            $columns[] = $column;
                        }

                        $carouselTemplateBuilder = new CarouselTemplateBuilder($columns);
                        $templateMessage         = new TemplateMessageBuilder('選擇飲料店', $carouselTemplateBuilder);
                        $this->bot->replyMessage($event['replyToken'], $templateMessage);

                    } elseif ($userMessage == '飲料店') {
                        $this->replyWithShopList($event['replyToken']);
                    } elseif (array_keys(config('menu.shops.drink'), $userMessage)) {
                        // 完全匹配店名
                        $matchingKeys = array_keys(config('menu.shops.drink'), $userMessage);
                        $shopName = $matchingKeys[0];
                        $shop = $this->menuService->getMenuByBrandCode($shopName);
                        if ($shop) {
                            $this->replyWithShopMenu($event['replyToken'], $shop, $shop['shop_name'] . ' 菜單');
                        }
                    } else {
                        // 先檢查是否為標籤
                        $tags = config('shop_tags');
                        if (isset($tags[$userMessage])) {
                            $this->showShopsByTag($event['replyToken'], $tags[$userMessage], "🏷️ {$userMessage}");
                            return;
                        }

                        // 關鍵字搜尋
                        $searchResults = $this->shopSearchService->search($userMessage);

                        if (count($searchResults) == 1) {
                            // 只找到一個結果，直接顯示菜單
                            $shopCode = array_key_first($searchResults);
                            $this->sendToTelegram("🔍 找到唯一店家: {$shopCode}");
                            
                            try {
                                $shop = $this->menuService->getMenuByBrandCode($shopCode);
                                if ($shop) {
                                    $this->sendToTelegram("✅ 成功載入菜單，準備顯示");
                                    $this->replyWithShopMenu($event['replyToken'], $shop, $shop['shop_name'] . ' 菜單');
                                } else {
                                    $this->sendToTelegram("❌ 無法載入店家菜單: {$shopCode}");
                                    $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder(
                                        "抱歉，無法載入 {$searchResults[$shopCode]} 的菜單資料 😢"
                                    ));
                                }
                            } catch (\Exception $e) {
                                $this->sendToTelegram("❌ 顯示菜單時發生錯誤: " . $e->getMessage());
                                $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder(
                                    "抱歉，顯示菜單時發生錯誤，請稍後再試 😢"
                                ));
                            }
                        } elseif (count($searchResults) > 1) {
                            // 多個結果，顯示選擇列表
                            $this->replyWithSearchResults($event['replyToken'], $searchResults, $userMessage);
                        } else {
                            // 沒有找到結果
                            $message = "找不到「{$userMessage}」相關的飲料店 😢\n\n";
                            $message .= "試試這些關鍵字：\n";
                            $suggestions = $this->shopSearchService->getSuggestedKeywords();
                            $message .= implode('、', array_slice($suggestions, 0, 5));
                            $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder($message));
                        }
                    }

                } elseif ($event['type'] == 'message' && $event['message']['type'] == 'location') {
                    // 處理位置訊息
                    $this->handleLocationMessage($event);
                    
                } elseif ($event['type'] == 'postback') {
                    $data = $event['postback']['data'];
                    parse_str($data, $postbackData);

                    // 記錄 postback 事件
                    $this->sendToTelegram("🔘 收到 Postback: " . json_encode($postbackData, JSON_UNESCAPED_UNICODE));

                    try {
                        if ($postbackData['action'] == 'select' && isset($postbackData['shop'])) {
                            $shopName = $postbackData['shop'];
                            $shop = $this->menuService->getMenuByBrandCode($shopName);
                            if ($shop) {
                                $this->replyWithShopMenu($event['replyToken'], $shop, $shop['shop_name'] . ' 菜單');
                            }
                        } elseif ($postbackData['action'] == 'view_category' && isset($postbackData['shop']) && isset($postbackData['category'])) {
                            // 顯示特定分類的菜單
                            $shopCode = $postbackData['shop'];
                            $category = $postbackData['category'];
                            $shop = $this->menuService->getMenuByBrandCode($shopCode);
                            
                            if ($shop && isset($shop['menu_items'][$category])) {
                                $this->sendToTelegram("📋 顯示分類: {$category}");
                                $this->replyWithCategoryItems($event['replyToken'], $shop, $category);
                            } else {
                                $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder(
                                    "找不到該分類的資料 😢"
                                ));
                            }
                        } elseif ($postbackData['action'] == 'instructions') {
                            // 顯示使用說明
                            $message = "📖 使用說明\n\n";
                            $message .= "🔸 使用說明：查看功能介紹\n";
                            $message .= "🔸 店家清單：瀏覽所有飲料店\n";
                            $message .= "🔸 TOP名店：查看熱門推薦店家\n";
                            $message .= "🔸 隨機飲料店：讓系統推薦一家店\n";
                            $message .= "🔸 找茶：茶類專門店\n";
                            $message .= "🔸 奶類：鮮奶茶專門店\n";
                            $message .= "🔸 快速搜尋附近店家：搜尋 2km 內的店家\n";
                            $message .= "🔸 自訂搜尋範圍：選擇搜尋距離再找店家\n\n";
                            $message .= "💡 也可以直接輸入店名或關鍵字搜尋！\n";
                            $message .= "📍 或直接分享位置來尋找附近店家！";
                            $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder($message));
                        } elseif ($postbackData['action'] == 'random') {
                            // 執行「喝什麼」功能
                            $this->showRandomShop($event['replyToken']);
                        } elseif ($postbackData['action'] == 'random_brand') {
                            // 執行隨機品牌推薦
                            $this->showRandomBrand($event['replyToken']);
                        } elseif ($postbackData['action'] == 'request_location_for_random') {
                            // 請求位置來進行附近隨機推薦
                            $userId = $event['source']['userId'] ?? null;
                            if ($userId) {
                                Cache::put("user_action_{$userId}", 'random_nearby', 300); // 暫存 5 分鐘
                            }
                            $this->requestLocationForRandom($event['replyToken']);
                        } elseif ($postbackData['action'] == 'shoplist') {
                            // 執行「飲料店」功能
                            $this->replyWithShopList($event['replyToken']);
                        } elseif ($postbackData['action'] == 'tags') {
                            // 執行「飲料標籤」功能
                            $this->showShopTags($event['replyToken']);
                        } elseif ($postbackData['action'] == 'aliases') {
                            // 執行「飲料店別名」功能
                            $this->showShopAliases($event['replyToken']);
                        } elseif ($postbackData['action'] == 'nearby') {
                            // 執行「找附近飲料店」功能（舊版，保留相容性）
                            $this->requestLocation($event['replyToken']);
                        } elseif ($postbackData['action'] == 'nearby_quick') {
                            // 快速搜尋附近店家（使用預設 2km）
                            $this->requestLocation($event['replyToken'], true);
                        } elseif ($postbackData['action'] == 'nearby_custom') {
                            // 自訂搜尋範圍
                            $this->showDistanceOptions($event['replyToken']);
                        } elseif ($postbackData['action'] == 'search_nearby') {
                            // 執行距離搜尋
                            $lat = (float)($postbackData['lat'] ?? 0);
                            $lng = (float)($postbackData['lng'] ?? 0);
                            $distance = (float)($postbackData['distance'] ?? 2.0);
                            
                            $this->sendToTelegram("📍 執行搜尋: 距離 {$distance} 公里");
                            $this->findNearbyShops($event['replyToken'], $lat, $lng, $distance);
                        } elseif ($postbackData['action'] == 'select_distance') {
                            // 選擇距離後，要求分享位置（舊版，保留相容性）
                            $distance = (float)($postbackData['distance'] ?? 2.0);
                            $this->requestLocationWithDistance($event['replyToken'], $distance);
                        } elseif ($postbackData['action'] == 'custom_search') {
                            // 自訂搜尋範圍 - 選擇距離後要求分享位置
                            $distance = (float)($postbackData['distance'] ?? 2.0);
                            $userId = $event['source']['userId'] ?? null;
                            
                            if ($userId) {
                                // 暫存用戶選擇的距離（5分鐘有效）
                                Cache::put("custom_search_distance_{$userId}", $distance, 300);
                                $this->sendToTelegram("🎯 儲存用戶 {$userId} 的自訂距離：{$distance} km");
                            }
                            
                            $this->requestLocationForCustomSearch($event['replyToken'], $distance);
                        }
                    } catch (\Exception $e) {
                        // 發送錯誤到 Telegram
                        $errorMsg = "❌ Postback 處理錯誤\n";
                        $errorMsg .= "Action: " . ($postbackData['action'] ?? 'unknown') . "\n";
                        $errorMsg .= "錯誤: " . $e->getMessage() . "\n";
                        $errorMsg .= "位置: " . $e->getFile() . ":" . $e->getLine();
                        $this->sendToTelegram($errorMsg);

                        // 回傳錯誤訊息給用戶
                        $errorMsg = "❌ 發生錯誤：\n";
                        $errorMsg .= "錯誤訊息：" . $e->getMessage() . "\n";
                        $errorMsg .= "錯誤位置：" . $e->getFile() . ":" . $e->getLine();
                        $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder($errorMsg));
                    }
                }
            }
        } catch (\Exception $e) {
            // 最外層的錯誤處理
            $errorMsg = "❌ 主要錯誤\n";
            $errorMsg .= "錯誤: " . $e->getMessage() . "\n";
            $errorMsg .= "位置: " . $e->getFile() . ":" . $e->getLine();
            $this->sendToTelegram($errorMsg);
        }
        return response()->json(['status' => 'success'], 200);
    }

    private function replyWithShopList($replyToken)
    {
        $shops = config('menu.shops.drink');

        if (empty($shops)) {
            $this->bot->replyMessage($replyToken, new TextMessageBuilder('抱歉，目前沒有可用的飲料店資訊。'));
            return;
        }

        // 构建所有饮料店信息的 Flex Components
        $shopComponents = [];
        // 顯示更多店家，但保持適當數量避免過長
        $randomKeys = (count($shops) > 30) ? array_rand($shops, 30) : array_keys($shops);
        shuffle($randomKeys); // 隨機排序
        foreach ($randomKeys as $key) {
//        foreach ($shops as $shopName => $shopInfo) {
            $shopName = $shops[$key]; // 或者直接用 $key 如果鍵名就是店鋪名
            $shop = $this->menuService->getMenuByBrandCode($key);
            if (is_null($shop)) {
                continue;
            }
            $buttonAction     = new PostbackTemplateActionBuilder('查看菜单', "action=select&shop={$key}");
            // 在每個店名前加入間距
            if (count($shopComponents) > 0) {
                // 加入分隔線
                $shopComponents[] = SeparatorComponentBuilder::builder()
                    ->setMargin('md');
            }

            $shopComponents[] = BoxComponentBuilder::builder()
                ->setLayout('baseline')
                ->setMargin('lg')  // 增加外邊距
                ->setContents([
                    TextComponentBuilder::builder()
                        ->setAction($buttonAction)
                        ->setText($shopName)
                        ->setSize('md')  // 從 xl 改為 md
                        ->setFlex(4),
                ]);
        }

        // 创建 Flex Message
        $flexMessageBuilder = FlexMessageBuilder::builder()
            ->setAltText('飲料店列表') // 设置备用文字
            ->setContents(BubbleContainerBuilder::builder()
                ->setHero(ImageComponentBuilder::builder()
                    ->setUrl('https://i.meee.com.tw/ElQ1WxG.jpg')
                    ->setSize('full')
                    ->setAspectRatio('20:13')
                    ->setAspectMode('cover'))
                ->setBody(BoxComponentBuilder::builder()
                    ->setLayout('vertical')
                    ->setContents($shopComponents)));

        // 使用LINE Bot实例发送消息
        $this->bot->replyMessage($replyToken, $flexMessageBuilder);
    }


    private function replyWithShopMenu($replyToken, $shop, $title)
    {
        try {
            // 優先檢查是否有菜單圖片
            $menuImagePath = "/images/menus/{$shop['shop_name']}.png";
            $fullImagePath = public_path($menuImagePath);
            
            if (file_exists($fullImagePath)) {
                $this->sendToTelegram("📷 使用圖片菜單: {$menuImagePath}");
                $this->replyWithMenuImage($replyToken, $shop, $title);
                return;
            }
            
            // 檢查菜單資料是否存在
            if (!isset($shop['menu_items']) || empty($shop['menu_items'])) {
                $this->sendToTelegram("⚠️ 店家菜單資料不完整: " . json_encode($shop, JSON_UNESCAPED_UNICODE));
                $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                    "抱歉，{$shop['shop_name']} 的菜單資料不完整 😢"
                ));
                return;
            }
            
            // 計算菜單項目總數
            $totalItems = 0;
            foreach ($shop['menu_items'] as $category => $categoryData) {
                if (isset($categoryData['items']) && is_array($categoryData['items'])) {
                    $totalItems += count($categoryData['items']);
                } elseif (is_array($categoryData)) {
                    $totalItems += count($categoryData);
                }
            }
            
            // 如果項目太多，使用分類選擇方式
            if ($totalItems > 50 || count($shop['menu_items']) > 5) {
                $this->sendToTelegram("📋 菜單太大 ({$totalItems} 項)，使用分類選擇");
                $this->replyWithCategorySelection($replyToken, $shop, $title);
                return;
            }

            $coldEmoji = "\u{2744}\u{FE0F}"; // ❄️ 雪花
            $hotEmoji  = "\u{1F525}"; // 🔥 火焰

            // 创建饮料项目组件
            $itemComponents = [];
            foreach ($shop['menu_items'] as $category => $categoryData) {
                // 處理 beverage_shops.php 格式（有 'items' 鍵）和一般格式
                if (isset($categoryData['items']) && is_array($categoryData['items'])) {
                    $items = $categoryData['items'];
                } elseif (is_array($categoryData)) {
                    $items = $categoryData;
                } else {
                    $this->sendToTelegram("⚠️ 分類 {$category} 的項目格式不正確");
                    continue;
                }
                
                $itemComponents[] = TextComponentBuilder::builder()
                    ->setText($category)
                    ->setWeight('bold')
                    ->setSize('md');

                foreach ($items as $item) {
                    // 確保 $item 是陣列且有 name 欄位
                    if (!is_array($item) || !isset($item['name'])) {
                        continue;
                    }
                    
                    $priceText = ' ';
                    if (! empty($item['price_cold'])) {
                        $priceText .= $item['price_cold'] . $coldEmoji;
                    }
                    if (! empty($item['price_hot'])) {
                        if (! empty($priceText) && trim($priceText) !== '') {
                            $priceText .= ' '; // 分隔冷热价格
                        }
                        $priceText .= $item['price_hot'] . $hotEmoji;
                    }
                    if (! empty($item['price'])) {
                        if (! empty($priceText) && trim($priceText) !== '') {
                            $priceText .= ' '; // 分隔冷热价格
                        }
                        $priceText .= $item['price'] . '$';
                    }
                $itemComponents[] = BoxComponentBuilder::builder()
                    ->setLayout('baseline')
                    ->setContents([
                        TextComponentBuilder::builder()
                            ->setText($item['name'])
                            //                            ->setWeight('bold')
                            ->setSize('sm')
                            ->setFlex(1),
                        TextComponentBuilder::builder()
                            ->setText($priceText)
                            ->setSize('sm')
                            ->setAlign('end')
                            ->setFlex(1)
                    ]);
            }
        }

        // 创建Flex Message
        if (isset($shop['web_url'])) {
            $web_url = new UriTemplateActionBuilder('官網', $shop['web_url']);
        } else {
            $web_url = new UriTemplateActionBuilder('你訂', sprintf('https://order.nidin.shop/brand/%s/', $shop['brand_code']));
        }
        $flexMessageBuilder = FlexMessageBuilder::builder()
            ->setAltText($title) // 设置备用文字
            ->setContents(BubbleContainerBuilder::builder()
                ->setHero(ImageComponentBuilder::builder()
                    ->setUrl($shop['image_url'])
                    ->setSize('full')
                    ->setAspectRatio('20:13')
                    ->setAspectMode('cover'))
                ->setBody(BoxComponentBuilder::builder()
                    ->setLayout('vertical')
                    ->setContents($itemComponents))
                ->setFooter(BoxComponentBuilder::builder()
                    ->setLayout('vertical')
                    ->setSpacing('sm')
                    ->setContents([
                        ButtonComponentBuilder::builder()
                            ->setStyle('link')
                            ->setHeight('sm')
                            ->setAction($web_url)
                        // 设置按钮动作链接
                    ])));

        // 使用LINE Bot实例发送消息
        $response = $this->bot->replyMessage($replyToken, $flexMessageBuilder);
        
        if ($response->isSucceeded()) {
            $this->sendToTelegram("✅ 成功發送菜單");
        } else {
            $this->sendToTelegram("❌ 發送菜單失敗: " . $response->getRawBody());
        }
        
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ replyWithShopMenu 錯誤: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                "抱歉，顯示菜單時發生錯誤 😢\n請稍後再試或聯繫客服。"
            ));
        }
    }

    /**
     * 使用圖片顯示菜單
     */
    private function replyWithMenuImage($replyToken, $shop, $title)
    {
        try {
            $menuImagePath = "/images/menus/{$shop['shop_name']}.png";
            $imageUrl = url($menuImagePath);
            
            // 確保是 HTTPS
            if (substr($imageUrl, 0, 7) === 'http://') {
                $imageUrl = 'https://' . substr($imageUrl, 7);
            }
            
            $this->sendToTelegram("📷 發送圖片菜單: {$imageUrl}");
            
            // 使用 Flex Message 顯示圖片
            $flexMessageBuilder = FlexMessageBuilder::builder()
                ->setAltText($title)
                ->setContents(
                    BubbleContainerBuilder::builder()
                        ->setHeader(
                            BoxComponentBuilder::builder()
                                ->setLayout(ComponentLayout::VERTICAL)
                                ->setContents([
                                    TextComponentBuilder::builder()
                                        ->setText($title)
                                        ->setWeight(ComponentFontWeight::BOLD)
                                        ->setSize(ComponentFontSize::LG)
                                        ->setAlign('center')
                                ])
                        )
                        ->setHero(
                            ImageComponentBuilder::builder()
                                ->setUrl($imageUrl)
                                ->setSize(ComponentImageSize::FULL)
                                ->setAspectRatio(ComponentImageAspectRatio::R20TO13)
                                ->setAspectMode(ComponentImageAspectMode::FIT)
                        )
                        ->setBody(
                            BoxComponentBuilder::builder()
                                ->setLayout(ComponentLayout::VERTICAL)
                                ->setContents([
                                    TextComponentBuilder::builder()
                                        ->setText('點擊圖片可放大查看')
                                        ->setSize(ComponentFontSize::SM)
                                        ->setColor('#999999')
                                        ->setAlign('center')
                                ])
                        )
                );
            
            $response = $this->bot->replyMessage($replyToken, $flexMessageBuilder);
            
            if ($response->isSucceeded()) {
                $this->sendToTelegram("✅ 成功發送圖片菜單");
            } else {
                $this->sendToTelegram("❌ 發送圖片菜單失敗: " . $response->getRawBody());
                // 降級到文字訊息
                $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                    "{$title}\n\n請參考店內菜單或官方網站查詢最新價格。"
                ));
            }
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ replyWithMenuImage 錯誤: " . $e->getMessage());
            $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                "抱歉，無法顯示菜單圖片 😢"
            ));
        }
    }
    
    /**
     * 顯示分類選擇
     */
    private function replyWithCategorySelection($replyToken, $shop, $title)
    {
        try {
            $categories = array_keys($shop['menu_items']);
            
            // 限制最多顯示 10 個分類
            if (count($categories) > 10) {
                $categories = array_slice($categories, 0, 10);
            }
            
            // 建立按鈕
            $buttons = [];
            foreach ($categories as $category) {
                // 處理 beverage_shops.php 格式
                $categoryData = $shop['menu_items'][$category];
                if (isset($categoryData['items']) && is_array($categoryData['items'])) {
                    $itemCount = count($categoryData['items']);
                } else {
                    $itemCount = is_array($categoryData) ? count($categoryData) : 0;
                }
                
                $postbackData = http_build_query([
                    'action' => 'view_category',
                    'shop' => $shop['brand_code'] ?? $shop['shop_name'],
                    'category' => $category
                ]);
                
                $buttons[] = ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder(
                        "{$category} ({$itemCount}項)",
                        $postbackData
                    ));
            }
            
            $flexMessageBuilder = FlexMessageBuilder::builder()
                ->setAltText($title)
                ->setContents(
                    BubbleContainerBuilder::builder()
                        ->setHeader(
                            BoxComponentBuilder::builder()
                                ->setLayout(ComponentLayout::VERTICAL)
                                ->setContents([
                                    TextComponentBuilder::builder()
                                        ->setText($title)
                                        ->setWeight(ComponentFontWeight::BOLD)
                                        ->setSize(ComponentFontSize::LG)
                                        ->setAlign('center')
                                ])
                        )
                        ->setBody(
                            BoxComponentBuilder::builder()
                                ->setLayout(ComponentLayout::VERTICAL)
                                ->setSpacing(ComponentSpacing::SM)
                                ->setContents(array_merge(
                                    [
                                        TextComponentBuilder::builder()
                                            ->setText('請選擇要查看的分類：')
                                            ->setSize(ComponentFontSize::MD)
                                            ->setMargin(ComponentMargin::MD)
                                    ],
                                    $buttons
                                ))
                        )
                );
            
            $response = $this->bot->replyMessage($replyToken, $flexMessageBuilder);
            
            if ($response->isSucceeded()) {
                $this->sendToTelegram("✅ 成功發送分類選擇");
            } else {
                $this->sendToTelegram("❌ 發送分類選擇失敗: " . $response->getRawBody());
            }
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ replyWithCategorySelection 錯誤: " . $e->getMessage());
            $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                "抱歉，無法顯示分類選擇 😢"
            ));
        }
    }

    /**
     * 顯示分類中的菜單項目
     */
    private function replyWithCategoryItems($replyToken, $shop, $category)
    {
        try {
            $this->sendToTelegram("📋 開始顯示分類項目: {$category}");
            
            // 確認分類存在
            if (!isset($shop['menu_items'][$category])) {
                $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                    "找不到 {$category} 分類的資料 😢"
                ));
                return;
            }
            
            // 處理 beverage_shops.php 格式
            $categoryData = $shop['menu_items'][$category];
            if (isset($categoryData['items']) && is_array($categoryData['items'])) {
                $items = $categoryData['items'];
            } elseif (is_array($categoryData)) {
                $items = $categoryData;
            } else {
                $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                    "分類資料格式錯誤 😢"
                ));
                return;
            }
            $coldEmoji = "\u{2744}\u{FE0F}"; // ❄️ 雪花
            $hotEmoji  = "\u{1F525}"; // 🔥 火焰
            
            // 建立項目組件
            $itemComponents = [];
            
            // 標題
            $itemComponents[] = TextComponentBuilder::builder()
                ->setText("{$shop['shop_name']} - {$category}")
                ->setWeight(ComponentFontWeight::BOLD)
                ->setSize(ComponentFontSize::LG)
                ->setMargin(ComponentMargin::MD);
                
            // 分隔線
            $itemComponents[] = SeparatorComponentBuilder::builder()
                ->setMargin(ComponentMargin::MD);
            
            // 項目列表
            foreach ($items as $item) {
                if (!is_array($item) || !isset($item['name'])) {
                    continue;
                }
                
                $priceText = '';
                if (!empty($item['price_cold'])) {
                    $priceText .= $coldEmoji . ' $' . $item['price_cold'];
                }
                if (!empty($item['price_hot'])) {
                    if ($priceText) $priceText .= ' / ';
                    $priceText .= $hotEmoji . ' $' . $item['price_hot'];
                }
                
                // 如果沒有價格資訊，使用預設文字
                if (empty($priceText)) {
                    $priceText = '價格請洽店家';
                }
                
                // 建立項目區塊
                $itemComponents[] = BoxComponentBuilder::builder()
                    ->setLayout(ComponentLayout::HORIZONTAL)
                    ->setMargin(ComponentMargin::MD)
                    ->setContents([
                        TextComponentBuilder::builder()
                            ->setText($item['name'])
                            ->setSize(ComponentFontSize::SM)
                            ->setFlex(3)
                            ->setWrap(true),
                        TextComponentBuilder::builder()
                            ->setText($priceText)
                            ->setSize(ComponentFontSize::SM)
                            ->setAlign('end')
                            ->setFlex(2)
                    ]);
            }
            
            // 加入返回按鈕
            $itemComponents[] = SeparatorComponentBuilder::builder()
                ->setMargin(ComponentMargin::LG);
                
            $itemComponents[] = ButtonComponentBuilder::builder()
                ->setStyle(ComponentButtonStyle::LINK)
                ->setHeight(ComponentButtonHeight::SM)
                ->setAction(new PostbackTemplateActionBuilder(
                    '⬅️ 返回分類選擇',
                    http_build_query(['action' => 'select', 'shop' => $shop['brand_code']])
                ))
                ->setColor('#666666')
                ->setMargin(ComponentMargin::MD);
            
            // 建立 Flex Message
            $flexMessageBuilder = FlexMessageBuilder::builder()
                ->setAltText("{$shop['shop_name']} - {$category}")
                ->setContents(
                    BubbleContainerBuilder::builder()
                        ->setBody(
                            BoxComponentBuilder::builder()
                                ->setLayout(ComponentLayout::VERTICAL)
                                ->setContents($itemComponents)
                        )
                );
            
            $response = $this->bot->replyMessage($replyToken, $flexMessageBuilder);
            
            if ($response->isSucceeded()) {
                $this->sendToTelegram("✅ 成功發送分類項目");
            } else {
                $this->sendToTelegram("❌ 發送分類項目失敗: " . $response->getRawBody());
            }
            
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ replyWithCategoryItems 錯誤: " . $e->getMessage());
            $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                "顯示分類項目時發生錯誤 😢"
            ));
        }
    }

    /**
     * 顯示搜尋結果列表
     */
    private function replyWithSearchResults($replyToken, $searchResults, $keyword)
    {
        // 限制最多顯示10個結果
        $searchResults = array_slice($searchResults, 0, 10, true);

        if (count($searchResults) <= 5) {
            // 使用 Carousel 顯示（5個以下）
            $columns = [];
            foreach ($searchResults as $shopCode => $shopName) {
                $shop = $this->menuService->getMenuByBrandCode($shopCode);
                if (!$shop) {
                    continue;
                }

                $postbackData = http_build_query(['action' => 'select', 'shop' => $shopCode]);
                $action = new PostbackTemplateActionBuilder($shopName, $postbackData);

                $column = new CarouselColumnTemplateBuilder(
                    $shopName,
                    '點擊查看菜單',
                    isset($shop['image_url']) ? $shop['image_url'] : null,
                    [$action]
                );
                $columns[] = $column;
            }

            if (!empty($columns)) {
                $carouselTemplateBuilder = new CarouselTemplateBuilder($columns);
                $templateMessage = new TemplateMessageBuilder(
                    "找到 " . count($searchResults) . " 個「{$keyword}」相關的飲料店",
                    $carouselTemplateBuilder
                );
                $this->bot->replyMessage($replyToken, $templateMessage);
            }
        } else {
            // 使用 Flex Message 顯示（超過5個）
            $shopComponents = [];
            $shopComponents[] = TextComponentBuilder::builder()
                ->setText("找到 " . count($searchResults) . " 個「{$keyword}」相關的飲料店")
                ->setWeight('bold')
                ->setSize('lg')
                ->setMargin('md');

            $shopComponents[] = SeparatorComponentBuilder::builder()
                ->setMargin('md');

            foreach ($searchResults as $shopCode => $shopName) {
                $buttonAction = new PostbackTemplateActionBuilder('查看菜單', "action=select&shop={$shopCode}");

                $shopComponents[] = BoxComponentBuilder::builder()
                    ->setLayout('baseline')
                    ->setMargin('md')
                    ->setContents([
                        TextComponentBuilder::builder()
                            ->setAction($buttonAction)
                            ->setText($shopName)
                            ->setSize('md')
                            ->setColor('#1976D2')
                            ->setFlex(4),
                    ]);
            }

            $flexMessageBuilder = FlexMessageBuilder::builder()
                ->setAltText("找到 " . count($searchResults) . " 個相關飲料店")
                ->setContents(BubbleContainerBuilder::builder()
                    ->setBody(BoxComponentBuilder::builder()
                        ->setLayout('vertical')
                        ->setContents($shopComponents)));

            $this->bot->replyMessage($replyToken, $flexMessageBuilder);
        }
    }

    /**
     * 顯示使用說明
     */
    private function showInstructions($replyToken)
    {
        // 追蹤進入方法
        $this->sendToTelegram("📍 進入 showInstructions 方法, replyToken: {$replyToken}");

        try {
            // 使用 Flex Message 支援7個選項
            $buttonComponents = [
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('📖 使用說明', 'action=instructions'))
                    ->setColor('#1976D2'),
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('🎲 喝什麼', 'action=random'))
                    ->setColor('#388E3C'),
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('🏪 飲料店', 'action=shoplist'))
                    ->setColor('#F57C00'),
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('🏷️ 飲料標籤', 'action=tags'))
                    ->setColor('#7B1FA2'),
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('🔤 飲料店別名', 'action=aliases'))
                    ->setColor('#C2185B'),
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('📍 快速搜尋附近店家', 'action=nearby_quick'))
                    ->setColor('#E91E63'),
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('🎯 自訂搜尋範圍', 'action=nearby_custom'))
                    ->setColor('#FF5722'),
            ];

        // 使用按鈕組件
        $components = $buttonComponents;

        $flexMessageBuilder = FlexMessageBuilder::builder()
            ->setAltText('功能選單')
            ->setContents(BubbleContainerBuilder::builder()
                ->setHeader(BoxComponentBuilder::builder()
                    ->setLayout(ComponentLayout::VERTICAL)
                    ->setContents([
                        TextComponentBuilder::builder()
                            ->setText('功能選單')
                            ->setWeight(ComponentFontWeight::BOLD)
                            ->setSize(ComponentFontSize::LG)
                            ->setAlign('center')
                    ]))
                ->setBody(BoxComponentBuilder::builder()
                    ->setLayout(ComponentLayout::VERTICAL)
                    ->setSpacing(ComponentSpacing::SM)
                    ->setContents([
                        TextComponentBuilder::builder()
                            ->setText('請選擇您要使用的功能')
                            ->setSize(ComponentFontSize::SM)
                            ->setAlign('center')
                            ->setMargin(ComponentMargin::MD),
                        BoxComponentBuilder::builder()
                            ->setLayout(ComponentLayout::VERTICAL)
                            ->setMargin(ComponentMargin::LG)
                            ->setSpacing(ComponentSpacing::SM)
                            ->setContents($components)
                    ])));

            $this->sendToTelegram("📍 準備發送 Flex Message");

            // 記錄 FlexMessage 內容
            try {
                $messageArray = $flexMessageBuilder->buildMessage();
                $this->sendToTelegram("📍 FlexMessage 內容: " . substr(json_encode($messageArray, JSON_UNESCAPED_UNICODE), 0, 500));
            } catch (\Exception $e) {
                $this->sendToTelegram("📍 無法序列化 FlexMessage: " . $e->getMessage());
            }

            // 發送訊息並檢查結果
            $response = $this->bot->replyMessage($replyToken, $flexMessageBuilder);

            // 檢查 HTTP 狀態碼
            if ($response->isSucceeded()) {
                $this->sendToTelegram("✅ showInstructions 完成");
            } else {
                $httpStatus = $response->getHTTPStatus();
                $errorMessage = $response->getRawBody();
                $this->sendToTelegram("❌ replyMessage 失敗\nHTTP Status: {$httpStatus}\nError: {$errorMessage}");
            }

        } catch (\Exception $e) {
            $errorMsg = "❌ showInstructions 錯誤\n";
            $errorMsg .= "錯誤: " . $e->getMessage() . "\n";
            $errorMsg .= "位置: " . $e->getFile() . ":" . $e->getLine();
            $this->sendToTelegram($errorMsg);

            // 回傳簡單錯誤訊息
            $this->bot->replyMessage($replyToken, new TextMessageBuilder("功能選單暫時無法使用，請稍後再試"));
        }
    }

    /**
     * 顯示TOP名店
     */
    private function showTopShops($replyToken)
    {
        $topShops = config('shop_tags.TOP熱門店家');

        if (empty($topShops)) {
            $this->bot->replyMessage($replyToken, new TextMessageBuilder('目前沒有熱門店家資訊'));
            return;
        }

        // 隨機選擇最多10家顯示
        $selectedShops = array_slice($topShops, 0, 10, true);
        $columns = [];
        $skippedShops = [];

        foreach ($selectedShops as $shopCode => $shopName) {
            $shop = $this->menuService->getMenuByBrandCode($shopCode);
            if (!$shop) {
                $skippedShops[] = "{$shopName} ({$shopCode})";
                $this->sendToTelegram("⚠️ 無法載入TOP店家菜單: {$shopName} ({$shopCode})");
                continue;
            }

            $postbackData = http_build_query(['action' => 'select', 'shop' => $shopCode]);
            $action = new PostbackTemplateActionBuilder($shopName, $postbackData);

            $column = new CarouselColumnTemplateBuilder(
                $shopName,
                '🔥 熱門推薦',
                isset($shop['image_url']) ? $shop['image_url'] : null,
                [$action]
            );
            $columns[] = $column;
        }

        if (!empty($columns)) {
            $carouselTemplateBuilder = new CarouselTemplateBuilder($columns);
            $templateMessage = new TemplateMessageBuilder('TOP熱門店家', $carouselTemplateBuilder);
            $this->bot->replyMessage($replyToken, $templateMessage);
        } else {
            // 如果所有店家都被跳過，顯示錯誤訊息
            $errorMsg = "抱歉，TOP熱門店家的菜單目前無法載入。";
            if (!empty($skippedShops)) {
                $errorMsg .= "\n\n無法載入的店家：\n" . implode("\n", $skippedShops);
            }
            $this->bot->replyMessage($replyToken, new TextMessageBuilder($errorMsg));
        }
    }

    /**
     * 顯示隨機推薦選項
     */
    private function showRandomShop($replyToken)
    {
        // 取得使用者 ID 來設定狀態
        $userId = null;
        if (isset($event['source']['userId'])) {
            $userId = $event['source']['userId'];
        }
        
        // 建立 Quick Reply 按鈕
        $quickReplyButtons = [
            new QuickReplyButtonBuilder(
                new PostbackTemplateActionBuilder(
                    '🎲 隨機推薦品牌',
                    'action=random_brand'
                )
            ),
            new QuickReplyButtonBuilder(
                new PostbackTemplateActionBuilder(
                    '📍 附近隨機推薦',
                    'action=request_location_for_random'
                )
            )
        ];
        
        $quickReply = new QuickReplyMessageBuilder($quickReplyButtons);
        
        $message = "請選擇推薦方式：\n\n";
        $message .= "🎲 隨機推薦品牌\n";
        $message .= "從所有品牌中隨機選擇一家\n\n";
        $message .= "📍 附近隨機推薦\n";
        $message .= "從您附近 1km 內的店家隨機選擇";
        
        $textMessage = new TextMessageBuilder($message, $quickReply);
        $this->bot->replyMessage($replyToken, $textMessage);
    }
    
    /**
     * 顯示隨機品牌
     */
    private function showRandomBrand($replyToken)
    {
        $shops = config('menu.shops.drink');

        if (empty($shops)) {
            $this->bot->replyMessage($replyToken, new TextMessageBuilder('目前沒有飲料店資訊'));
            return;
        }

        // 隨機選一家店
        $randomKey = array_rand($shops);
        $shop = $this->menuService->getMenuByBrandCode($randomKey);

        if ($shop) {
            $this->replyWithShopMenu($replyToken, $shop, '🎲 為您推薦：' . $shop['shop_name']);
        }
    }

    /**
     * 顯示茶類專門店
     */
    private function showTeaShops($replyToken)
    {
        $teaShops = config('shop_tags.茶專門');
        $this->showShopsByTag($replyToken, $teaShops, '🍵 茶類專門店');
    }

    /**
     * 顯示奶類飲品店
     */
    private function showMilkShops($replyToken)
    {
        $milkShops = config('shop_tags.鮮奶茶');
        $this->showShopsByTag($replyToken, $milkShops, '🥛 奶類飲品店');
    }

    /**
     * 根據標籤顯示店家
     */
    private function showShopsByTag($replyToken, $shops, $title)
    {
        if (empty($shops)) {
            $this->bot->replyMessage($replyToken, new TextMessageBuilder('目前沒有相關店家資訊'));
            return;
        }

        // 限制顯示數量
        $shops = array_slice($shops, 0, 10, true);
        $columns = [];
        $skippedShops = [];

        foreach ($shops as $shopCode => $shopName) {
            $shop = $this->menuService->getMenuByBrandCode($shopCode);
            if (!$shop) {
                $skippedShops[] = "{$shopName} ({$shopCode})";
                $this->sendToTelegram("⚠️ 無法載入店家菜單: {$shopName} ({$shopCode})");
                continue;
            }

            $postbackData = http_build_query(['action' => 'select', 'shop' => $shopCode]);
            $action = new PostbackTemplateActionBuilder($shopName, $postbackData);

            $column = new CarouselColumnTemplateBuilder(
                $shopName,
                '點擊查看菜單',
                isset($shop['image_url']) ? $shop['image_url'] : null,
                [$action]
            );
            $columns[] = $column;
        }

        if (!empty($columns)) {
            $carouselTemplateBuilder = new CarouselTemplateBuilder($columns);
            $templateMessage = new TemplateMessageBuilder($title, $carouselTemplateBuilder);
            $this->bot->replyMessage($replyToken, $templateMessage);
        } else {
            // 如果所有店家都被跳過，顯示錯誤訊息
            $errorMsg = "抱歉，{$title} 的店家菜單目前無法載入。";
            if (!empty($skippedShops)) {
                $errorMsg .= "\n\n無法載入的店家：\n" . implode("\n", $skippedShops);
            }
            $this->bot->replyMessage($replyToken, new TextMessageBuilder($errorMsg));
        }
    }

    /**
     * 顯示所有飲料標籤
     */
    private function showShopTags($replyToken)
    {
        $tags = config('shop_tags');
        
        // 建立標籤按鈕
        $tagButtons = [];
        $colorMap = [
            '黑糖系列' => '#795548',
            '鮮奶茶' => '#FF9800',
            '水果茶' => '#4CAF50',
            '精品茶飲' => '#673AB7',
            '茶專門' => '#009688',
            '手搖創新' => '#2196F3',
            '咖啡茶飲' => '#795548',
            '特色飲品' => '#E91E63',
            '泰式奶茶' => '#FF5722',
        ];
        
        foreach (array_keys($tags) as $tag) {
            if ($tag !== 'TOP熱門店家') {
                $color = isset($colorMap[$tag]) ? $colorMap[$tag] : '#607D8B';
                $tagButtons[] = ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new MessageTemplateActionBuilder($tag, $tag))
                    ->setColor($color);
            }
        }
        
        // 建立 Flex Message
        $flexMessageBuilder = FlexMessageBuilder::builder()
            ->setAltText('飲料店分類標籤')
            ->setContents(BubbleContainerBuilder::builder()
                ->setHeader(BoxComponentBuilder::builder()
                    ->setLayout(ComponentLayout::VERTICAL)
                    ->setContents([
                        TextComponentBuilder::builder()
                            ->setText('🏷️ 飲料店分類標籤')
                            ->setWeight(ComponentFontWeight::BOLD)
                            ->setSize(ComponentFontSize::LG)
                            ->setAlign('center')
                    ]))
                ->setBody(BoxComponentBuilder::builder()
                    ->setLayout(ComponentLayout::VERTICAL)
                    ->setSpacing(ComponentSpacing::SM)
                    ->setContents(array_merge(
                        [
                            TextComponentBuilder::builder()
                                ->setText('點選標籤查看相關店家')
                                ->setSize(ComponentFontSize::SM)
                                ->setAlign('center')
                                ->setMargin(ComponentMargin::MD)
                                ->setColor('#666666'),
                            SeparatorComponentBuilder::builder()
                                ->setMargin(ComponentMargin::MD)
                        ],
                        $tagButtons
                    ))));
        
        $this->bot->replyMessage($replyToken, $flexMessageBuilder);
    }

    /**
     * 顯示飲料店別名
     */
    private function showShopAliases($replyToken)
    {
        try {
            $keywords = config('shop_keywords', []);
            $shops = config('menu.shops.drink', []);

            // 隨機選取品牌來顯示別名
            $allShopCodes = array_keys($keywords);
            shuffle($allShopCodes); // 隨機打亂順序
            $popularShops = array_slice($allShopCodes, 0, 10); // 取前 10 個品牌

            $components = [
                TextComponentBuilder::builder()
                    ->setText('🔤 飲料店別名查詢')
                    ->setWeight(ComponentFontWeight::BOLD)
                    ->setSize(ComponentFontSize::LG)
                    ->setMargin(ComponentMargin::MD),
                TextComponentBuilder::builder()
                    ->setText('您可以使用以下別名快速查詢：')
                    ->setSize(ComponentFontSize::SM)
                    ->setMargin(ComponentMargin::MD)
                    ->setColor('#666666'),
                SeparatorComponentBuilder::builder()
                    ->setMargin(ComponentMargin::MD)
            ];

            $foundCount = 0;
            foreach ($popularShops as $shopCode) {
                // 加強錯誤檢查
                if (!isset($shops[$shopCode])) {
                    continue; // 店家不存在
                }

                if (!isset($keywords[$shopCode]) || !is_array($keywords[$shopCode])) {
                    continue; // 關鍵字不存在或不是陣列
                }

                $shopName = $shops[$shopCode];
                $aliases = $keywords[$shopCode];
                $foundCount++;

                // 店名標題
                $components[] = TextComponentBuilder::builder()
                    ->setText("【{$shopName}】")
                    ->setWeight(ComponentFontWeight::BOLD)
                    ->setSize(ComponentFontSize::MD)
                    ->setMargin(ComponentMargin::LG)
                    ->setColor('#1976D2');

                // 別名列表
                $aliasText = '✓ ' . implode('、', array_slice($aliases, 0, 4));
                $components[] = TextComponentBuilder::builder()
                    ->setText($aliasText)
                    ->setSize(ComponentFontSize::SM)
                    ->setMargin(ComponentMargin::SM)
                    ->setWrap(true);
            }

            // 如果沒有找到任何店家
            if ($foundCount === 0) {
                $components[] = TextComponentBuilder::builder()
                    ->setText('目前沒有可用的別名資料')
                    ->setSize(ComponentFontSize::SM)
                    ->setMargin(ComponentMargin::LG)
                    ->setColor('#999999');
            } else {
                // 加入提示
                $components[] = SeparatorComponentBuilder::builder()
                    ->setMargin(ComponentMargin::LG);

                $components[] = BoxComponentBuilder::builder()
                    ->setLayout(ComponentLayout::HORIZONTAL)
                    ->setMargin(ComponentMargin::LG)
                    ->setContents([
                        TextComponentBuilder::builder()
                            ->setText('💡')
                            ->setSize(ComponentFontSize::SM)
                            ->setFlex(0),
                        TextComponentBuilder::builder()
                            ->setText('直接輸入別名即可查看菜單！')
                            ->setSize(ComponentFontSize::SM)
                            ->setMargin(ComponentMargin::SM)
                            ->setFlex(1)
                            ->setWrap(true)
                    ]);

                $components[] = TextComponentBuilder::builder()
                    ->setText('例如：輸入「50」即可查看50嵐菜單')
                    ->setSize(ComponentFontSize::XXS)
                    ->setMargin(ComponentMargin::SM)
                    ->setColor('#999999');
            }

            $flexMessageBuilder = FlexMessageBuilder::builder()
                ->setAltText('飲料店別名查詢')
                ->setContents(BubbleContainerBuilder::builder()
                    ->setBody(BoxComponentBuilder::builder()
                        ->setLayout(ComponentLayout::VERTICAL)
                        ->setContents($components)));

            $this->bot->replyMessage($replyToken, $flexMessageBuilder);
        } catch (\Exception $e) {
            // 回傳簡單的錯誤訊息
            $errorMsg = "❌ 別名查詢功能發生錯誤\n";
            $errorMsg .= "請稍後再試或聯絡管理員";
            $this->bot->replyMessage($replyToken, new TextMessageBuilder($errorMsg));

            // 記錄詳細錯誤到 Telegram
            $errorMsg = "❌ showShopAliases 錯誤\n";
            $errorMsg .= "錯誤: " . $e->getMessage() . "\n";
            $errorMsg .= "位置: " . $e->getFile() . ":" . $e->getLine();
            $this->sendToTelegram($errorMsg);
        }
    }

    /**
     * 處理位置訊息
     */
    private function handleLocationMessage($event)
    {
        $latitude = $event['message']['latitude'];
        $longitude = $event['message']['longitude'];
        $address = $event['message']['address'] ?? '';
        $userId = $event['source']['userId'] ?? null;
        
        $this->sendToTelegram("📍 收到位置: {$latitude}, {$longitude}\n地址: {$address}");
        
        // 優先檢查是否是從隨機推薦來的位置分享
        if ($userId && Cache::get("user_action_{$userId}") === 'random_nearby') {
            // 清除暫存狀態
            Cache::forget("user_action_{$userId}");
            
            $this->sendToTelegram("🎲 執行附近隨機推薦");
            
            // 執行附近隨機推薦
            $this->randomNearbyShop($event['replyToken'], $latitude, $longitude);
            return;
        }
        
        // 其次檢查是否有自訂搜尋的距離設定
        if ($userId) {
            $customDistance = Cache::get("custom_search_distance_{$userId}");
            if ($customDistance !== null) {
                // 清除暫存
                Cache::forget("custom_search_distance_{$userId}");
                
                $this->sendToTelegram("🎯 使用自訂距離搜尋：{$customDistance} km");
                
                // 直接使用自訂距離搜尋
                $this->findNearbyShops($event['replyToken'], $latitude, $longitude, $customDistance);
                return;
            }
        }
        
        // 一般流程：顯示距離選擇選項
        $this->askSearchDistance($event['replyToken'], $latitude, $longitude);
    }

    /**
     * 詢問搜尋距離
     */
    private function askSearchDistance($replyToken, $latitude, $longitude)
    {
        try {
            $this->sendToTelegram("📍 顯示距離選擇選項");
            
            // 建立快速回覆按鈕
            $quickReplyButtons = [
                new QuickReplyButtonBuilder(
                    new PostbackTemplateActionBuilder(
                        '⚡ 快速搜尋 2km',
                        "action=search_nearby&lat={$latitude}&lng={$longitude}&distance=2"
                    )
                ),
                new QuickReplyButtonBuilder(
                    new PostbackTemplateActionBuilder(
                        '500 公尺',
                        "action=search_nearby&lat={$latitude}&lng={$longitude}&distance=0.5"
                    )
                ),
                new QuickReplyButtonBuilder(
                    new PostbackTemplateActionBuilder(
                        '1 公里',
                        "action=search_nearby&lat={$latitude}&lng={$longitude}&distance=1"
                    )
                ),
                new QuickReplyButtonBuilder(
                    new PostbackTemplateActionBuilder(
                        '2 公里',
                        "action=search_nearby&lat={$latitude}&lng={$longitude}&distance=2"
                    )
                ),
                new QuickReplyButtonBuilder(
                    new PostbackTemplateActionBuilder(
                        '5 公里',
                        "action=search_nearby&lat={$latitude}&lng={$longitude}&distance=5"
                    )
                ),
            ];
            
            // 建立快速回覆訊息
            $quickReply = new QuickReplyMessageBuilder($quickReplyButtons);
            
            // 建立文字訊息並附加快速回覆
            $textMessageBuilder = new TextMessageBuilder(
                "📍 請選擇搜尋範圍：\n\n" .
                "選擇較小的範圍可以找到最近的店家，\n" .
                "選擇較大的範圍可以看到更多選擇。",
                $quickReply
            );
            
            // 發送訊息
            $response = $this->bot->replyMessage($replyToken, $textMessageBuilder);
            
            if ($response->isSucceeded()) {
                $this->sendToTelegram("✅ 成功發送距離選擇選項");
            } else {
                $this->sendToTelegram("❌ 發送距離選擇失敗: " . $response->getRawBody());
                // 如果失敗，使用預設 2 公里搜尋
                $this->findNearbyShops($replyToken, $latitude, $longitude, 2.0);
            }
            
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ askSearchDistance 錯誤: " . $e->getMessage());
            // 發生錯誤時使用預設搜尋
            $this->findNearbyShops($replyToken, $latitude, $longitude, 2.0);
        }
    }

    /**
     * 搜尋附近店家資料（共用邏輯）
     * @return array
     */
    private function searchNearbyShopsData($userLat, $userLng, $searchDistance)
    {
        $nearbyShops = [];
        
        try {
            $nidinData = $this->shopSearchService->getNidinShops();
            $brands = config('menu.shops.drink');
            $brandCount = count($brands);
            
            $this->sendToTelegram("📍 開始搜尋 {$searchDistance} 公里內的店家，共有 {$brandCount} 個品牌");
            
            foreach ($nidinData as $shopCode => $stores) {
                $shopName = config("menu.shops.drink.{$shopCode}");
                if (!$shopName) {
                    continue;
                }
                
                try {
                    foreach ($stores as $store) {
                        if (empty($store['latitude']) || empty($store['longitude'])) {
                            continue;
                        }
                        
                        $distance = $this->calculateDistance(
                            $userLat, $userLng, 
                            (float)$store['latitude'], 
                            (float)$store['longitude']
                        );
                        
                        if ($distance <= $searchDistance) {
                            $nearbyShops[] = [
                                'shop_code' => $shopCode,
                                'shop_name' => $shopName,
                                'branch_name' => $store['name'] ?? $store['name_short'] ?? '分店',
                                'address' => $store['address'] ?? '',
                                'tel' => $store['tel'] ?? '',
                                'distance' => round($distance, 2),
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $this->sendToTelegram("❌ 讀取 {$shopCode} 資料錯誤: " . $e->getMessage());
                    continue;
                }
            }
            
            $this->sendToTelegram("📍 找到 " . count($nearbyShops) . " 家附近的店");
            
            // 按距離排序
            usort($nearbyShops, function($a, $b) {
                return $a['distance'] <=> $b['distance'];
            });
            
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ 搜尋店家時發生錯誤: " . $e->getMessage());
        }
        
        return $nearbyShops;
    }
    
    /**
     * 尋找附近的飲料店
     */
    private function findNearbyShops($replyToken, $userLat, $userLng, $searchDistance = 2.0)
    {
        // 使用共用邏輯搜尋附近店家
        $nearbyShops = $this->searchNearbyShopsData($userLat, $userLng, $searchDistance);
        
        // 顯示結果，傳遞搜尋距離
        $this->displayNearbyShops($replyToken, $nearbyShops, $searchDistance);
    }

    /**
     * 計算兩點之間的距離（公里）
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // 地球半徑（公里）
        
        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);
        
        $a = sin($latDiff/2) * sin($latDiff/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDiff/2) * sin($lonDiff/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    /**
     * 請求位置來進行附近隨機推薦
     */
    private function requestLocationForRandom($replyToken)
    {
        $quickReplyButtons = [
            new QuickReplyButtonBuilder(
                new LocationTemplateActionBuilder('📍 分享位置'),
                null
            )
        ];
        
        $quickReply = new QuickReplyMessageBuilder($quickReplyButtons);
        
        $message = "請分享您的位置，我會從您附近 1 公里內隨機推薦一家飲料店給您！";
        
        $textMessage = new TextMessageBuilder($message, $quickReply);
        $this->bot->replyMessage($replyToken, $textMessage);
    }
    
    /**
     * 隨機推薦附近的店家
     */
    private function randomNearbyShop($replyToken, $userLat, $userLng)
    {
        // 使用 1km 作為預設搜尋範圍
        $searchDistance = 1.0;
        
        $this->sendToTelegram("📍 開始搜尋附近 {$searchDistance}km 內的隨機店家");
        
        // 使用共用邏輯搜尋附近店家
        $nearbyShops = $this->searchNearbyShopsData($userLat, $userLng, $searchDistance);
        
        $this->sendToTelegram("📍 找到 " . count($nearbyShops) . " 家附近的店");
        
        if (empty($nearbyShops)) {
            $message = "您附近 1 公里內沒有找到飲料店 😢\n\n";
            $message .= "要不要試試看隨機推薦品牌？";
            
            // 提供 Quick Reply 選項
            $quickReplyButtons = [
                new QuickReplyButtonBuilder(
                    new PostbackTemplateActionBuilder(
                        '🎲 隨機推薦品牌',
                        'action=random_brand'
                    )
                )
            ];
            
            $quickReply = new QuickReplyMessageBuilder($quickReplyButtons);
            $textMessage = new TextMessageBuilder($message, $quickReply);
            $this->bot->replyMessage($replyToken, $textMessage);
            return;
        }
        
        // 隨機選擇一家
        $randomIndex = array_rand($nearbyShops);
        $selectedShop = $nearbyShops[$randomIndex];
        
        $this->sendToTelegram("📍 隨機選中：{$selectedShop['shop_name']} {$selectedShop['branch_name']}");
        
        // 載入並顯示菜單
        $shop = $this->menuService->getMenuByBrandCode($selectedShop['shop_code']);
        if ($shop) {
            $distance = $selectedShop['distance'];
            $branchName = $selectedShop['branch_name'];
            $title = "📍 為您推薦附近 {$distance}km 的：\n{$shop['shop_name']} {$branchName}";
            $this->replyWithShopMenu($replyToken, $shop, $title);
        }
    }

    /**
     * 顯示距離選項（用於自訂搜尋範圍）
     */
    private function showDistanceOptions($replyToken)
    {
        try {
            $this->sendToTelegram("🎯 顯示自訂搜尋範圍選項");
            
            // 使用 Flex Message 顯示距離選項
            $buttonComponents = [
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('🔍 搜尋 500 公尺內', 'action=custom_search&distance=0.5'))
                    ->setColor('#4CAF50'),
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('🔍 搜尋 1 公里內', 'action=custom_search&distance=1'))
                    ->setColor('#2196F3'),
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('🔍 搜尋 2 公里內', 'action=custom_search&distance=2'))
                    ->setColor('#FF9800'),
                ButtonComponentBuilder::builder()
                    ->setStyle(ComponentButtonStyle::LINK)
                    ->setHeight(ComponentButtonHeight::SM)
                    ->setAction(new PostbackTemplateActionBuilder('🔍 搜尋 5 公里內', 'action=custom_search&distance=5'))
                    ->setColor('#F44336'),
            ];
            
            $flexMessageBuilder = FlexMessageBuilder::builder()
                ->setAltText('選擇搜尋範圍')
                ->setContents(BubbleContainerBuilder::builder()
                    ->setHeader(BoxComponentBuilder::builder()
                        ->setLayout(ComponentLayout::VERTICAL)
                        ->setContents([
                            TextComponentBuilder::builder()
                                ->setText('🎯 選擇搜尋範圍')
                                ->setWeight(ComponentFontWeight::BOLD)
                                ->setSize(ComponentFontSize::LG)
                                ->setAlign('center')
                        ]))
                    ->setBody(BoxComponentBuilder::builder()
                        ->setLayout(ComponentLayout::VERTICAL)
                        ->setSpacing(ComponentSpacing::SM)
                        ->setContents([
                            TextComponentBuilder::builder()
                                ->setText('請選擇您想要搜尋的範圍')
                                ->setSize(ComponentFontSize::SM)
                                ->setAlign('center')
                                ->setMargin(ComponentMargin::MD),
                            TextComponentBuilder::builder()
                                ->setText('點擊後將要求您分享位置')
                                ->setSize(ComponentFontSize::XXS)
                                ->setAlign('center')
                                ->setColor('#999999')
                                ->setMargin(ComponentMargin::SM),
                            BoxComponentBuilder::builder()
                                ->setLayout(ComponentLayout::VERTICAL)
                                ->setMargin(ComponentMargin::LG)
                                ->setSpacing(ComponentSpacing::SM)
                                ->setContents($buttonComponents)
                        ])));
            
            $response = $this->bot->replyMessage($replyToken, $flexMessageBuilder);
            
            if ($response->isSucceeded()) {
                $this->sendToTelegram("✅ 成功顯示距離選項");
            } else {
                $this->sendToTelegram("❌ 顯示距離選項失敗: " . $response->getRawBody());
            }
            
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ showDistanceOptions 錯誤: " . $e->getMessage());
            
            // 回傳錯誤訊息
            $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                "抱歉，功能暫時無法使用。\n請直接分享您的位置。"
            ));
        }
    }

    /**
     * 要求用戶分享位置（自訂搜尋範圍專用）
     */
    private function requestLocationForCustomSearch($replyToken, $distance)
    {
        try {
            $distanceText = $distance < 1 ? ($distance * 1000) . ' 公尺' : $distance . ' 公里';
            $this->sendToTelegram("🎯 自訂搜尋範圍：要求分享位置，距離 {$distanceText}");
            
            // 建立快速回覆按鈕
            $quickReplyButton = new QuickReplyButtonBuilder(
                new LocationTemplateActionBuilder('分享位置')
            );
            
            // 建立快速回覆訊息
            $quickReply = new QuickReplyMessageBuilder([$quickReplyButton]);
            
            // 建立文字訊息並附加快速回覆
            // 在訊息中加入特殊標記，用於識別自訂搜尋模式
            $message = "🎯 [自訂搜尋:{$distance}km] 已選擇搜尋範圍：{$distanceText}\n\n";
            $message .= "請分享您的位置，我會立即搜尋範圍內的飲料店！\n";
            $message .= "點擊下方的「分享位置」按鈕開始搜尋。\n\n";
            $message .= "⚡ 分享位置後將直接搜尋，不會再詢問距離";
            
            $textMessageBuilder = new TextMessageBuilder($message, $quickReply);
            
            // 儲存距離資訊（使用 Redis 或其他方式）
            // 這裡我們使用訊息內容來標記
            $this->sendToTelegram("🎯 標記自訂搜尋模式，距離：{$distance} km");
            
            // 發送訊息
            $response = $this->bot->replyMessage($replyToken, $textMessageBuilder);
            
            if ($response->isSucceeded()) {
                $this->sendToTelegram("✅ 成功發送自訂搜尋的位置請求");
            } else {
                $this->sendToTelegram("❌ 發送位置請求失敗: " . $response->getRawBody());
            }
            
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ requestLocationForCustomSearch 錯誤: " . $e->getMessage());
            
            // 回傳錯誤訊息
            $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                "抱歉，功能暫時無法使用。\n請直接分享您的位置。"
            ));
        }
    }

    /**
     * 要求用戶分享位置（帶預設距離）
     */
    private function requestLocationWithDistance($replyToken, $distance)
    {
        try {
            $distanceText = $distance < 1 ? ($distance * 1000) . ' 公尺' : $distance . ' 公里';
            $this->sendToTelegram("📍 要求用戶分享位置，預設距離: {$distanceText}");
            
            // 建立快速回覆按鈕
            $quickReplyButton = new QuickReplyButtonBuilder(
                new LocationTemplateActionBuilder('分享位置')
            );
            
            // 建立快速回覆訊息
            $quickReply = new QuickReplyMessageBuilder([$quickReplyButton]);
            
            // 建立文字訊息並附加快速回覆
            $textMessageBuilder = new TextMessageBuilder(
                "🎯 已選擇搜尋範圍：{$distanceText}\n\n" .
                "請分享您的位置，我會幫您找出範圍內的飲料店！\n" .
                "點擊下方的「分享位置」按鈕開始搜尋。\n\n" .
                "💡 提示：分享位置後會直接搜尋 {$distanceText} 內的店家",
                $quickReply
            );
            
            // 由於無法保存狀態，我們將距離資訊放在訊息中讓用戶知道
            
            // 發送訊息
            $response = $this->bot->replyMessage($replyToken, $textMessageBuilder);
            
            if ($response->isSucceeded()) {
                $this->sendToTelegram("✅ 成功發送位置請求（含距離資訊）");
            } else {
                $this->sendToTelegram("❌ 發送位置請求失敗: " . $response->getRawBody());
            }
            
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ requestLocationWithDistance 錯誤: " . $e->getMessage());
            
            // 回傳錯誤訊息
            $this->bot->replyMessage($replyToken, new TextMessageBuilder(
                "抱歉，功能暫時無法使用。\n請直接分享您的位置。"
            ));
        }
    }

    /**
     * 要求用戶分享位置
     */
    private function requestLocation($replyToken, $isQuickSearch = false)
    {
        try {
            $this->sendToTelegram("📍 要求用戶分享位置，快速搜尋模式: " . ($isQuickSearch ? '是' : '否'));
            
            // 建立快速回覆按鈕，根據模式加入不同的資訊
            $actionBuilder = $isQuickSearch 
                ? new PostbackTemplateActionBuilder('分享位置', 'action=location_quick&mode=quick')
                : new LocationTemplateActionBuilder('分享位置');
            
            $quickReplyButton = new QuickReplyButtonBuilder($actionBuilder);
            
            // 如果是快速搜尋，使用特殊的 postback 按鈕來標記
            if ($isQuickSearch) {
                // 對於快速搜尋，我們需要使用 LocationTemplateActionBuilder
                // 但要在訊息中標記這是快速搜尋
                $quickReplyButton = new QuickReplyButtonBuilder(
                    new LocationTemplateActionBuilder('分享位置')
                );
            }
            
            // 建立快速回覆訊息
            $quickReply = new QuickReplyMessageBuilder([$quickReplyButton]);
            
            // 根據模式調整訊息文字
            $message = $isQuickSearch
                ? "📍 快速搜尋模式\n\n" .
                  "請分享您的位置，我會幫您找出 2 公里內的飲料店！\n" .
                  "點擊下方的「分享位置」按鈕開始搜尋。"
                : "📍 請分享您的位置，我會幫您找出附近的飲料店！\n\n" .
                  "點擊下方的「分享位置」按鈕，即可開始搜尋。";
            
            // 建立文字訊息並附加快速回覆
            $textMessageBuilder = new TextMessageBuilder($message, $quickReply);
            
            // 如果是快速搜尋，在 session 或暫存中標記
            if ($isQuickSearch) {
                // 由於 LINE Bot 是無狀態的，我們將在訊息中包含提示
                $this->sendToTelegram("📍 設定快速搜尋模式");
            }
            
            // 發送訊息
            $response = $this->bot->replyMessage($replyToken, $textMessageBuilder);
            
            if ($response->isSucceeded()) {
                $this->sendToTelegram("✅ 成功發送位置請求");
            } else {
                $this->sendToTelegram("❌ 發送位置請求失敗: " . $response->getRawBody());
            }
            
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ requestLocation 錯誤: " . $e->getMessage());
            
            // 回傳錯誤訊息
            $errorMsg = "抱歉，位置功能暫時無法使用。\n";
            $errorMsg .= "請直接分享您的位置訊息。";
            $this->bot->replyMessage($replyToken, new TextMessageBuilder($errorMsg));
        }
    }

    /**
     * 加權隨機選擇店家
     * 距離越近的店家有更高的機率被選中
     */
    private function weightedRandomSelect($shops, $limit = 10)
    {
        if (count($shops) <= $limit) {
            return $shops;
        }
        
        // 計算權重（距離越近，權重越高）
        $distances = array_column($shops, 'distance');
        $maxDistance = max($distances);
        $minDistance = min($distances);
        $range = $maxDistance - $minDistance;
        
        $weightedShops = [];
        
        foreach ($shops as $index => $shop) {
            // 反向權重：距離越近，權重越大
            // 使用指數函數讓近的店家有更明顯的優勢
            $normalizedDistance = ($shop['distance'] - $minDistance) / ($range > 0 ? $range : 1);
            $weight = exp(-2 * $normalizedDistance); // 指數衰減，近的店家權重明顯更高
            
            $weightedShops[] = [
                'index' => $index,
                'weight' => $weight,
                'cumulative' => 0
            ];
        }
        
        // 計算累積權重
        $totalWeight = 0;
        foreach ($weightedShops as &$item) {
            $totalWeight += $item['weight'];
            $item['cumulative'] = $totalWeight;
        }
        
        // 隨機選擇不重複的店家
        $selected = [];
        $selectedIndices = [];
        $attempts = 0;
        $maxAttempts = $limit * 10; // 防止無限循環
        
        while (count($selected) < $limit && $attempts < $maxAttempts) {
            $attempts++;
            $random = (mt_rand() / mt_getrandmax()) * $totalWeight;
            
            foreach ($weightedShops as $item) {
                if ($random <= $item['cumulative'] && !in_array($item['index'], $selectedIndices)) {
                    $selected[] = $shops[$item['index']];
                    $selectedIndices[] = $item['index'];
                    break;
                }
            }
        }
        
        // 如果隨機選擇不足，補充剩餘的店家
        if (count($selected) < $limit) {
            foreach ($shops as $index => $shop) {
                if (!in_array($index, $selectedIndices)) {
                    $selected[] = $shop;
                    if (count($selected) >= $limit) break;
                }
            }
        }
        
        // 保持距離排序，讓顯示更有邏輯性
        usort($selected, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });
        
        return $selected;
    }
    
    /**
     * 顯示附近的飲料店
     */
    private function displayNearbyShops($replyToken, $shops, $searchDistance = 2.0)
    {
        try {
            $this->sendToTelegram("📍 準備顯示 " . count($shops) . " 家店");
            
            if (empty($shops)) {
                $distanceText = $searchDistance < 1 ? ($searchDistance * 1000) . ' 公尺' : $searchDistance . ' 公里';
                $message = "附近 {$distanceText}內沒有找到飲料店 😢\n\n";
                $message .= "您可以試試：\n";
                $message .= "1. 擴大搜尋範圍\n";
                $message .= "2. 移動到其他位置再試\n";
                $message .= "3. 直接輸入店名搜尋";
                $this->bot->replyMessage($replyToken, new TextMessageBuilder($message));
                return;
            }
            
            // 使用加權隨機選擇 10 家（給所有店家曝光機會）
            $totalShops = count($shops);
            $shops = $this->weightedRandomSelect($shops, 10);
            $this->sendToTelegram("📍 從 {$totalShops} 家店中加權隨機選擇了 " . count($shops) . " 家");
            
            // 建立 Carousel 卡片
            $this->sendToTelegram("📍 開始建構 Carousel，共 " . count($shops) . " 家店");
            
            $columns = [];
            
            foreach ($shops as $index => $shop) {
                // 準備距離字串
                $distanceStr = is_numeric($shop['distance']) ? sprintf("%.1f", $shop['distance']) : $shop['distance'];
                
                // 標題（最多40字元）
                $title = mb_substr($shop['shop_name'] . ' ' . $shop['branch_name'], 0, 40);
                
                // 描述文字（最多60字元）
                $text = "📍 {$distanceStr} km\n";
                $text .= mb_substr($shop['address'], 0, 50);
                
                // 建立動作按鈕
                $actions = [];
                
                // 查看菜單按鈕
                if (!empty($shop['shop_code'])) {
                    $postbackData = http_build_query(['action' => 'select', 'shop' => $shop['shop_code']]);
                    $actions[] = new PostbackTemplateActionBuilder('查看菜單', $postbackData);
                }
                
                // 撥打電話按鈕（如果有電話）
                if (!empty($shop['tel'])) {
                    $actions[] = new UriTemplateActionBuilder('撥打電話', 'tel:' . $shop['tel']);
                }
                
                // 如果沒有動作，至少加一個查看詳情
                if (empty($actions)) {
                    $actions[] = new MessageTemplateActionBuilder('查看詳情', $shop['shop_name'] . ' ' . $shop['branch_name']);
                }
                
                // 取得店家圖片（如果有的話）
                $imageUrl = null;
                if (!empty($shop['shop_code'])) {
                    $shopConfig = $this->menuService->getMenuByBrandCode($shop['shop_code']);
                    if ($shopConfig && isset($shopConfig['image_url']) && !empty($shopConfig['image_url'])) {
                        // 驗證是否為有效的 HTTPS URL
                        $url = $shopConfig['image_url'];
                        if (filter_var($url, FILTER_VALIDATE_URL) && 
                            substr($url, 0, 8) === 'https://' && 
                            $url !== 'None') {
                            $imageUrl = $url;
                        }
                    }
                }
                
                // 如果沒有有效的圖片 URL，使用預設圖片
                if (empty($imageUrl)) {
                    // 使用預設的飲料店圖片
                    // CarouselColumnTemplateBuilder 需要有效的 HTTPS URL
                    $imageUrl = url('/images/menus/drink.jpeg');
                    
                    // 確保是 HTTPS
                    if (substr($imageUrl, 0, 7) === 'http://') {
                        $imageUrl = 'https://' . substr($imageUrl, 7);
                    }
                }
                
                // 建立 Carousel Column
                $column = new CarouselColumnTemplateBuilder(
                    $title,      // 標題
                    $text,       // 描述文字
                    $imageUrl,   // 圖片 URL（可選）
                    $actions     // 動作按鈕
                );
                
                $columns[] = $column;
                
                $this->sendToTelegram("📍 建立第 " . ($index + 1) . " 張卡片：{$title}");
            }
            
            // 建立 Carousel Template
            $carouselTemplateBuilder = new CarouselTemplateBuilder($columns);
            $templateMessage = new TemplateMessageBuilder('📍 附近的飲料店', $carouselTemplateBuilder);
            
            $this->sendToTelegram("📍 準備發送 Carousel Message");
            
            $response = $this->bot->replyMessage($replyToken, $templateMessage);
            
            if ($response->isSucceeded()) {
                $this->sendToTelegram("✅ 成功發送附近店家 Carousel");
            } else {
                $this->sendToTelegram("❌ 發送失敗: " . $response->getRawBody());
                throw new \Exception("LINE API 錯誤");
            }
            
        } catch (\Exception $e) {
            $this->sendToTelegram("❌ displayNearbyShops 錯誤: " . $e->getMessage());
            
            // 改用簡單文字訊息
            $message = "📍 附近的飲料店：\n\n";
            $count = 0;
            foreach ($shops as $shop) {
                $count++;
                $distanceStr = is_numeric($shop['distance']) ? sprintf("%.1f", $shop['distance']) : $shop['distance'];
                $message .= "{$count}. {$shop['shop_name']} {$shop['branch_name']}\n";
                $message .= "   📍 {$distanceStr} km\n";
                $message .= "   📮 {$shop['address']}\n";
                if (!empty($shop['tel'])) {
                    $message .= "   📞 {$shop['tel']}\n";
                }
                $message .= "\n";
            }
            
            $this->bot->replyMessage($replyToken, new TextMessageBuilder($message));
        }
    }

}
