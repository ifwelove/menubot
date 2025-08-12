<?php

namespace App\Services;

use Illuminate\Support\Str;

class ShopSearchService
{
    /**
     * 搜尋飲料店
     * 
     * @param string $keyword 搜尋關鍵字
     * @return array 找到的店家資訊 ['shop_code' => 'shop_name']
     */
    public function search(string $keyword)
    {
        try {
            $keyword = trim($keyword);
            $keyword = Str::lower($keyword);
            
            // 載入配置並檢查是否存在
            $shops = config('menu.shops.drink', []);
            $keywords = config('shop_keywords', []);
            
            $results = [];
            
            // 1. 先檢查是否完全匹配店名
            foreach ($shops as $code => $name) {
                if (Str::lower($name) === $keyword) {
                    return [$code => $name];
                }
            }
            
            // 2. 檢查關鍵字配置
            foreach ($keywords as $shopCode => $shopKeywords) {
                // 確保 shopKeywords 是陣列
                if (!is_array($shopKeywords)) {
                    continue;
                }
                
                foreach ($shopKeywords as $shopKeyword) {
                    // 確保 shopKeyword 是字串
                    if (!is_string($shopKeyword)) {
                        continue;
                    }
                    
                    if (Str::lower($shopKeyword) === $keyword || 
                        Str::contains(Str::lower($shopKeyword), $keyword) ||
                        Str::contains($keyword, Str::lower($shopKeyword))) {
                        
                        // 確認這個店家代碼存在於店家列表中
                        if (isset($shops[$shopCode])) {
                            $results[$shopCode] = $shops[$shopCode];
                            break; // 找到就跳出內層迴圈
                        }
                    }
                }
            }
            
            // 3. 如果關鍵字沒找到，進行模糊搜尋店名
            if (empty($results)) {
                foreach ($shops as $code => $name) {
                    if (Str::contains(Str::lower($name), $keyword)) {
                        $results[$code] = $name;
                    }
                }
            }
            
            return $results;
        } catch (\Exception $e) {
            // 發生錯誤時返回空陣列
            \Log::error('ShopSearchService::search error', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [];
        }
    }
    
    /**
     * 檢查店家是否存在
     * 
     * @param string $shopCode 店家代碼
     * @return bool
     */
    public function shopExists(string $shopCode)
    {
        $shops = config('menu.shops.drink', []);
        return isset($shops[$shopCode]);
    }
    
    /**
     * 取得店家資訊
     * 
     * @param string $shopCode 店家代碼
     * @return array|null
     */
    public function getShopInfo(string $shopCode)
    {
        if (!$this->shopExists($shopCode)) {
            return null;
        }
        
        $shop = config("menus.{$shopCode}");
        if (!$shop) {
            return null;
        }
        
        return $shop;
    }
    
    /**
     * 取得建議的搜尋關鍵字
     * 
     * @return array
     */
    public function getSuggestedKeywords()
    {
        return [
            '50嵐', '清心', '珍煮丹', '茶湯會', '功夫茶',
            '黑糖', '鮮奶', '水果茶', '珍珠', '仙草',
            '85度C', '萬波', '貢茶', '大苑子', '歇腳亭'
        ];
    }
}