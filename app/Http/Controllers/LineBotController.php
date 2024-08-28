<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use LINE\LINEBot\TemplateAction\MessageTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\ImageMessageBuilder;

class LineBotController extends Controller
{
    private $bot;

    public function __construct()
    {
        $httpClient = new CurlHTTPClient(env('LINE_CHANNEL_ACCESS_TOKEN'));
        $this->bot = new LINEBot($httpClient, ['channelSecret' => env('LINE_CHANNEL_SECRET')]);
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
                        $actions[] = new MessageTemplateActionBuilder($shopName, $shopName);
                    }

                    $buttonTemplateBuilder = new ButtonTemplateBuilder(
                        '飲料店選單',
                        '請選擇一家飲料店',
                        null, // 可選的圖片URL
                        $actions
                    );

                    $templateMessage = new TemplateMessageBuilder('選擇飲料店', $buttonTemplateBuilder);
                    $this->bot->replyMessage($event['replyToken'], $templateMessage);
                } elseif (isset(config('beverage_shops.shops')[$userMessage])) {
                    $imageUrl = url(config('beverage_shops.shops')[$userMessage]);
                    $imageMessageBuilder = new ImageMessageBuilder($imageUrl, $imageUrl);
                    $this->bot->replyMessage($event['replyToken'], $imageMessageBuilder);
                } else {
                    $responseMessage = "抱歉，我找不到與 '$userMessage' 相關的飲料店。";
                    $textMessageBuilder = new TextMessageBuilder($responseMessage);
                    $this->bot->replyMessage($event['replyToken'], $textMessageBuilder);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}
