<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MenuService;

class TestMenuService extends Command
{
    protected $signature = 'menu:test {brand_code?} {--store-id=}';
    
    protected $description = '測試菜單服務，顯示 JSON 和 PHP 菜單載入狀況';
    
    protected $menuService;
    
    public function __construct()
    {
        parent::__construct();
        $this->menuService = new MenuService();
    }
    
    public function handle()
    {
        $brandCode = $this->argument('brand_code');
        $storeId = $this->option('store-id');
        
        if ($storeId) {
            $this->testStoreMenu($storeId);
        } elseif ($brandCode) {
            $this->testBrandMenu($brandCode);
        } else {
            $this->listAllBrands();
        }
    }
    
    protected function testBrandMenu($brandCode)
    {
        $this->info("測試品牌菜單: {$brandCode}");
        
        $menu = $this->menuService->getMenuByBrandCode($brandCode);
        
        if ($menu) {
            $this->info("✓ 成功載入菜單");
            $this->info("品牌名稱: " . ($menu['shop_name'] ?? 'N/A'));
            $this->info("品牌代碼: " . ($menu['brand_code'] ?? 'N/A'));
            
            if (isset($menu['store_id'])) {
                $this->info("店鋪 ID: " . $menu['store_id']);
                $this->info("店鋪名稱: " . ($menu['store_name'] ?? 'N/A'));
                $this->comment("(從 JSON 載入)");
            } else {
                $this->comment("(從 PHP 載入)");
            }
            
            if (isset($menu['menu_version'])) {
                $this->info("菜單版本: " . $menu['menu_version']);
            }
            
            $this->info("");
            $this->info("菜單分類:");
            
            if (isset($menu['menu_items']) && is_array($menu['menu_items'])) {
                foreach ($menu['menu_items'] as $category => $items) {
                    $this->info("- {$category}: " . count($items) . " 項");
                    
                    // 顯示前3個項目作為範例
                    $sample = array_slice($items, 0, 3);
                    foreach ($sample as $item) {
                        $price = [];
                        if (!empty($item['price_cold'])) {
                            $price[] = "冷 $" . $item['price_cold'];
                        }
                        if (!empty($item['price_hot'])) {
                            $price[] = "熱 $" . $item['price_hot'];
                        }
                        $priceStr = implode(', ', $price) ?: '無價格';
                        
                        $this->line("  • " . $item['name'] . " ({$priceStr})");
                        
                        if (!empty($item['description'])) {
                            $this->line("    " . $item['description']);
                        }
                    }
                    
                    if (count($items) > 3) {
                        $this->line("  ... 還有 " . (count($items) - 3) . " 項");
                    }
                }
            }
        } else {
            $this->error("✗ 無法載入菜單");
        }
    }
    
    protected function testStoreMenu($storeId)
    {
        $this->info("測試店鋪菜單: {$storeId}");
        
        $menu = $this->menuService->getMenuByStoreId($storeId);
        
        if ($menu) {
            $this->info("✓ 成功載入菜單");
            $this->testBrandMenu($menu['brand_code'] ?? '');
        } else {
            $this->error("✗ 無法載入店鋪 {$storeId} 的菜單");
        }
    }
    
    protected function listAllBrands()
    {
        $this->info("列出所有可用品牌:");
        $this->info("");
        
        $brands = $this->menuService->getAllBrands();
        
        $jsonBrands = [];
        $phpBrands = [];
        $bothBrands = [];
        
        foreach ($brands as $code => $brand) {
            if (isset($brand['has_json']) && $brand['has_json'] && isset($brand['has_php']) && $brand['has_php']) {
                $bothBrands[$code] = $brand;
            } elseif (isset($brand['has_json']) && $brand['has_json']) {
                $jsonBrands[$code] = $brand;
            } else {
                $phpBrands[$code] = $brand;
            }
        }
        
        if (!empty($jsonBrands)) {
            $this->info("【JSON 菜單】(" . count($jsonBrands) . " 個)");
            foreach ($jsonBrands as $code => $brand) {
                $storeCount = count($brand['stores'] ?? []);
                $storeInfo = $storeCount > 0 ? " ({$storeCount} 家分店)" : "";
                $this->line("  - {$code}: {$brand['name']}{$storeInfo}");
            }
            $this->info("");
        }
        
        if (!empty($phpBrands)) {
            $this->info("【PHP 菜單】(" . count($phpBrands) . " 個)");
            foreach ($phpBrands as $code => $brand) {
                $this->line("  - {$code}: {$brand['name']}");
            }
            $this->info("");
        }
        
        if (!empty($bothBrands)) {
            $this->info("【同時有 JSON 和 PHP】(" . count($bothBrands) . " 個)");
            foreach ($bothBrands as $code => $brand) {
                $storeCount = count($brand['stores'] ?? []);
                $storeInfo = $storeCount > 0 ? " ({$storeCount} 家分店)" : "";
                $this->line("  - {$code}: {$brand['name']}{$storeInfo}");
            }
            $this->info("");
        }
        
        $this->info("總計: " . count($brands) . " 個品牌");
        $this->info("");
        $this->comment("使用方式:");
        $this->comment("  php artisan menu:test <brand_code>     - 測試特定品牌菜單");
        $this->comment("  php artisan menu:test --store-id=<id>  - 測試特定店鋪菜單");
    }
}