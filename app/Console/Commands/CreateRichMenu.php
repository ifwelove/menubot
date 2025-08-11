<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RichMenuService;
use Exception;

class CreateRichMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linebot:richmenu:create 
                            {--image= : Path to the rich menu image (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '創建 LINE Bot Rich Menu';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('開始創建 Rich Menu...');
        
        try {
            $richMenuService = new RichMenuService();
            
            // 使用預設的飲料店查詢 Rich Menu 結構
            $richMenuStructure = $richMenuService->getBeverageShopRichMenuStructure();
            
            // 創建 Rich Menu
            $this->info('正在創建 Rich Menu...');
            $richMenuId = $richMenuService->createRichMenu($richMenuStructure);
            $this->info("Rich Menu 創建成功！ID: {$richMenuId}");
            
            // 上傳圖片
            $imagePath = $this->option('image') ?: resource_path('images/rich-menu.png');
            
            if (file_exists($imagePath)) {
                $this->info('正在上傳 Rich Menu 圖片...');
                $richMenuService->uploadRichMenuImage($richMenuId, $imagePath);
                $this->info('圖片上傳成功！');
            } else {
                $this->warn("找不到圖片檔案: {$imagePath}");
                $this->warn('請手動上傳圖片或使用 --image 參數指定圖片路徑');
            }
            
            // 詢問是否要設為預設
            if ($this->confirm('是否要將此 Rich Menu 設為預設？')) {
                $this->info('正在設定為預設 Rich Menu...');
                $richMenuService->setDefaultRichMenu($richMenuId);
                $this->info('已成功設定為預設 Rich Menu！');
            }
            
            $this->info('Rich Menu 創建完成！');
            $this->info('');
            $this->info('Rich Menu 結構：');
            $this->info('- 菜單：顯示主選單');
            $this->info('- 喝什麼：隨機推薦飲料店');
            $this->info('- 飲料店：顯示飲料店列表');
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('創建 Rich Menu 失敗：' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}