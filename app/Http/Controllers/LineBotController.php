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
                        $shops = config('beverage_shops.shops');

                        if (empty($shops)) {
                            $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder('抱歉，目前沒有可用的飲料店資訊。'));
                            return;
                        }

                        $actions = [];
                        foreach (array_keys($shops) as $shopName) {
                            //                            $actions[] = new PostbackTemplateActionBuilder($shopName, "action=select&shop={$shopName}");
                        }

                        // 添加一个随机选择的选项
                        $randomShopKey = array_rand($shops);
                        $actions[]     = new PostbackTemplateActionBuilder('正餐', "action=select&shop={$randomShopKey}");
                        $actions[]     = new PostbackTemplateActionBuilder('下午茶', "action=select&shop={$randomShopKey}");
                        $actions[]     = new PostbackTemplateActionBuilder('飲料', "action=select&shop={$randomShopKey}");

                        $buttonTemplateBuilder = new ButtonTemplateBuilder('選單', '請選擇一個項目', null, $actions);

                        $templateMessage = new TemplateMessageBuilder('選擇', $buttonTemplateBuilder);
                        $this->bot->replyMessage($event['replyToken'], $templateMessage);
                    } elseif ($userMessage == '喝什麼') {
                        $shops = config('beverage_shops.shops');

                        if (empty($shops)) {
                            $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder('抱歉，目前沒有可用的飲料店資訊。'));
                            return;
                        }

                        // 随机选择最多5个店铺
                        $randomKeys = (count($shops) > 5) ? array_rand($shops, 5) : array_keys($shops);
                        shuffle($randomKeys); // 随机排序选出的店铺键
                        $columns = [];
                        foreach ($randomKeys as $key) {
                            $shopName     = $key; // 使用 $key 作为店铺名称
                            $postbackData = http_build_query(['action' => 'select', 'shop' => $shopName]);
                            $action       = new PostbackTemplateActionBuilder($shopName, $postbackData);

                            $column    = new CarouselColumnTemplateBuilder($shopName, // title
                                '選擇您的飲料', // text
                                $shops[$key]['image_url'], // image url (optional)
                                [$action] // actions
                            );
                            $columns[] = $column;
                        }

                        $carouselTemplateBuilder = new CarouselTemplateBuilder($columns);
                        $templateMessage         = new TemplateMessageBuilder('選擇飲料店', $carouselTemplateBuilder);
                        $this->bot->replyMessage($event['replyToken'], $templateMessage);

                    } elseif ($userMessage == '飲料店') {
                        $this->replyWithShopList($event['replyToken']);
                    } elseif (isset(config('beverage_shops.shops')[$userMessage])) {
                        $shop = config('beverage_shops.shops')[$userMessage];
                        $this->replyWithShopMenu($event['replyToken'], $shop, $userMessage . ' 菜單');
                    }

                } elseif ($event['type'] == 'postback') {
                    $data = $event['postback']['data'];
                    parse_str($data, $postbackData);

                    if ($postbackData['action'] == 'select' && isset($postbackData['shop'])) {
                        $shopName = $postbackData['shop'];
                        $shop     = config('beverage_shops.shops')[$shopName];
                        $this->replyWithShopMenu($event['replyToken'], $shop, $shopName . ' 菜單');
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
        $shops = config('beverage_shops.shops');

        if (empty($shops)) {
            $this->bot->replyMessage($replyToken, new TextMessageBuilder('抱歉，目前沒有可用的飲料店資訊。'));
            return;
        }

        // 构建所有饮料店信息的 Flex Components
        $shopComponents = [];
        foreach ($shops as $shopName => $shopInfo) {
            $buttonAction     = new PostbackTemplateActionBuilder('查看菜单', "action=select&shop={$shopName}");
            $shopComponents[] = BoxComponentBuilder::builder()
                ->setLayout('baseline')
                ->setContents([
                    TextComponentBuilder::builder()
                        ->setAction($buttonAction)
                        ->setText($shopName)
                        ->setSize('sm')
                        ->setFlex(4),
                ]);
        }

        // 创建 Flex Message
        $flexMessageBuilder = FlexMessageBuilder::builder()
            ->setAltText('飲料店列表') // 设置备用文字
            ->setContents(BubbleContainerBuilder::builder()
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
        foreach ($shop['items'] as $category => $detail) {
            $itemComponents[] = TextComponentBuilder::builder()
                ->setText($category)
                ->setWeight('bold')
                ->setSize('md');

            foreach ($detail['items'] as $item) {
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
                            ->setAction(new UriTemplateActionBuilder('你訂', $shop['website_url']))
                        // 设置按钮动作链接
                    ])));

        // 使用LINE Bot实例发送消息
        $this->bot->replyMessage($replyToken, $flexMessageBuilder);
    }

}
