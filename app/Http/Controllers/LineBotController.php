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

class LineBotController extends Controller
{
    private $bot;

    public function __construct()
    {
        $httpClient = new CurlHTTPClient(config('line.LINE_CHANNEL_ACCESS_TOKEN'));
        $this->bot  = new LINEBot($httpClient, ['channelSecret' => config('line.LINE_CHANNEL_SECRET')]);
    }

    protected function dd($data)
    {
        $owen_token = config('app.line_owen_token');
        $client     = new Client();
        $headers    = [
            'Authorization' => sprintf('Bearer %s', $owen_token),
            'Content-Type'  => 'application/x-www-form-urlencoded'
        ];
        $options    = [
            'form_params' => [
                'message' => json_encode($data)
            ]
        ];
        $response   = $client->request('POST', 'https://notify-api.line.me/api/notify', [
            'headers'     => $headers,
            'form_params' => $options['form_params']
        ]);
    }

    public function webhook(Request $request)
    {
        try {
            $events = $request->events;

            foreach ($events as $event) {
                if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
                    $userMessage = $event['message']['text'];

                    if ($userMessage == '菜單') {
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
                        $matchingKeys = array_keys(config('menu.shops.drink'), $userMessage);
                        $shopName = $matchingKeys[0];
                        $shop = config("menus.{$shopName}");
//                        $shop = config('beverage_shops.shops')[$userMessage];
                        $this->replyWithShopMenu($event['replyToken'], $shop, $shop['shop_name'] . ' 菜單');
                    }

                } elseif ($event['type'] == 'postback') {
                    $data = $event['postback']['data'];
                    parse_str($data, $postbackData);

                    if ($postbackData['action'] == 'select' && isset($postbackData['shop'])) {
                        $shopName = $postbackData['shop'];
//                        $shop     = config('beverage_shops.shops')[$shopName];
                        $shop = config("menus.{$shopName}");
                        $this->replyWithShopMenu($event['replyToken'], $shop, $shop['shop_name'] . ' 菜單');
                    }
                }
            }
        } catch (\Exception $e) {
            $this->dd($e->getMessage());
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

}
