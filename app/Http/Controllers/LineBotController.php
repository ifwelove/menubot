<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
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

class LineBotController extends Controller
{
    private $bot;

    public function __construct()
    {
        $httpClient = new CurlHTTPClient(config('line.LINE_CHANNEL_ACCESS_TOKEN'));
        $this->bot = new LINEBot($httpClient, ['channelSecret' => config('line.LINE_CHANNEL_SECRET')]);
    }

    protected function dd($data)
    {
        $owen_token = config('app.line_owen_token');
        $client   = new Client();
        $headers  = [
            'Authorization' => sprintf('Bearer %s', $owen_token),
            'Content-Type'  => 'application/x-www-form-urlencoded'
        ];
        $options  = [
            'form_params' => [
                'message' => json_encode($data)
            ]
        ];
        $response = $client->request('POST', 'https://notify-api.line.me/api/notify', [
            'headers'     => $headers,
            'form_params' => $options['form_params']
        ]);
    }
    public function webhook(Request $request)
    {
        $events = $request->events;

        foreach ($events as $event) {
            if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
                $userMessage = $event['message']['text'];

                if ($userMessage == '店') {
                    $shops = config('beverage_shops.shops');

                    if (empty($shops)) {
                        $this->bot->replyMessage($event['replyToken'], new TextMessageBuilder('抱歉，目前沒有可用的飲料店資訊。'));
                        return;
                    }

                    $actions = [];
                    foreach (array_keys($shops) as $shopName) {
                        $actions[] = new PostbackTemplateActionBuilder($shopName, "action=select&shop={$shopName}");
                    }

                    // 添加一个随机选择的选项
                    $randomShopKey = array_rand($shops);
                    $actions[] = new PostbackTemplateActionBuilder('隨機選擇', "action=select&shop={$randomShopKey}");

                    $buttonTemplateBuilder = new ButtonTemplateBuilder(
                        '飲料店選單',
                        '請選擇一家飲料店',
                        null,
                        $actions
                    );

                    $templateMessage = new TemplateMessageBuilder('選擇飲料店', $buttonTemplateBuilder);
                    $this->bot->replyMessage($event['replyToken'], $templateMessage);
                } elseif (isset(config('beverage_shops.shops')[$userMessage])) {
                    $shop = config('beverage_shops.shops')[$userMessage];
                    $this->replyWithShopMenu($event['replyToken'], $shop, $userMessage . ' 菜單');
                }
            } elseif ($event['type'] == 'postback') {
                $data = $event['postback']['data'];
                parse_str($data, $postbackData);

                if ($postbackData['action'] == 'select' && isset($postbackData['shop'])) {
                    $shopName = $postbackData['shop'];
                    $shop = config('beverage_shops.shops')[$shopName];
                    $this->replyWithShopMenu($event['replyToken'], $shop, $shopName . ' 菜單');
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    private function replyWithShopMenu($replyToken, $shop, $title)
    {
        $imageUrl = $shop['image_url'];
        $items = $shop['items'];

        $itemComponents = [];
        foreach ($items as $item) {
            $itemComponents[] = new BoxComponentBuilder('baseline', [
                new TextComponentBuilder($item['name'], null, 'sm', 'bold', 2),
                new TextComponentBuilder($item['price'], null, 'sm', null, 1, 'end'),
            ]);
        }

        $bubble = new BubbleContainerBuilder(
            null,
            new ImageComponentBuilder($imageUrl, null, 'full', '20:13', 'cover'),
            new BoxComponentBuilder('vertical', array_merge(
                [new TextComponentBuilder($title, null, 'xl', 'bold')],
                $itemComponents
            ))
        );

        $flexMessageBuilder = new FlexMessageBuilder('飲料店菜單', $bubble);
        $this->bot->replyMessage($replyToken, $flexMessageBuilder);
    }
}
