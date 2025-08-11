<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RichMenuService;
use Exception;

class ListRichMenus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linebot:richmenu:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '列出所有 LINE Bot Rich Menu';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $richMenuService = new RichMenuService();
            
            $this->info('正在取得 Rich Menu 列表...');
            $richMenus = $richMenuService->getRichMenuList();
            
            if (empty($richMenus)) {
                $this->info('沒有找到任何 Rich Menu');
                return Command::SUCCESS;
            }
            
            $this->info('');
            $this->info('現有的 Rich Menu：');
            $this->info('');
            
            $headers = ['ID', '名稱', '尺寸', '聊天欄文字', '區域數量', '是否選中'];
            $rows = [];
            
            foreach ($richMenus as $menu) {
                $rows[] = [
                    $menu['richMenuId'],
                    $menu['name'],
                    "{$menu['size']['width']} x {$menu['size']['height']}",
                    $menu['chatBarText'],
                    count($menu['areas']),
                    $menu['selected'] ? '是' : '否'
                ];
            }
            
            $this->table($headers, $rows);
            
            $this->info('');
            $this->info('總共 ' . count($richMenus) . ' 個 Rich Menu');
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('取得 Rich Menu 列表失敗：' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}