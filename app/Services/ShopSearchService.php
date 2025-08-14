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
    
    /**
     * 取得 Nidin 平台的店家資料
     * 
     * @return array 店家資料陣列，key 為品牌代碼，value 為該品牌的所有分店資料
     */
    public function getNidinShops()
    {
        // 使用快取來提升效能
        $cacheKey = 'nidin_shops_data';
        $cacheDuration = 3600; // 快取 1 小時
        
        // 嘗試從快取取得
        $cachedData = \Cache::get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }
        
        $nidinData = [];
        $storesPath = base_path('stores');
        
        // 檢查 stores 目錄是否存在
        if (!is_dir($storesPath)) {
            \Log::warning('Stores directory not found: ' . $storesPath);
            return $nidinData;
        }
        
        // 取得所有 JSON 檔案
        $files = glob($storesPath . '/*.json');
        
        foreach ($files as $file) {
            $shopCode = basename($file, '.json');
            
            // 跳過索引檔案
            if ($shopCode === '_index') {
                continue;
            }
            
            try {
                $content = file_get_contents($file);
                $data = json_decode($content, true);
                
                // 檢查 JSON 解析是否成功
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('JSON decode error: ' . json_last_error_msg());
                }
                
                // 確認資料結構包含 stores 陣列
                if (isset($data['stores']) && is_array($data['stores'])) {
                    $nidinData[$shopCode] = $data['stores'];
                } else {
                    \Log::warning("Invalid store data structure for {$shopCode}");
                }
                
            } catch (\Exception $e) {
                \Log::error("Failed to load store data for {$shopCode}", [
                    'file' => $file,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // 儲存到快取
        \Cache::put($cacheKey, $nidinData, $cacheDuration);
        
        \Log::info('Loaded Nidin shops data', [
            'total_brands' => count($nidinData),
            'total_stores' => array_sum(array_map('count', $nidinData))
        ]);
        
        return $nidinData;
    }
}