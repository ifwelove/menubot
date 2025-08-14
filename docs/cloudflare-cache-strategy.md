# Cloudflare 免費快取方案整合指南

## 目錄
1. [概述](#概述)
2. [Cloudflare Workers KV 方案](#cloudflare-workers-kv-方案)
3. [Cloudflare Cache API 方案](#cloudflare-cache-api-方案)  
4. [混合架構方案](#混合架構方案)
5. [實作步驟](#實作步驟)
6. [效能評估](#效能評估)

## 概述

本文件說明如何使用 Cloudflare 的免費服務來加速 MenuBot 的菜單查詢，不需要 Redis 或額外的資料庫。

### 現有系統架構
```
用戶 → LINE Bot → Laravel → File Cache → JSON/PHP 檔案
```

### 優化後架構
```
用戶 → LINE Bot → Cloudflare Workers → KV Cache
                           ↓ (cache miss)
                        Laravel → File Cache
```

## Cloudflare Workers KV 方案

### 免費額度
- 100,000 次讀取/天（足夠處理約 10 萬次查詢）
- 1,000 次寫入/天（足夠每日更新快取）
- 1GB 儲存空間（可存約 2000 個完整菜單）

### 架構設計

#### 1. KV 命名空間設計
```javascript
// 品牌菜單
MENU_CACHE: {
  "brand:cocotea": {...},
  "brand:50lantea": {...},
  "brand:chingshin": {...}
}

// 索引資料
INDEX_CACHE: {
  "index:main": {...},
  "index:brands": [...]
}

// 搜尋結果快取
SEARCH_CACHE: {
  "search:珍珠": [...],
  "search:奶茶": [...]
}
```

#### 2. Workers 腳本範例
```javascript
// workers.js
export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    
    // 處理菜單查詢
    if (url.pathname.startsWith('/api/menu/')) {
      const brandCode = url.pathname.split('/')[3];
      const cacheKey = `brand:${brandCode}`;
      
      // 嘗試從 KV 讀取
      const cached = await env.MENU_CACHE.get(cacheKey);
      if (cached) {
        return new Response(cached, {
          headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'public, max-age=3600',
            'X-Cache': 'HIT'
          }
        });
      }
      
      // Cache miss，轉發到源站
      const response = await fetch(request);
      const data = await response.text();
      
      // 存入 KV（TTL 1天）
      await env.MENU_CACHE.put(cacheKey, data, {
        expirationTtl: 86400
      });
      
      return new Response(data, {
        headers: {
          'Content-Type': 'application/json',
          'X-Cache': 'MISS'
        }
      });
    }
    
    // 其他請求直接轉發
    return fetch(request);
  }
};
```

## Cloudflare Cache API 方案

### 使用 Page Rules
1. 在 Cloudflare Dashboard 設定 Page Rules
2. 規則範例：
   ```
   URL: example.com/api/menu/*
   設定: Cache Level: Cache Everything
        Edge Cache TTL: 1 hour
   ```

### Laravel 端配置
```php
// app/Http/Middleware/CacheHeaders.php
public function handle($request, Closure $next)
{
    $response = $next($request);
    
    if ($request->is('api/menu/*')) {
        $response->header('Cache-Control', 'public, max-age=3600');
        $response->header('Surrogate-Control', 'max-age=86400');
    }
    
    return $response;
}
```

## 混合架構方案

### 最佳實踐：KV + Cache API + Laravel File Cache

```javascript
// workers-hybrid.js
export default {
  async fetch(request, env, ctx) {
    const cache = caches.default;
    const cacheKey = new Request(request.url, request);
    
    // 1. 檢查 Cache API
    let response = await cache.match(cacheKey);
    if (response) {
      return response;
    }
    
    // 2. 檢查 KV
    const url = new URL(request.url);
    if (url.pathname.startsWith('/api/menu/')) {
      const brandCode = url.pathname.split('/')[3];
      const kvKey = `brand:${brandCode}`;
      const kvData = await env.MENU_CACHE.get(kvKey);
      
      if (kvData) {
        response = new Response(kvData, {
          headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'public, max-age=3600'
          }
        });
        
        // 存入 Cache API
        ctx.waitUntil(cache.put(cacheKey, response.clone()));
        return response;
      }
    }
    
    // 3. 從源站取得
    response = await fetch(request);
    
    // 快取熱門內容
    if (response.ok && url.pathname.startsWith('/api/menu/')) {
      const brandCode = url.pathname.split('/')[3];
      const data = await response.text();
      
      // 非同步更新 KV
      ctx.waitUntil(
        env.MENU_CACHE.put(`brand:${brandCode}`, data, {
          expirationTtl: 86400
        })
      );
      
      // 建立可快取的回應
      response = new Response(data, response);
      response.headers.set('Cache-Control', 'public, max-age=3600');
      
      // 存入 Cache API
      ctx.waitUntil(cache.put(cacheKey, response.clone()));
    }
    
    return response;
  }
};
```

## 實作步驟

### 步驟 1：設定 Cloudflare Workers
1. 登入 Cloudflare Dashboard
2. 進入 Workers & Pages
3. 建立新的 Worker
4. 貼上上述程式碼
5. 設定自訂網域或路由

### 步驟 2：建立 KV 命名空間
```bash
# 使用 Wrangler CLI
npm install -g wrangler
wrangler login
wrangler kv:namespace create "MENU_CACHE"
wrangler kv:namespace create "INDEX_CACHE"
```

### 步驟 3：預載熱門菜單
```javascript
// preload.js
const popularBrands = [
  'cocotea', '50lantea', 'chingshin', 'milkshoptea',
  'comebuytea', 'teatop', 'truedan'
];

async function preloadMenus() {
  for (const brand of popularBrands) {
    const response = await fetch(`https://your-domain.com/api/menu/${brand}`);
    const data = await response.text();
    
    await MENU_CACHE.put(`brand:${brand}`, data, {
      expirationTtl: 86400
    });
  }
}
```

### 步驟 4：Laravel API 端點
```php
// routes/api.php
Route::get('/menu/{brandCode}', [ApiMenuController::class, 'show']);

// app/Http/Controllers/ApiMenuController.php
public function show($brandCode)
{
    $menu = $this->menuService->getMenuByBrandCode($brandCode);
    
    if (!$menu) {
        return response()->json(['error' => 'Menu not found'], 404);
    }
    
    return response()
        ->json($menu)
        ->header('Cache-Control', 'public, max-age=3600');
}
```

## 效能評估

### 預期效能提升
| 指標 | 現有系統 | 優化後 |
|------|---------|--------|
| 首次載入 | 100-500ms | 20-50ms |
| 快取命中 | 10-20ms | 5-10ms |
| 全球延遲 | 200-1000ms | 10-50ms |
| 併發能力 | 100 req/s | 10,000+ req/s |

### 成本分析
- **免費額度內**：每日 10 萬次查詢
- **超出計算**：
  - 若每日 20 萬次查詢
  - 超出 10 萬次 = $0.50/百萬次 = $0.05/日

### 監控指標
```javascript
// Workers Analytics
{
  requests: {
    cached: 85000,  // 85% 快取命中率
    uncached: 15000
  },
  bandwidth: {
    cached: "850MB",
    uncached: "150MB"
  },
  cpu_time: "avg 0.5ms"
}
```

## 進階優化

### 1. 智慧快取策略
```javascript
// 根據熱門度調整 TTL
const ttl = isPopularBrand(brandCode) ? 86400 : 3600;
```

### 2. 邊緣運算搜尋
```javascript
// 在 Workers 中實作簡單搜尋
async function searchInWorker(keyword) {
  const allBrands = await env.INDEX_CACHE.get('brands', 'json');
  return allBrands.filter(brand => 
    brand.name.includes(keyword)
  );
}
```

### 3. 預測性預載
```javascript
// 根據使用模式預載
if (hour >= 11 && hour <= 14) {
  // 午餐時段預載熱門店家
  await preloadLunchFavorites();
}
```

## 結論

使用 Cloudflare Workers KV 配合現有的檔案快取系統，可以：
1. **零成本**提升效能（免費額度內）
2. **全球加速**，降低延遲到 50ms 以下
3. **減少源站負載** 90% 以上
4. **保持簡單**，不需要管理 Redis 或資料庫

建議從 Workers KV 方案開始，逐步優化到混合架構。