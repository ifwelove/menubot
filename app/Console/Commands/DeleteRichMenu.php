<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RichMenuService;
use Exception;

class DeleteRichMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linebot:richmenu:delete 
                            {richMenuId? : The Rich Menu ID to delete}
                            {--all : Delete all Rich Menus}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '刪除 LINE Bot Rich Menu';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $richMenuService = new RichMenuService();
            
            if ($this->option('all')) {
                // 刪除所有 Rich Menu
                if (!$this->confirm('確定要刪除所有 Rich Menu 嗎？')) {
                    $this->info('操作已取消');
                    return Command::SUCCESS;
                }
                
                $this->info('正在取得 Rich Menu 列表...');
                $richMenus = $richMenuService->getRichMenuList();
                
                if (empty($richMenus)) {
                    $this->info('沒有找到任何 Rich Menu');
                    return Command::SUCCESS;
                }
                
                foreach ($richMenus as $menu) {
                    $this->info("正在刪除 Rich Menu: {$menu['richMenuId']} ({$menu['name']})");
                    $richMenuService->deleteRichMenu($menu['richMenuId']);
                }
                
                $this->info('所有 Rich Menu 已刪除');
                
            } else {
                // 刪除指定的 Rich Menu
                $richMenuId = $this->argument('richMenuId');
                
                if (!$richMenuId) {
                    // 顯示列表供選擇
                    $this->info('正在取得 Rich Menu 列表...');
                    $richMenus = $richMenuService->getRichMenuList();
                    
                    if (empty($richMenus)) {
                        $this->info('沒有找到任何 Rich Menu');
                        return Command::SUCCESS;
                    }
                    
                    $this->info('現有的 Rich Menu：');
                    $choices = [];
                    foreach ($richMenus as $menu) {
                        $choice = "{$menu['richMenuId']} - {$menu['name']}";
                        $choices[] = $choice;
                        $this->info($choice);
                    }
                    
                    $selected = $this->choice('請選擇要刪除的 Rich Menu', $choices);
                    $richMenuId = explode(' - ', $selected)[0];
                }
                
                if ($this->confirm("確定要刪除 Rich Menu {$richMenuId} 嗎？")) {
                    $this->info('正在刪除 Rich Menu...');
                    $richMenuService->deleteRichMenu($richMenuId);
                    $this->info('Rich Menu 已成功刪除');
                } else {
                    $this->info('操作已取消');
                }
            }
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('刪除 Rich Menu 失敗：' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}