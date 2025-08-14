<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MenuService
{
    protected $jsonMenuPath;
    protected $phpMenuPath;
    
    public function __construct()
    {
        $this->jsonMenuPath = base_path('menus');
        $this->phpMenuPath = config_path('menus');
    }
    
    /**
     * 根據品牌代碼獲取菜單
     * 優先使用 JSON 菜單，找不到時使用 PHP 菜單
     */
    public function getMenuByBrandCode($brandCode)
    {
        // 先嘗試從 JSON 載入
        $jsonMenu = $this->loadJsonMenuByBrandCode($brandCode);
        if ($jsonMenu) {
            return $this->formatJsonMenu($jsonMenu);
        }
        
        // 找不到 JSON 時，使用原有的 PHP 菜單
        return $this->loadPhpMenu($brandCode);
    }
    
    /**
     * 根據店鋪 ID 獲取菜單
     */
    public function getMenuByStoreId($storeId)
    {
        $cacheKey = "menu_store_{$storeId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($storeId) {
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
        // 獲取所有 JSON 檔案
        $jsonFiles = File::glob("{$this->jsonMenuPath}/*.json");
        
        foreach ($jsonFiles as $file) {
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
        $formatted = [
            'brand_code' => $jsonMenu['brand_code'] ?? '',
            'shop_name' => $jsonMenu['brand_name'] ?? $jsonMenu['store_name'] ?? '',
            'store_id' => $jsonMenu['store_id'] ?? null,
            'store_name' => $jsonMenu['store_name'] ?? '',
            'image_url' => $jsonMenu['brand_image'] ?? '',
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
}