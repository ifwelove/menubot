<?php

namespace Tests\Feature;

use App\Services\ShopSearchService;
use Tests\TestCase;

class HomePageRandomShopTest extends TestCase
{
    public function test_homepage_shows_random_shop_module()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('隨機抽一家');
        $response->assertSee('全部縣市');
        $response->assertSee('查看這家菜單');
        $response->assertSee('立即訂購');
    }

    public function test_shop_menu_page_shows_order_button()
    {
        $brandCode = array_key_first(config('menu.shops.drink', []));

        $response = $this->get('/shop/' . $brandCode);

        $response->assertStatus(200);
        $response->assertSee('立即訂購');
        $response->assertSee('target="_blank"', false);
        $response->assertSee('order.nidin.shop/brand/', false);
    }

    public function test_random_shop_route_redirects_to_a_valid_shop_menu()
    {
        $response = $this->get('/shop/random');

        $response->assertStatus(302);

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringStartsWith(url('/shop/'), $location);

        $path = parse_url($location, PHP_URL_PATH) ?? '';
        $brandCode = basename($path);

        $this->assertArrayHasKey($brandCode, config('menu.shops.drink', []));
    }

    public function test_random_shop_route_can_filter_by_city_and_district()
    {
        [$city, $district, $brandCodes] = $this->firstAvailableRegion();

        $response = $this->get('/shop/random?city=' . urlencode($city) . '&district=' . urlencode($district));

        $response->assertStatus(302);

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);

        $brandCode = basename(parse_url($location, PHP_URL_PATH) ?? '');

        $this->assertContains($brandCode, $brandCodes);
    }

    private function firstAvailableRegion(): array
    {
        $nidinShops = app(ShopSearchService::class)->getNidinShops();
        $shops = config('menu.shops.drink', []);

        foreach ($nidinShops as $brandCode => $stores) {
            if (!isset($shops[$brandCode])) {
                continue;
            }

            foreach ($stores as $store) {
                $address = trim($store['address'] ?? '');

                if (!preg_match('/^(?<city>[^市縣]{1,3}[市縣])(?<district>[^區鄉鎮市]{1,4}[區鄉鎮市])/u', $address, $matches)) {
                    continue;
                }

                $city = $matches['city'];
                $district = $matches['district'];
                $brandCodes = [];

                foreach ($nidinShops as $candidateBrandCode => $candidateStores) {
                    if (!isset($shops[$candidateBrandCode])) {
                        continue;
                    }

                    foreach ($candidateStores as $candidateStore) {
                        $candidateAddress = trim($candidateStore['address'] ?? '');

                        if (str_starts_with($candidateAddress, $city . $district)) {
                            $brandCodes[] = $candidateBrandCode;
                            break;
                        }
                    }
                }

                if (!empty($brandCodes)) {
                    return [$city, $district, array_values(array_unique($brandCodes))];
                }
            }
        }

        $this->fail('找不到可用的縣市區域測試資料');
    }
}
