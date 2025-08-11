# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 專案概述

這是一個基於 Laravel 8 框架的 LINE Bot 應用程式，專門提供台灣飲料店的菜單查詢服務。Bot 支援 232 家飲料店的菜單資訊，使用者可以透過 LINE 聊天介面快速查詢各家飲料店的完整菜單。

### 主要功能
- **菜單查詢**: 輸入飲料店名稱即可獲得該店的完整菜單
- **隨機推薦**: 輸入「喝什麼」獲得隨機飲料店推薦
- **店家列表**: 輸入「飲料店」查看所有支援的飲料店列表
- **主選單**: 輸入「菜單」顯示功能選單

## 開發環境設置

### 初始設置
```bash
# 安裝 PHP 相依套件
composer install

# 複製環境設定檔
cp .env.example .env

# 生成應用程式金鑰
php artisan key:generate

# 安裝前端相依套件
npm install
```

### 必要的環境變數
在 `.env` 檔案中設定以下 LINE Bot 相關變數：
```
LINE_CHANNEL_ACCESS_TOKEN=你的_LINE_Channel_Access_Token
LINE_CHANNEL_SECRET=你的_LINE_Channel_Secret
```

## 常用開發命令

### Laravel 相關
```bash
# 啟動開發伺服器
php artisan serve

# 執行測試
php artisan test

# 清除快取
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### 前端資源編譯
```bash
# 開發模式編譯
npm run dev

# 監聽檔案變更並自動編譯
npm run watch

# 生產模式編譯（壓縮）
npm run production
```

## 專案架構

### 核心檔案結構
- `/app/Http/Controllers/LineBotController.php` - LINE Bot 主要邏輯控制器
- `/routes/web.php` - 路由設定（包含 webhook 端點）
- `/config/line.php` - LINE Bot 設定檔
- `/config/menu.php` - 飲料店列表（232家店）
- `/config/menus/` - 各飲料店的詳細菜單資料
- `/public/images/menus/` - 飲料店菜單圖片

### LineBotController 主要方法
- `webhook()` - 處理 LINE Bot webhook 請求
- `sendShopMenu()` - 發送特定飲料店的菜單
- `randomShop()` - 隨機推薦飲料店
- `showBeverageShops()` - 顯示飲料店列表
- `showMainMenu()` - 顯示主選單

### 飲料店菜單資料結構
每個飲料店的菜單檔案（如 `/config/menus/50lantea.php`）包含：
```php
return [
    'name' => '店名',
    'image' => '菜單圖片路徑',
    'categories' => [
        '分類名稱' => [
            ['name' => '飲品名稱', 'cold' => '冷飲價格', 'hot' => '熱飲價格'],
            // ...
        ]
    ]
];
```

## LINE Bot 開發重點

### Webhook 處理流程
1. 接收 LINE 平台的 webhook 請求
2. 驗證請求簽章
3. 解析訊息內容
4. 根據關鍵字執行對應動作
5. 使用 Flex Message 回覆豐富的互動內容

### Flex Message 建構
專案使用 LINE Bot SDK 的 Flex Message Builder 來建立美觀的訊息介面：
- `BubbleContainerBuilder` - 建立氣泡容器
- `BoxComponentBuilder` - 建立區塊佈局
- `TextComponentBuilder` - 建立文字元件
- `ButtonComponentBuilder` - 建立按鈕元件

### 新增飲料店
1. 在 `/config/menu.php` 新增店家資訊
2. 在 `/config/menus/` 建立對應的菜單檔案
3. 將菜單圖片放置於 `/public/images/menus/`

## Rich Menu 管理

### Rich Menu 功能說明
專案包含 Rich Menu 管理功能，讓用戶可以透過圖形化選單快速操作：

**第一排按鈕**：
- **使用說明** - 顯示功能選單（包含子選單）
- **店家清單** - 顯示所有飲料店列表
- **TOP名店** - 顯示熱門推薦店家

**第二排按鈕**：
- **隨機飲料店** - 隨機推薦一家飲料店
- **找茶** - 顯示茶類專門店
- **奶類** - 顯示鮮奶茶專門店

### 新增功能
- **飲料標籤系統**：用戶可輸入「飲料標籤」查看所有分類，或直接輸入標籤名稱（如「黑糖系列」、「水果茶」）查看相關店家
- **關鍵字搜尋**：支援部分店名或別名搜尋（如「50」找到50嵐）
- **使用說明子選單**：包含使用說明、喝什麼、飲料店、飲料標籤四個選項

### Rich Menu 管理命令
```bash
# 創建 Rich Menu
php artisan linebot:richmenu:create
# 可選參數：--image=/path/to/image.png

# 列出所有 Rich Menu
php artisan linebot:richmenu:list

# 刪除 Rich Menu
php artisan linebot:richmenu:delete {richMenuId}
# 刪除所有：--all
```

### Rich Menu 圖片規格
- 尺寸：2500 x 1686 像素（6格設計，2行3列）
- 格式：PNG 或 JPEG
- 檔案大小：最大 1MB
- 預設路徑：`resources/images/rich-menu.png`

### 相關檔案
- `/app/Services/RichMenuService.php` - Rich Menu API 服務
- `/app/Console/Commands/CreateRichMenu.php` - 創建命令
- `/app/Console/Commands/DeleteRichMenu.php` - 刪除命令
- `/app/Console/Commands/ListRichMenus.php` - 列表命令
- `/resources/images/README.md` - 圖片規格說明

## 部署注意事項
- 專案包含 `Procfile`，可部署至 Heroku 或類似平台
- 確保 webhook URL 已在 LINE Developers Console 設定
- 生產環境需設定正確的 `APP_URL`
- 部署後記得執行 Rich Menu 創建命令