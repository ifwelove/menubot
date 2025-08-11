<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RichMenuService;
use Exception;

class UploadRichMenuImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linebot:richmenu:upload 
                            {richMenuId : The Rich Menu ID}
                            {imagePath : Path to the image file}
                            {--set-default : Set as default rich menu after upload}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '上傳圖片到已存在的 Rich Menu';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $richMenuId = $this->argument('richMenuId');
        $imagePath = $this->argument('imagePath');
        
        if (!file_exists($imagePath)) {
            $this->error("找不到圖片檔案: {$imagePath}");
            return Command::FAILURE;
        }
        
        $this->info("正在上傳圖片到 Rich Menu: {$richMenuId}");
        
        try {
            $richMenuService = new RichMenuService();
            
            // 上傳圖片
            $richMenuService->uploadRichMenuImage($richMenuId, $imagePath);
            $this->info('圖片上傳成功！');
            
            // 是否設為預設
            if ($this->option('set-default') || $this->confirm('是否要將此 Rich Menu 設為預設？')) {
                $this->info('正在設定為預設 Rich Menu...');
                $richMenuService->setDefaultRichMenu($richMenuId);
                $this->info('已成功設定為預設 Rich Menu！');
            }
            
            $this->info('');
            $this->info('Rich Menu 已準備就緒！用戶現在可以在 LINE 聊天室中看到圖形化選單。');
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('上傳失敗：' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}