<?php

namespace App\Http\Controllers;

use App\Services\MenuService;
use App\Services\ShopSearchService;
use Illuminate\Http\Request;

class WebController extends Controller
{
    protected $menuService;
    protected $shopSearchService;

    public function __construct(MenuService $menuService, ShopSearchService $shopSearchService)
    {
        $this->menuService = $menuService;
        $this->shopSearchService = $shopSearchService;
    }

    /**
     * Homepage - Display all shops
     */
    public function index()
    {
        $shops = config('menu.shops.drink', []);
        $tags = config('shop_tags', []);

        // Transform shops array for the view
        $shopList = [];
        foreach ($shops as $code => $name) {
            $menu = $this->menuService->getMenuByBrandCode($code);
            $shopList[] = [
                'code' => $code,
                'name' => $name,
                'image' => $menu['image_url'] ?? null,
            ];
        }

        return view('home', compact('shopList', 'tags'));
    }

    /**
     * Search shops
     */
    public function search(Request $request)
    {
        $keyword = $request->input('q', '');
        $results = $this->shopSearchService->search($keyword);

        if ($request->wantsJson() || $request->ajax()) {
            $shops = [];
            foreach ($results as $code => $name) {
                $shops[] = [
                    'code' => $code,
                    'name' => $name,
                ];
            }
            return response()->json(['shops' => $shops]);
        }

        return view('search', [
            'keyword' => $keyword,
            'results' => $results,
        ]);
    }

    /**
     * Show shop menu
     */
    public function showMenu($brandCode)
    {
        $menu = $this->menuService->getMenuByBrandCode($brandCode);

        if (!$menu) {
            abort(404, '找不到該店家的菜單');
        }

        // Get store count from Nidin data
        $nidinShops = $this->shopSearchService->getNidinShops();
        $storeCount = isset($nidinShops[$brandCode]) ? count($nidinShops[$brandCode]) : 0;

        return view('shop.menu', compact('menu', 'brandCode', 'storeCount'));
    }

    /**
     * Tags list page
     */
    public function tags()
    {
        $tags = config('shop_tags', []);

        // Count shops in each tag
        $tagCounts = [];
        foreach ($tags as $tagName => $shops) {
            $tagCounts[$tagName] = count($shops);
        }

        return view('tags.index', compact('tags', 'tagCounts'));
    }

    /**
     * Shops by tag
     */
    public function shopsByTag($tagName)
    {
        $tags = config('shop_tags', []);

        if (!isset($tags[$tagName])) {
            abort(404, '找不到該標籤');
        }

        $tagShops = $tags[$tagName];
        $shopList = [];

        foreach ($tagShops as $code => $name) {
            $menu = $this->menuService->getMenuByBrandCode($code);
            $shopList[] = [
                'code' => $code,
                'name' => $name,
                'image' => $menu['image_url'] ?? null,
            ];
        }

        return view('tags.shops', compact('tagName', 'shopList'));
    }

    /**
     * Nearby stores page
     */
    public function nearby()
    {
        $shops = config('menu.shops.drink', []);
        $initialBrand = request()->query('brand', '');

        return view('nearby', compact('shops', 'initialBrand'));
    }
}
