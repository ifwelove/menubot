<?php

namespace App\Services;

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use Illuminate\Support\Facades\Log;
use Exception;

class RichMenuService
{
    private $bot;
    private $httpClient;
    
    public function __construct()
    {
        $this->httpClient = new CurlHTTPClient(config('line.LINE_CHANNEL_ACCESS_TOKEN'));
        $this->bot = new LINEBot($this->httpClient, ['channelSecret' => config('line.LINE_CHANNEL_SECRET')]);
    }
    
    /**
     * 創建 Rich Menu
     */
    public function createRichMenu(array $richMenuObject)
    {
        try {
            $response = $this->httpClient->post(
                'https://api.line.me/v2/bot/richmenu',
                $richMenuObject,
                [
                    'Content-Type: application/json; charset=utf-8',
                ]
            );
            
            if ($response->getHTTPStatus() !== 200) {
                throw new Exception('Failed to create rich menu: ' . $response->getRawBody());
            }
            
            $body = json_decode($response->getRawBody(), true);
            return $body['richMenuId'];
            
        } catch (Exception $e) {
            Log::error('Create Rich Menu Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 上傳 Rich Menu 圖片
     */
    public function uploadRichMenuImage(string $richMenuId, string $imagePath)
    {
        try {
            if (!file_exists($imagePath)) {
                throw new Exception("Image file not found: {$imagePath}");
            }
            
            $imageData = file_get_contents($imagePath);
            $contentType = mime_content_type($imagePath);
            
            // 使用 cURL 直接上傳圖片
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api-data.line.me/v2/bot/richmenu/{$richMenuId}/content");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . config('line.LINE_CHANNEL_ACCESS_TOKEN'),
                'Content-Type: ' . $contentType,
                'Content-Length: ' . strlen($imageData)
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new Exception('Failed to upload rich menu image: HTTP ' . $httpCode . ' - ' . $response);
            }
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Upload Rich Menu Image Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 設定預設 Rich Menu
     */
    public function setDefaultRichMenu(string $richMenuId)
    {
        try {
            $response = $this->httpClient->post(
                "https://api.line.me/v2/bot/user/all/richmenu/{$richMenuId}",
                [],
                []
            );
            
            if ($response->getHTTPStatus() !== 200) {
                throw new Exception('Failed to set default rich menu: ' . $response->getRawBody());
            }
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Set Default Rich Menu Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 取得所有 Rich Menu 列表
     */
    public function getRichMenuList()
    {
        try {
            $response = $this->httpClient->get('https://api.line.me/v2/bot/richmenu/list');
            
            if ($response->getHTTPStatus() !== 200) {
                throw new Exception('Failed to get rich menu list: ' . $response->getRawBody());
            }
            
            $body = json_decode($response->getRawBody(), true);
            return $body['richmenus'] ?? [];
            
        } catch (Exception $e) {
            Log::error('Get Rich Menu List Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 刪除 Rich Menu
     */
    public function deleteRichMenu(string $richMenuId)
    {
        try {
            $response = $this->httpClient->delete(
                "https://api.line.me/v2/bot/richmenu/{$richMenuId}"
            );
            
            if ($response->getHTTPStatus() !== 200) {
                throw new Exception('Failed to delete rich menu: ' . $response->getRawBody());
            }
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Delete Rich Menu Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 建立飲料店查詢 Rich Menu 結構
     */
    public function getBeverageShopRichMenuStructure()
    {
        return [
            'size' => [
                'width' => 2500,
                'height' => 843
            ],
            'selected' => false,
            'name' => '飲料店查詢選單',
            'chatBarText' => '選單',
            'areas' => [
                [
                    'bounds' => [
                        'x' => 0,
                        'y' => 0,
                        'width' => 833,
                        'height' => 843
                    ],
                    'action' => [
                        'type' => 'message',
                        'text' => '菜單'
                    ]
                ],
                [
                    'bounds' => [
                        'x' => 833,
                        'y' => 0,
                        'width' => 834,
                        'height' => 843
                    ],
                    'action' => [
                        'type' => 'message',
                        'text' => '喝什麼'
                    ]
                ],
                [
                    'bounds' => [
                        'x' => 1667,
                        'y' => 0,
                        'width' => 833,
                        'height' => 843
                    ],
                    'action' => [
                        'type' => 'message',
                        'text' => '飲料店'
                    ]
                ]
            ]
        ];
    }
}