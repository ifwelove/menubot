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
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
use App\Services\ShopSearchService;

class LineBotController extends Controller
{
    private $bot;
    private $shopSearchService;
    private $tgToken;
    private $tgClient;
    private $tgChatId;

    public function __construct()
    {
        $httpClient = new CurlHTTPClient(config('line.LINE_CHANNEL_ACCESS_TOKEN'));
        $this->bot  = new LINEBot($httpClient, ['channelSecret' => config('line.LINE_CHANNEL_SECRET')]);
        $this->shopSearchService = new ShopSearchService();
        
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
        // 記錄收到的請求
        $this->sendToTelegram("🔵 收到 LINE Webhook 請求\n" . json_encode($request->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        try {
            $events = $request->events;

            foreach ($events as $event) {
                if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
                    $userMessage = $event['message']['text'];
                    
                    // 記錄收到的訊息
                    $this->sendToTelegram("📨 收到訊息: {$userMessage}");

                    if ($userMessage == '使用說明') {
                        $this->showInstructions($event['replyToken']);
                    } elseif ($userMessage == '店家清單') {
                        $this->replyWithShopList($event['replyToken']);
                    } elseif ($userMessage == 'TOP名店') {
                        $this->showTopShops($event['replyToken']);
                    } elseif ($userMessage == '隨機飲料店') {
                        $this->showRandomShop($event['replyToken']);
                    } elseif ($userMessage == '找茶') {
                        $this->showTeaShops($event['replyToken']);
                    } elseif ($userMessage == '奶類') {
                        $this->showMilkShops($event['replyToken']);
                    } elseif ($userMessage == '飲料標籤') {
                        $this->showShopTags($event['replyToken']);
                    } elseif ($userMessage == '菜單') {
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
                            $shop = config("menus.{$key}");
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
                        $shop = config("menus.{$shopName}");
                        $this->replyWithShopMenu($event['replyToken'], $shop, $shop['shop_name'] . ' 菜單');
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
                            $shop = $this->shopSearchService->getShopInfo($shopCode);
                            if ($shop) {
                                $this->replyWithShopMenu($event['replyToken'], $shop, $shop['shop_name'] . ' 菜單');
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

                } elseif ($event['type'] == 'postback') {
                    $data = $event['postback']['data'];
                    parse_str($data, $postbackData);
                    
                    // 記錄 postback 事件
                    $this->sendToTelegram("🔘 收到 Postback: " . json_encode($postbackData, JSON_UNESCAPED_UNICODE));

                    try {
                        if ($postbackData['action'] == 'select' && isset($postbackData['shop'])) {
                            $shopName = $postbackData['shop'];
                            $shop = config("menus.{$shopName}");
                            $this->replyWithShopMenu($event['replyToken'], $shop, $shop['shop_name'] . ' 菜單');
                        } elseif ($postbackData['action'] == 'instructions') {
                            // 顯示使用說明
                            $message = "📖 使用說明\n\n";
                            $message .= "🔸 使用說明：查看功能介紹\n";
                            $message .= "🔸 店家清單：瀏覽所有飲料店\n";
                            $message .= "🔸 TOP名店：查看熱門推薦店家\n";
                            $message .= "🔸 隨機飲料店：讓系統推薦一家店\n";
                            $message .= "🔸 找茶：茶類專門店\n";
                            $message .= "🔸 奶類：鮮奶茶專門店\n\n";
                            $message .= "💡 也可以直接輸入店名或關鍵字搜尋！";
                            $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder($message));
                        } elseif ($postbackData['action'] == 'random') {
                            // 執行「喝什麼」功能
                            $this->showRandomShop($event['replyToken']);
                        } elseif ($postbackData['action'] == 'shoplist') {
                            // 執行「飲料店」功能
                            $this->replyWithShopList($event['replyToken']);
                        } elseif ($postbackData['action'] == 'tags') {
                            // 執行「飲料標籤」功能
                            $this->showShopTags($event['replyToken']);
                        } elseif ($postbackData['action'] == 'aliases') {
                            // 執行「飲料店別名」功能
                            $this->showShopAliases($event['replyToken']);
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
            $shop = config("menus.{$key}");
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
        $coldEmoji = "\u{2744}\u{FE0F}"; // ❄️ 雪花
        $hotEmoji  = "\u{1F525}"; // 🔥 火焰

        // 创建饮料项目组件
        $itemComponents = [];
        foreach ($shop['menu_items'] as $category => $detail) {
            $itemComponents[] = TextComponentBuilder::builder()
                ->setText($category)
                ->setWeight('bold')
                ->setSize('md');

            foreach ($detail as $item) {
                $priceText = ' ';
                if (! empty($item['price_cold'])) {
                    $priceText .= $item['price_cold'] . $coldEmoji;
                }
                if (! empty($item['price_hot'])) {
                    if (! empty($priceText)) {
                        $priceText .= ''; // 分隔冷热价格
                    }
                    $priceText .= $item['price_hot'] . $hotEmoji;
                }
                if (! empty($item['price'])) {
                    if (! empty($priceText)) {
                        $priceText .= ''; // 分隔冷热价格
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
        $this->bot->replyMessage($replyToken, $flexMessageBuilder);
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
                $shop = config("menus.{$shopCode}");
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
        $this->sendToTelegram("📍 進入 showInstructions 方法");
        
        try {
            // 使用 Flex Message 支援5個選項
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
        ];

        // 在按鈕之間加入間距
        $components = [];
        foreach ($buttonComponents as $index => $button) {
            $components[] = $button;
            if ($index < count($buttonComponents) - 1) {
                $components[] = BoxComponentBuilder::builder()
                    ->setLayout(ComponentLayout::VERTICAL)
                    ->setHeight('8px');
            }
        }

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
            $this->bot->replyMessage($replyToken, $flexMessageBuilder);
            $this->sendToTelegram("✅ showInstructions 完成");
            
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

        foreach ($selectedShops as $shopCode => $shopName) {
            $shop = config("menus.{$shopCode}");
            if (!$shop) {
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
        }
    }

    /**
     * 顯示隨機一家店
     */
    private function showRandomShop($replyToken)
    {
        $shops = config('menu.shops.drink');

        if (empty($shops)) {
            $this->bot->replyMessage($replyToken, new TextMessageBuilder('目前沒有飲料店資訊'));
            return;
        }

        // 隨機選一家店
        $randomKey = array_rand($shops);
        $shop = config("menus.{$randomKey}");

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

        foreach ($shops as $shopCode => $shopName) {
            $shop = config("menus.{$shopCode}");
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
            $templateMessage = new TemplateMessageBuilder($title, $carouselTemplateBuilder);
            $this->bot->replyMessage($replyToken, $templateMessage);
        }
    }

    /**
     * 顯示所有飲料標籤
     */
    private function showShopTags($replyToken)
    {
        $tags = config('shop_tags');

        $message = "🏷️ 飲料店分類標籤\n";
        $message .= "請輸入以下標籤查看相關店家：\n\n";

        foreach (array_keys($tags) as $tag) {
            if ($tag !== 'TOP熱門店家') {
                $message .= "• {$tag}\n";
            }
        }

        $this->bot->replyMessage($replyToken, new TextMessageBuilder($message));
    }

    /**
     * 顯示飲料店別名
     */
    private function showShopAliases($replyToken)
    {
        try {
            $keywords = config('shop_keywords', []);
            $shops = config('menu.shops.drink', []);

            // 選擇一些常用的店家來顯示別名（確保這些品牌都存在於 menu.php）
            $popularShops = [
                '50lantea', 'chingshin', 'truedan', 'comebuytea',
                'kungfutea', 'threepercent', 'herotang', 'teatop',
                'sharetea', 'dayungs', 'mrwish', '85cafe'
            ];

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

}
