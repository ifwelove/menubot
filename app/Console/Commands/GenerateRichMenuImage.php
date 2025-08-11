<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateRichMenuImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linebot:richmenu:generate-image 
                            {--bg-color=E3F2FD : Background color in hex (without #)}
                            {--text-color=1976D2 : Text color in hex (without #)}
                            {--font-size=60 : Font size for text}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成預設的 Rich Menu 圖片';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('正在生成 Rich Menu 圖片...');
        
        // 圖片規格
        $width = 2500;
        $height = 843;
        
        // 取得選項
        $bgColorHex = $this->option('bg-color');
        $textColorHex = $this->option('text-color');
        $fontSize = (int) $this->option('font-size');
        
        // 轉換顏色
        $bgColor = $this->hexToRgb($bgColorHex);
        $textColor = $this->hexToRgb($textColorHex);
        
        // 創建圖片
        $image = imagecreatetruecolor($width, $height);
        
        // 分配顏色
        $bgColorAlloc = imagecolorallocate($image, $bgColor['r'], $bgColor['g'], $bgColor['b']);
        $textColorAlloc = imagecolorallocate($image, $textColor['r'], $textColor['g'], $textColor['b']);
        $borderColor = imagecolorallocate($image, 200, 200, 200);
        $whiteColor = imagecolorallocate($image, 255, 255, 255);
        
        // 填充背景
        imagefilledrectangle($image, 0, 0, $width, $height, $bgColorAlloc);
        
        // 區域定義
        $areas = [
            ['text' => '菜單', 'emoji' => '📋', 'x' => 0, 'width' => 833],
            ['text' => '喝什麼', 'emoji' => '🥤', 'x' => 833, 'width' => 834],
            ['text' => '飲料店', 'emoji' => '🏪', 'x' => 1667, 'width' => 833]
        ];
        
        // 字體路徑（使用系統字體或內建字體）
        $fontPath = $this->findFont();
        
        foreach ($areas as $index => $area) {
            $centerX = $area['x'] + ($area['width'] / 2);
            
            // 繪製區域背景（稍微深一點的顏色）
            $areaColor = imagecolorallocate($image, 
                max(0, $bgColor['r'] - 20), 
                max(0, $bgColor['g'] - 20), 
                max(0, $bgColor['b'] - 20)
            );
            
            // 創建圓角矩形效果
            $margin = 20;
            $rectX1 = $area['x'] + $margin;
            $rectX2 = $area['x'] + $area['width'] - $margin;
            $rectY1 = $margin;
            $rectY2 = $height - $margin;
            
            // 繪製主要區域
            imagefilledrectangle($image, $rectX1, $rectY1, $rectX2, $rectY2, $whiteColor);
            
            // 繪製 emoji（使用較大的內建字體代替）
            $emojiSize = 100;
            $emojiY = $height / 2 - 80;
            
            if ($fontPath) {
                // 使用 TrueType 字體
                imagettftext($image, $emojiSize, 0, 
                    $centerX - 50, $emojiY, 
                    $textColorAlloc, $fontPath, $area['emoji']);
                
                // 繪製文字
                $textBounds = imagettfbbox($fontSize, 0, $fontPath, $area['text']);
                $textWidth = abs($textBounds[4] - $textBounds[0]);
                $textX = $centerX - ($textWidth / 2);
                $textY = $height / 2 + 80;
                
                imagettftext($image, $fontSize, 0, $textX, $textY, 
                    $textColorAlloc, $fontPath, $area['text']);
            } else {
                // 使用內建字體
                $emojiText = $index === 0 ? '[MENU]' : ($index === 1 ? '[DRINK]' : '[SHOP]');
                $emojiX = $centerX - (strlen($emojiText) * 10);
                imagestring($image, 5, $emojiX, $emojiY, $emojiText, $textColorAlloc);
                
                // 繪製文字
                $textX = $centerX - (strlen($area['text']) * 15);
                imagestring($image, 5, $textX, $height / 2 + 50, $area['text'], $textColorAlloc);
            }
            
            // 繪製分隔線
            if ($index < count($areas) - 1) {
                $lineX = $area['x'] + $area['width'];
                imageline($image, $lineX, 50, $lineX, $height - 50, $borderColor);
            }
        }
        
        // 繪製外框
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);
        
        // 確保目錄存在
        $outputDir = resource_path('images');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // 儲存圖片
        $outputPath = $outputDir . '/rich-menu.png';
        imagepng($image, $outputPath);
        imagedestroy($image);
        
        $this->info("Rich Menu 圖片已生成：{$outputPath}");
        $this->info('');
        $this->info('圖片規格：');
        $this->info("- 尺寸：{$width} x {$height} 像素");
        $this->info("- 背景顏色：#{$bgColorHex}");
        $this->info("- 文字顏色：#{$textColorHex}");
        $this->info('');
        $this->info('您現在可以執行以下命令來創建 Rich Menu：');
        $this->info('php artisan linebot:richmenu:create');
        
        return Command::SUCCESS;
    }
    
    /**
     * 將 hex 顏色轉換為 RGB
     */
    private function hexToRgb($hex)
    {
        $hex = str_replace('#', '', $hex);
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
    
    /**
     * 尋找可用的字體
     */
    private function findFont()
    {
        // macOS 字體路徑
        $fonts = [
            '/System/Library/Fonts/PingFang.ttc',  // 蘋方字體（支援中文）
            '/Library/Fonts/Arial Unicode.ttf',     // Arial Unicode（支援多語言）
            '/System/Library/Fonts/Helvetica.ttc',  // Helvetica
        ];
        
        // Linux 字體路徑
        $fonts = array_merge($fonts, [
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ]);
        
        foreach ($fonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }
        
        // 如果找不到字體，返回 null（將使用內建字體）
        return null;
    }
}