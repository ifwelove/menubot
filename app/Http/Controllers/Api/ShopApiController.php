<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MenuService;
use App\Services\ShopSearchService;
use Illuminate\Http\Request;

class ShopApiController extends Controller
{
    protected $menuService;
    protected $shopSearchService;

    public function __construct(MenuService $menuService, ShopSearchService $shopSearchService)
    {
        $this->menuService = $menuService;
        $this->shopSearchService = $shopSearchService;
    }

    /**
     * List all shops
     */
    public function list()
    {
        $shops = config('menu.shops.drink', []);

        $shopList = [];
        foreach ($shops as $code => $name) {
            $menu = $this->menuService->getMenuByBrandCode($code);
            $shopList[] = [
                'code' => $code,
                'name' => $name,
                'image' => $menu['image_url'] ?? null,
            ];
        }

        return response()->json([
            'shops' => $shopList,
            'total' => count($shopList),
        ]);
    }

    /**
     * Search shops
     */
    public function search(Request $request)
    {
        $keyword = $request->input('q', '');

        if (empty($keyword)) {
            return response()->json(['shops' => []]);
        }

        $results = $this->shopSearchService->search($keyword);

        $shops = [];
        foreach ($results as $code => $name) {
            $menu = $this->menuService->getMenuByBrandCode($code);
            $shops[] = [
                'code' => $code,
                'name' => $name,
                'image' => $menu['image_url'] ?? null,
            ];
        }

        return response()->json(['shops' => $shops]);
    }

    /**
     * Get stores for a specific brand
     */
    public function stores($brandCode)
    {
        $nidinShops = $this->shopSearchService->getNidinShops();

        if (!isset($nidinShops[$brandCode])) {
            return response()->json([
                'brand_code' => $brandCode,
                'stores' => [],
                'total' => 0,
            ]);
        }

        $stores = array_map(function ($store) use ($brandCode) {
            return [
                'brand_code' => $brandCode,
                'name' => $store['name'] ?? $store['name_short'] ?? '',
                'address' => $store['address'] ?? '',
                'tel' => $store['tel'] ?? '',
                'lat' => (float) ($store['latitude'] ?? 0),
                'lng' => (float) ($store['longitude'] ?? 0),
            ];
        }, $nidinShops[$brandCode]);

        return response()->json([
            'brand_code' => $brandCode,
            'stores' => $stores,
            'total' => count($stores),
        ]);
    }

    /**
     * Get all stores with coordinates for map
     */
    public function allStores()
    {
        $nidinShops = $this->shopSearchService->getNidinShops();
        $shops = config('menu.shops.drink', []);

        $allStores = [];

        foreach ($nidinShops as $brandCode => $stores) {
            $brandName = $shops[$brandCode] ?? $brandCode;

            foreach ($stores as $store) {
                // Skip stores without coordinates
                if (empty($store['latitude']) || empty($store['longitude'])) {
                    continue;
                }

                $allStores[] = [
                    'brand_code' => $brandCode,
                    'brand_name' => $brandName,
                    'name' => $store['name'] ?? $store['name_short'] ?? '',
                    'address' => $store['address'] ?? '',
                    'tel' => $store['tel'] ?? '',
                    'lat' => (float) $store['latitude'],
                    'lng' => (float) $store['longitude'],
                ];
            }
        }

        return response()->json([
            'stores' => $allStores,
            'total' => count($allStores),
        ]);
    }
}
