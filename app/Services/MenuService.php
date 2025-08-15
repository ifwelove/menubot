<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MenuService
{
    protected $jsonMenuPath;
    protected $phpMenuPath;
    protected $indexPath;
    protected $index = null;
    protected $beverageShopsCache = null;
    
    public function __construct()
    {
        $this->jsonMenuPath = base_path('menus');
        $this->phpMenuPath = config_path('menus');
        $this->indexPath = base_path('menus/_index.json');
        $this->loadIndex();
    }
    
    /**
     * 載入索引檔案
     */
    protected function loadIndex()
    {
        if (File::exists($this->indexPath)) {
            try {
                $content = File::get($this->indexPath);
                $this->index = json_decode($content, true);
            } catch (\Exception $e) {
                Log::error("Failed to load menu index: " . $e->getMessage());
            }
        }
    }
    
    /**
     * 載入 beverage_shops.php 資料
     */
    protected function loadBeverageShops()
    {
        if ($this->beverageShopsCache === null) {
            try {
                $beverageShops = config('beverage_shops.shops', []);
                $this->beverageShopsCache = $beverageShops;
            } catch (\Exception $e) {
                Log::error("Failed to load beverage shops: " . $e->getMessage());
                $this->beverageShopsCache = [];
            }
        }
        return $this->beverageShopsCache;
    }
    
    /**
     * 從 beverage_shops.php 載入特定店家的菜單
     */
    protected function loadBeverageShopMenu($shopName)
    {
        $beverageShops = $this->loadBeverageShops();
        
        if (isset($beverageShops[$shopName])) {
            $shopData = $beverageShops[$shopName];
            
            // 轉換為標準菜單格式
            return [
                'brand_code' => $this->getBrandCodeByShopName($shopName),
                'shop_name' => $shopName,
                'store_id' => null,
                'store_name' => $shopName,
                'image_url' => $shopData['image_url'] ?? '',
                'website_url' => $shopData['website_url'] ?? '',
                'menu_version' => 'beverage_shops',
                'menu_items' => $shopData['items'] ?? []
            ];
        }
        
        return null;
    }
    
    /**
     * 根據店名獲取品牌代碼
     */
    protected function getBrandCodeByShopName($shopName)
    {
        // 建立店名到品牌代碼的對應
        $shopNameToBrandCode = [
            '50嵐' => '50lantea',
            'CoCo都可' => 'cocotea',
            '迷客夏' => 'milkshoptea',
            '清心福全' => 'chingshin',
            '老虎堂' => 'tigersugar',
            '珍煮丹' => 'truedan',
            'COMEBUY' => 'comebuytea',
            '天仁喫茶趣ToGo' => 'chaforteatogo',
            '茶湯會' => 'teatop',
            '日出茶太' => 'chatime',
            // 可以根據需要加入更多對應關係
        ];
        
        return $shopNameToBrandCode[$shopName] ?? strtolower(str_replace(' ', '', $shopName));
    }
    
    /**
     * 根據品牌代碼獲取菜單
     * 優先順序：beverage_shops.php → JSON 菜單 → PHP 菜單
     */
    public function getMenuByBrandCode($brandCode)
    {
        $cacheKey = "menu_brand_{$brandCode}";
        
        return Cache::store('file')->remember($cacheKey, 3600, function () use ($brandCode) {
            // 首先嘗試根據品牌代碼找到對應的店名
            $brandCodeToShopName = [
                '50lantea' => '50嵐',
                'cocotea' => 'CoCo都可',
                'milkshoptea' => '迷客夏',
                'chingshin' => '清心福全',
                'tigersugar' => '老虎堂',
                'truedan' => '珍煮丹',
                'comebuytea' => 'COMEBUY',
                'chaforteatogo' => '天仁喫茶趣ToGo',
                'teatop' => '茶湯會',
                'chatime' => '日出茶太',
                // 可以根據需要加入更多對應關係
            ];
            
            // 如果有對應的店名，嘗試從 beverage_shops.php 載入
            if (isset($brandCodeToShopName[$brandCode])) {
                $shopName = $brandCodeToShopName[$brandCode];
                $beverageShopMenu = $this->loadBeverageShopMenu($shopName);
                if ($beverageShopMenu) {
                    Log::info("Loading menu from beverage_shops.php for: {$shopName}");
                    return $beverageShopMenu;
                }
            }
            
            // 其次嘗試從 JSON 載入
            $jsonMenu = $this->loadJsonMenuByBrandCode($brandCode);
            if ($jsonMenu) {
                return $this->formatJsonMenu($jsonMenu);
            }
            
            // 最後使用原有的 PHP 菜單
            return $this->loadPhpMenu($brandCode);
        });
    }
    
    /**
     * 根據店鋪 ID 獲取菜單
     */
    public function getMenuByStoreId($storeId)
    {
        $cacheKey = "menu_store_{$storeId}";
        
        return Cache::store('file')->remember($cacheKey, 3600, function () use ($storeId) {
            $jsonPath = "{$this->jsonMenuPath}/{$storeId}.json";
            
            if (File::exists($jsonPath)) {
                try {
                    $content = File::get($jsonPath);
                    $menu = json_decode($content, true);
                    
                    if ($menu && isset($menu['menu_items'])) {
                        return $this->formatJsonMenu($menu);
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to load JSON menu for store {$storeId}: " . $e->getMessage());
                }
            }
            
            return null;
        });
    }
    
    /**
     * 載入 JSON 菜單（根據品牌代碼）
     */
    protected function loadJsonMenuByBrandCode($brandCode)
    {
        // 優先使用索引
        if ($this->index && isset($this->index['brand_to_stores'][$brandCode])) {
            $storeIds = $this->index['brand_to_stores'][$brandCode];
            
            // 只讀取第一個店鋪的菜單
            if (!empty($storeIds)) {
                $storeId = $storeIds[0];
                $jsonPath = "{$this->jsonMenuPath}/{$storeId}.json";
                
                if (File::exists($jsonPath)) {
                    try {
                        $content = File::get($jsonPath);
                        return json_decode($content, true);
                    } catch (\Exception $e) {
                        Log::error("Failed to load JSON menu for store {$storeId}: " . $e->getMessage());
                    }
                }
            }
        }
        
        // 索引不存在或找不到時，使用舊方法（但效率較低）
        Log::warning("Index not found or brand not in index: {$brandCode}, falling back to scan method");
        
        $jsonFiles = File::glob("{$this->jsonMenuPath}/*.json");
        
        foreach ($jsonFiles as $file) {
            // 跳過索引檔案
            if (str_contains($file, '_index.json')) {
                continue;
            }
            
            try {
                $content = File::get($file);
                $menu = json_decode($content, true);
                
                if ($menu && isset($menu['brand_code']) && $menu['brand_code'] === $brandCode) {
                    return $menu;
                }
            } catch (\Exception $e) {
                Log::error("Failed to parse JSON file {$file}: " . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * 載入 PHP 菜單
     */
    protected function loadPhpMenu($brandCode)
    {
        $phpPath = "{$this->phpMenuPath}/{$brandCode}.php";
        
        if (File::exists($phpPath)) {
            try {
                return include $phpPath;
            } catch (\Exception $e) {
                Log::error("Failed to load PHP menu for {$brandCode}: " . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * 格式化 JSON 菜單為統一格式
     */
    protected function formatJsonMenu($jsonMenu)
    {
        $brandCode = $jsonMenu['brand_code'] ?? '';
        $brandImage = $jsonMenu['brand_image'] ?? '';
        
        // 如果 JSON 沒有品牌圖片，嘗試從 PHP 檔案讀取
        if (empty($brandImage) && !empty($brandCode)) {
            $phpMenu = $this->loadPhpMenu($brandCode);
            if ($phpMenu && isset($phpMenu['image_url'])) {
                $brandImage = $phpMenu['image_url'];
            }
        }
        
        $formatted = [
            'brand_code' => $brandCode,
            'shop_name' => $jsonMenu['brand_name'] ?? $jsonMenu['store_name'] ?? '',
            'store_id' => $jsonMenu['store_id'] ?? null,
            'store_name' => $jsonMenu['store_name'] ?? '',
            'image_url' => $brandImage,
            'menu_version' => $jsonMenu['menu_version'] ?? null,
            'menu_items' => []
        ];
        
        // 轉換菜單項目格式
        if (isset($jsonMenu['menu_items']) && is_array($jsonMenu['menu_items'])) {
            foreach ($jsonMenu['menu_items'] as $category) {
                $categoryName = $category['category'] ?? '其他';
                $formatted['menu_items'][$categoryName] = [];
                
                if (isset($category['items']) && is_array($category['items'])) {
                    foreach ($category['items'] as $item) {
                        $formatted['menu_items'][$categoryName][] = [
                            'name' => $item['name'] ?? '',
                            'description' => $item['description'] ?? '',
                            'price_cold' => $this->formatPrice($item['price_cold'] ?? null),
                            'price_hot' => $this->formatPrice($item['price_hot'] ?? null),
                            'image_url' => $item['image'] ?? ''
                        ];
                    }
                }
            }
        }
        
        return $formatted;
    }
    
    /**
     * 格式化價格
     */
    protected function formatPrice($price)
    {
        if ($price === null || $price === '') {
            return '';
        }
        
        return (string) $price;
    }
    
    /**
     * 獲取所有可用的品牌列表
     */
    public function getAllBrands()
    {
        // 優先使用索引
        if ($this->index && isset($this->index['brand_info'])) {
            $brands = [];
            foreach ($this->index['brand_info'] as $code => $info) {
                $brands[$code] = [
                    'code' => $code,
                    'name' => $info['name'],
                    'has_json' => true,
                    'stores' => []
                ];
                
                // 從索引中獲取店鋪資訊
                if (isset($this->index['brand_to_stores'][$code])) {
                    foreach ($this->index['brand_to_stores'][$code] as $storeId) {
                        if (isset($this->index['store_info'][$storeId])) {
                            $storeInfo = $this->index['store_info'][$storeId];
                            $brands[$code]['stores'][] = [
                                'id' => $storeId,
                                'name' => $storeInfo['store_name'] ?? ''
                            ];
                        }
                    }
                }
            }
            
            // 補充 PHP 菜單
            $phpFiles = File::glob("{$this->phpMenuPath}/*.php");
            foreach ($phpFiles as $file) {
                $brandCode = basename($file, '.php');
                if (!isset($brands[$brandCode])) {
                    try {
                        $menu = include $file;
                        $brands[$brandCode] = [
                            'code' => $brandCode,
                            'name' => $menu['shop_name'] ?? '',
                            'has_json' => false,
                            'has_php' => true,
                            'stores' => []
                        ];
                    } catch (\Exception $e) {
                        Log::error("Failed to load PHP file {$file}: " . $e->getMessage());
                    }
                } else {
                    $brands[$brandCode]['has_php'] = true;
                }
            }
            
            return $brands;
        }
        
        // 索引不存在時使用舊方法
        Log::warning("Menu index not available, using fallback method for getAllBrands");
        
        $brands = [];
        
        // 從 JSON 檔案收集品牌
        $jsonFiles = File::glob("{$this->jsonMenuPath}/*.json");
        foreach ($jsonFiles as $file) {
            try {
                $content = File::get($file);
                $menu = json_decode($content, true);
                
                if ($menu && isset($menu['brand_code'])) {
                    $brandCode = $menu['brand_code'];
                    if (!isset($brands[$brandCode])) {
                        $brands[$brandCode] = [
                            'code' => $brandCode,
                            'name' => $menu['brand_name'] ?? '',
                            'has_json' => true,
                            'stores' => []
                        ];
                    }
                    
                    if (isset($menu['store_id'])) {
                        $brands[$brandCode]['stores'][] = [
                            'id' => $menu['store_id'],
                            'name' => $menu['store_name'] ?? ''
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to parse JSON file {$file}: " . $e->getMessage());
            }
        }
        
        // 從 PHP 檔案收集品牌
        $phpFiles = File::glob("{$this->phpMenuPath}/*.php");
        foreach ($phpFiles as $file) {
            $brandCode = basename($file, '.php');
            if (!isset($brands[$brandCode])) {
                try {
                    $menu = include $file;
                    $brands[$brandCode] = [
                        'code' => $brandCode,
                        'name' => $menu['shop_name'] ?? '',
                        'has_json' => false,
                        'has_php' => true,
                        'stores' => []
                    ];
                } catch (\Exception $e) {
                    Log::error("Failed to load PHP file {$file}: " . $e->getMessage());
                }
            } else {
                $brands[$brandCode]['has_php'] = true;
            }
        }
        
        return $brands;
    }
    
    /**
     * 搜尋菜單項目
     */
    public function searchMenuItem($keyword, $brandCode = null)
    {
        $cacheKey = "search_" . md5($keyword . '_' . ($brandCode ?? 'all'));
        
        return Cache::store('file')->remember($cacheKey, 1800, function () use ($keyword, $brandCode) {
            $results = [];
            $keyword = mb_strtolower($keyword);
            
            if ($brandCode) {
                $menu = $this->getMenuByBrandCode($brandCode);
                if ($menu) {
                    $results = $this->searchInMenu($menu, $keyword);
                }
            } else {
                // 搜尋所有品牌
                $brands = $this->getAllBrands();
                foreach ($brands as $brand) {
                    $menu = $this->getMenuByBrandCode($brand['code']);
                    if ($menu) {
                        $brandResults = $this->searchInMenu($menu, $keyword);
                        foreach ($brandResults as $result) {
                            $result['brand_name'] = $menu['shop_name'];
                            $result['brand_code'] = $brand['code'];
                            $results[] = $result;
                        }
                    }
                }
            }
            
            return $results;
        });
    }
    
    /**
     * 在菜單中搜尋項目
     */
    protected function searchInMenu($menu, $keyword)
    {
        $results = [];
        
        if (isset($menu['menu_items'])) {
            foreach ($menu['menu_items'] as $category => $items) {
                foreach ($items as $item) {
                    $itemName = mb_strtolower($item['name'] ?? '');
                    $description = mb_strtolower($item['description'] ?? '');
                    
                    if (strpos($itemName, $keyword) !== false || strpos($description, $keyword) !== false) {
                        $item['category'] = $category;
                        $results[] = $item;
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * 清除所有菜單快取
     */
    public function clearCache()
    {
        Cache::store('file')->flush();
        Log::info('Menu cache cleared');
    }
    
    /**
     * 重新載入索引
     */
    public function reloadIndex()
    {
        $this->index = null;
        $this->loadIndex();
        $this->clearCache();
        Log::info('Menu index reloaded');
    }
    
    /**
     * 獲取效能統計
     */
    public function getPerformanceStats()
    {
        $stats = [
            'index_loaded' => !is_null($this->index),
            'index_size' => 0,
            'total_brands' => 0,
            'total_stores' => 0,
            'cache_driver' => config('cache.default')
        ];
        
        if ($this->index) {
            $stats['total_brands'] = count($this->index['brand_to_stores'] ?? []);
            $stats['total_stores'] = count($this->index['store_info'] ?? []);
            
            if (File::exists($this->indexPath)) {
                $stats['index_size'] = filesize($this->indexPath);
            }
        }
        
        return $stats;
    }
}