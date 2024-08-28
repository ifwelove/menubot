<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use LINE\LINEBot\MessageBuilder\ImageMessageBuilder;

use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;


class LineBotController extends Controller
{
    private $bot;

    public function __construct()
    {
        $httpClient = new CurlHTTPClient(config('line.LINE_CHANNEL_ACCESS_TOKEN'));
        $this->bot = new LINEBot($httpClient, ['channelSecret' => config('line.LINE_CHANNEL_SECRET')]);
    }

    public function webhook(Request $request)
    {
        $events = $request->events;

        foreach ($events as $event) {
            if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
                $userMessage = $event['message']['text'];

                if ($userMessage == '店') {
                    $shops = config('beverage_shops.shops');

                    $actions = [];
                    foreach (array_keys($shops) as $shopName) {
                        $actions[] = new PostbackTemplateActionBuilder($shopName, "action=select&shop={$shopName}");
                    }

                    $buttonTemplateBuilder = new ButtonTemplateBuilder(
                        '飲料店選單',
                        '請選擇一家飲料店',
                        null, // 可选的图片URL
                        $actions
                    );

                    $templateMessage = new TemplateMessageBuilder('選擇飲料店', $buttonTemplateBuilder);
                    $this->bot->replyMessage($event['replyToken'], $templateMessage);
                } elseif (isset(config('beverage_shops.shops')[$userMessage])) {
                    $imageUrl = url(config('beverage_shops.shops')[$userMessage]);
                    $imageMessageBuilder = new ImageMessageBuilder($imageUrl, $imageUrl);
                    $this->bot->replyMessage($event['replyToken'], $imageMessageBuilder);
                } else {
//                    $responseMessage = "抱歉，我找不到與 " . $userMessage . " 相關的飲料店。";
//                    $textMessageBuilder = new TextMessageBuilder($responseMessage);
//                    $this->bot->replyMessage($event['replyToken'], $textMessageBuilder);
                }
            } elseif ($event['type'] == 'postback') {
                // 处理 postback 事件
                $data = $event['postback']['data']; // 获取 postback 的数据
                parse_str($data, $postbackData);

                if ($postbackData['action'] == 'select' && isset($postbackData['shop'])) {
                    $shopName = $postbackData['shop'];
                    $imageUrl = url(config('beverage_shops.shops')[$shopName]);
                    $imageMessageBuilder = new ImageMessageBuilder($imageUrl, $imageUrl);
                    $this->bot->replyMessage($event['replyToken'], $imageMessageBuilder);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}
