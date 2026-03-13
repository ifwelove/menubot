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
        $shopList = $this->buildShopList($shops);
        $randomShopOptions = $this->buildRandomShopOptions($shops);
        $randomShop = $this->pickRandomShop($randomShopOptions);
        [$regionOptions, $regionShopMap] = $this->buildRandomShopRegionData($randomShopOptions);

        shuffle($shopList);

        return view('home', compact('shopList', 'tags', 'randomShop', 'randomShopOptions', 'regionOptions', 'regionShopMap'));
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
     * Redirect to a random shop menu
     */
    public function randomShop(Request $request)
    {
        $shops = config('menu.shops.drink', []);
        $randomShopOptions = $this->buildRandomShopOptions($shops);
        $city = $request->query('city', '');
        $district = $request->query('district', '');

        $candidateShops = $this->filterRandomShopOptionsByRegion($randomShopOptions, $city, $district);

        if (($city || $district) && empty($candidateShops)) {
            return redirect()->route('home');
        }

        $randomShop = $this->pickRandomShop($candidateShops ?: $randomShopOptions);

        if (!$randomShop) {
            abort(404, '目前沒有可抽選的店家');
        }

        return redirect()->to($randomShop['url']);
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

    private function buildShopList(array $shops): array
    {
        $shopList = [];

        foreach ($shops as $code => $name) {
            $menu = $this->menuService->getMenuByBrandCode($code);
            $shopList[] = [
                'code' => $code,
                'name' => $name,
                'image' => $menu['image_url'] ?? null,
                'url' => route('shop.menu', $code),
            ];
        }

        return $shopList;
    }

    private function buildRandomShopOptions(array $shops): array
    {
        $shopOptions = [];

        foreach ($shops as $code => $name) {
            $shopOptions[] = [
                'code' => $code,
                'name' => $name,
                'url' => route('shop.menu', $code),
            ];
        }

        return $shopOptions;
    }

    private function pickRandomShop(array $shops): ?array
    {
        if (empty($shops)) {
            return null;
        }

        return $shops[array_rand($shops)];
    }

    private function buildRandomShopRegionData(array $shopOptions): array
    {
        $shopOptionsByCode = [];
        foreach ($shopOptions as $shopOption) {
            $shopOptionsByCode[$shopOption['code']] = $shopOption;
        }

        $regionIndex = $this->getRegionBrandIndex();
        $regionOptions = [];
        $regionShopMap = [];

        foreach ($regionIndex as $city => $cityData) {
            $districts = array_keys($cityData['districts'] ?? []);

            $regionOptions[] = [
                'name' => $city,
                'districts' => $districts,
            ];

            $regionShopMap[$city] = [
                '_all' => $this->mapBrandCodesToShopOptions(array_keys($cityData['_all'] ?? []), $shopOptionsByCode),
                'districts' => [],
            ];

            foreach ($districts as $district) {
                $regionShopMap[$city]['districts'][$district] = $this->mapBrandCodesToShopOptions(
                    array_keys($cityData['districts'][$district] ?? []),
                    $shopOptionsByCode
                );
            }
        }

        return [$regionOptions, $regionShopMap];
    }

    private function filterRandomShopOptionsByRegion(array $shopOptions, string $city = '', string $district = ''): array
    {
        $city = trim($city);
        $district = trim($district);

        if ($city === '') {
            return $shopOptions;
        }

        $regionIndex = $this->getRegionBrandIndex();
        if (!isset($regionIndex[$city])) {
            return [];
        }

        $brandCodes = $district !== ''
            ? array_keys($regionIndex[$city]['districts'][$district] ?? [])
            : array_keys($regionIndex[$city]['_all'] ?? []);

        $shopOptionsByCode = [];
        foreach ($shopOptions as $shopOption) {
            $shopOptionsByCode[$shopOption['code']] = $shopOption;
        }

        return $this->mapBrandCodesToShopOptions($brandCodes, $shopOptionsByCode);
    }

    private function getRegionBrandIndex(): array
    {
        return \Cache::remember('shop_region_brand_index_v1', 3600, function () {
            $nidinShops = $this->shopSearchService->getNidinShops();
            $regionIndex = [];

            foreach ($nidinShops as $brandCode => $stores) {
                foreach ($stores as $store) {
                    $region = $this->extractRegionFromAddress($store['address'] ?? '');

                    if (!$region) {
                        continue;
                    }

                    $city = $region['city'];
                    $district = $region['district'];

                    $regionIndex[$city]['_all'][$brandCode] = true;
                    $regionIndex[$city]['districts'][$district][$brandCode] = true;
                }
            }

            ksort($regionIndex);

            foreach ($regionIndex as &$cityData) {
                ksort($cityData['districts']);
            }

            return $regionIndex;
        });
    }

    private function extractRegionFromAddress(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        if (!preg_match('/^(?<city>[^市縣]{1,3}[市縣])(?<district>[^區鄉鎮市]{1,4}[區鄉鎮市])/u', $address, $matches)) {
            return null;
        }

        return [
            'city' => $matches['city'],
            'district' => $matches['district'],
        ];
    }

    private function mapBrandCodesToShopOptions(array $brandCodes, array $shopOptionsByCode): array
    {
        $shopOptions = [];

        foreach ($brandCodes as $brandCode) {
            if (!isset($shopOptionsByCode[$brandCode])) {
                continue;
            }

            $shopOptions[] = $shopOptionsByCode[$brandCode];
        }

        return $shopOptions;
    }
}
