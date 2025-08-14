<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BuildMenuIndex extends Command
{
    protected $signature = 'menu:build-index {--verify : 驗證索引的正確性}';
    
    protected $description = '建立菜單索引檔案，加速菜單查詢效能';
    
    protected $menuPath;
    protected $indexPath;
    
    public function __construct()
    {
        parent::__construct();
        $this->menuPath = base_path('menus');
        $this->indexPath = base_path('menus/_index.json');
    }
    
    public function handle()
    {
        $startTime = microtime(true);
        
        if ($this->option('verify')) {
            $this->verifyIndex();
            return;
        }
        
        $this->info('開始建立菜單索引...');
        
        // 取得所有 JSON 檔案
        $jsonFiles = File::glob("{$this->menuPath}/*.json");
        
        // 排除索引檔案本身
        $jsonFiles = array_filter($jsonFiles, function($file) {
            return !str_contains($file, '_index.json');
        });
        
        $this->info('找到 ' . count($jsonFiles) . ' 個菜單檔案');
        
        $brandToStores = [];
        $storeInfo = [];
        $brandInfo = [];
        $errors = [];
        
        $bar = $this->output->createProgressBar(count($jsonFiles));
        $bar->start();
        
        foreach ($jsonFiles as $file) {
            $bar->advance();
            
            try {
                $content = File::get($file);
                $menu = json_decode($content, true);
                
                if (!$menu) {
                    $errors[] = "無法解析 JSON: " . basename($file);
                    continue;
                }
                
                $storeId = $menu['store_id'] ?? null;
                $brandCode = $menu['brand_code'] ?? null;
                $brandName = $menu['brand_name'] ?? null;
                $storeName = $menu['store_name'] ?? null;
                
                if (!$storeId || !$brandCode) {
                    $errors[] = "缺少必要欄位: " . basename($file);
                    continue;
                }
                
                // 建立品牌到店鋪的映射
                if (!isset($brandToStores[$brandCode])) {
                    $brandToStores[$brandCode] = [];
                }
                $brandToStores[$brandCode][] = $storeId;
                
                // 儲存店鋪資訊
                $storeInfo[$storeId] = [
                    'brand_code' => $brandCode,
                    'brand_name' => $brandName,
                    'store_name' => $storeName,
                    'file' => basename($file),
                    'file_size' => filesize($file),
                    'menu_items_count' => $this->countMenuItems($menu)
                ];
                
                // 儲存品牌資訊
                if (!isset($brandInfo[$brandCode])) {
                    $brandInfo[$brandCode] = [
                        'name' => $brandName,
                        'store_count' => 0
                    ];
                }
                $brandInfo[$brandCode]['store_count']++;
                
            } catch (\Exception $e) {
                $errors[] = "處理檔案 " . basename($file) . " 時發生錯誤: " . $e->getMessage();
            }
        }
        
        $bar->finish();
        $this->newLine();
        
        // 排序品牌內的店鋪
        foreach ($brandToStores as &$stores) {
            sort($stores);
        }
        
        // 建立索引結構
        $index = [
            'version' => '1.0',
            'generated_at' => date('Y-m-d H:i:s'),
            'stats' => [
                'total_brands' => count($brandToStores),
                'total_stores' => count($storeInfo),
                'total_files' => count($jsonFiles),
                'errors' => count($errors)
            ],
            'brand_to_stores' => $brandToStores,
            'store_info' => $storeInfo,
            'brand_info' => $brandInfo
        ];
        
        // 寫入索引檔案
        $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        File::put($this->indexPath, $json);
        
        $elapsed = round(microtime(true) - $startTime, 2);
        
        $this->info("✅ 索引建立完成！");
        $this->info("📊 統計資訊：");
        $this->info("   - 品牌數量: " . count($brandToStores));
        $this->info("   - 店鋪數量: " . count($storeInfo));
        $this->info("   - 索引大小: " . $this->formatBytes(strlen($json)));
        $this->info("   - 建立時間: {$elapsed} 秒");
        
        if (!empty($errors)) {
            $this->newLine();
            $this->warn("⚠️  發現 " . count($errors) . " 個錯誤：");
            foreach (array_slice($errors, 0, 5) as $error) {
                $this->error("   - {$error}");
            }
            if (count($errors) > 5) {
                $this->error("   ... 還有 " . (count($errors) - 5) . " 個錯誤");
            }
        }
        
        $this->newLine();
        $this->comment("💡 提示：使用 --verify 選項驗證索引正確性");
    }
    
    protected function verifyIndex()
    {
        if (!File::exists($this->indexPath)) {
            $this->error("索引檔案不存在，請先執行 php artisan menu:build-index");
            return;
        }
        
        $this->info("驗證索引檔案...");
        
        $index = json_decode(File::get($this->indexPath), true);
        
        if (!$index) {
            $this->error("無法讀取索引檔案");
            return;
        }
        
        $this->info("索引版本: " . ($index['version'] ?? 'unknown'));
        $this->info("建立時間: " . ($index['generated_at'] ?? 'unknown'));
        $this->newLine();
        
        // 隨機抽樣驗證
        $sampleSize = min(10, count($index['store_info'] ?? []));
        $samples = array_rand($index['store_info'] ?? [], $sampleSize);
        
        if (!is_array($samples)) {
            $samples = [$samples];
        }
        
        $this->info("隨機抽樣驗證 {$sampleSize} 個店鋪...");
        
        $valid = 0;
        $invalid = 0;
        
        foreach ($samples as $storeId) {
            $storeData = $index['store_info'][$storeId];
            $filePath = "{$this->menuPath}/{$storeData['file']}";
            
            if (File::exists($filePath)) {
                $this->line("✓ {$storeId}: {$storeData['store_name']} - 檔案存在");
                $valid++;
            } else {
                $this->error("✗ {$storeId}: {$storeData['store_name']} - 檔案不存在");
                $invalid++;
            }
        }
        
        $this->newLine();
        $this->info("驗證結果: {$valid} 個有效, {$invalid} 個無效");
    }
    
    protected function countMenuItems($menu)
    {
        $count = 0;
        
        if (isset($menu['menu_items']) && is_array($menu['menu_items'])) {
            foreach ($menu['menu_items'] as $category) {
                if (isset($category['items']) && is_array($category['items'])) {
                    $count += count($category['items']);
                }
            }
        }
        
        return $count;
    }
    
    protected function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}