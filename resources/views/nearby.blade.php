@extends('layouts.app')

@section('title', '附近門市 - 飲料店菜單查詢')
@section('description', '查詢你附近的飲料店門市')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #map {
        height: 100%;
        min-height: 440px;
        width: 100%;
    }
    .leaflet-popup-content {
        margin: 10px 15px;
    }
    .user-marker {
        background: transparent;
        border: none;
    }
</style>
@endpush

@section('content')
<div class="page-section py-6 md:py-10" x-data="nearbyStores(@js($initialBrand ?? ''))">
    <div class="mb-8">
        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-stone-200 bg-stone-100 px-3 py-1 text-sm font-medium text-stone-700">
            位置導向搜尋
        </div>
        <h1 class="section-title">附近門市</h1>
        <p class="mt-3 max-w-2xl text-stone-600">允許定位後，系統會抓出你附近 5 公里內的飲料店，並可依品牌再篩一次。</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[320px,minmax(0,1fr)] lg:items-start">
        <aside class="space-y-4">
            <div class="map-sidebar-card">
                <label for="brandSelect" class="mb-2 block text-sm font-medium text-stone-700">選擇品牌</label>
                <select
                    id="brandSelect"
                    x-model="selectedBrand"
                    @change="onBrandChange()"
                    class="w-full rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-stone-800 outline-none transition focus:border-stone-400 focus:ring-4 focus:ring-stone-200"
                >
                    <option value="">全部品牌</option>
                    @foreach($shops as $code => $name)
                        <option value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                </select>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-2xl bg-stone-50 px-4 py-3">
                        <div class="text-stone-500">搜尋半徑</div>
                        <div class="mt-1 font-semibold text-stone-900">5 公里</div>
                    </div>
                    <div class="rounded-2xl bg-stone-50 px-4 py-3">
                        <div class="text-stone-500">結果數量</div>
                        <div class="mt-1 font-semibold text-stone-900" x-text="filteredStores.length"></div>
                    </div>
                </div>

                <template x-if="userLocation">
                    <div class="mt-4 rounded-2xl bg-stone-100 px-4 py-3 text-sm text-stone-700">
                        已取得定位，地圖會自動對焦到你附近的門市。
                    </div>
                </template>
            </div>

            <div x-show="error && !isLoading" class="rounded-2xl border border-red-200 bg-red-50 p-5">
                <div class="flex items-center">
                    <svg class="mr-3 h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-red-700" x-text="error"></p>
                </div>
                <p class="mt-2 text-sm text-red-600">請允許瀏覽器存取位置，或改用品牌菜單頁繼續查詢。</p>
            </div>
        </aside>

        <section class="space-y-6">
            <div class="map-shell relative">
                <div id="map"></div>
                <div x-show="isLoading" class="absolute inset-0 flex items-center justify-center bg-white/75 backdrop-blur-sm">
                    <div class="text-center">
                        <svg class="mx-auto mb-4 h-8 w-8 animate-spin text-stone-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-stone-600">正在取得你的位置...</p>
                    </div>
                </div>
            </div>

            <div x-show="!isLoading && filteredStores.length > 0">
                <h2 class="mb-4 text-xl font-bold text-stone-800">
                    附近的店家
                    <span class="text-sm font-normal text-stone-500" x-text="'（共 ' + filteredStores.length + ' 家）'"></span>
                </h2>

                <div class="space-y-4">
                    <template x-for="store in filteredStores" :key="store.name + store.address">
                        <div
                            @click="openStoreMenu(store)"
                            class="cursor-pointer rounded-[1.5rem] border border-stone-200/80 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-grow">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-700" x-text="store.brand_name"></span>
                                        <span class="rounded-full bg-stone-100 px-3 py-1 text-xs text-stone-600" x-text="formatDistance(store.distance)"></span>
                                    </div>
                                    <h3 class="font-semibold text-stone-800" x-text="store.name"></h3>
                                    <p class="mt-1 text-sm text-stone-600" x-text="store.address"></p>
                                    <p class="mt-1 text-sm text-stone-500" x-show="store.tel" x-text="'電話: ' + store.tel"></p>
                                </div>
                                <button
                                    @click.stop="openNavigation(store)"
                                    class="rounded-xl bg-stone-900 px-4 py-2 text-sm text-white transition-colors hover:bg-stone-700"
                                >
                                    導航
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div x-show="!isLoading && !error && filteredStores.length === 0 && userLocation" class="rounded-[1.75rem] border border-stone-200/80 bg-white p-12 text-center shadow-sm">
                <svg class="mx-auto mb-4 h-16 w-16 text-stone-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <p class="text-stone-500">附近 5 公里內沒有找到飲料店</p>
            </div>
        </section>
    </div>

    <div
        x-cloak
        x-show="menuModal.open"
        x-transition.opacity
        @keydown.escape.window="closeStoreMenu()"
        class="fixed inset-0 z-50 flex items-end justify-center bg-stone-950/50 p-4 backdrop-blur-sm md:items-center"
    >
        <div @click="closeStoreMenu()" class="absolute inset-0"></div>
        <div x-show="menuModal.open" x-transition class="relative z-10 flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-[2rem] bg-white shadow-2xl">
            <div class="border-b border-stone-200 px-6 py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-700" x-text="menuModal.store?.brand_name || ''"></span>
                            <span class="rounded-full bg-stone-100 px-3 py-1 text-xs text-stone-600" x-text="menuModal.store ? formatDistance(menuModal.store.distance) : ''"></span>
                        </div>
                        <h2 class="text-2xl font-bold text-stone-900" x-text="menuModal.menu?.shop_name || menuModal.store?.brand_name || '菜單'"></h2>
                        <p class="mt-2 text-sm text-stone-600" x-text="menuModal.store?.name || ''"></p>
                        <p class="mt-1 text-sm text-stone-500" x-text="menuModal.store?.address || ''"></p>
                    </div>
                    <button @click="closeStoreMenu()" class="rounded-xl border border-stone-200 px-3 py-2 text-stone-600 hover:bg-stone-50 hover:text-stone-900">
                        關閉
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5">
                <div x-show="menuModal.loading" class="py-12 text-center text-stone-500">
                    讀取菜單中...
                </div>

                <div x-show="menuModal.error" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-700" x-text="menuModal.error"></div>

                <div x-show="!menuModal.loading && !menuModal.error && menuModal.menu">
                    <template x-if="menuModal.menu?.categories?.length">
                        <div class="space-y-6">
                            <template x-for="category in menuModal.menu.categories" :key="category.name">
                                <section class="rounded-[1.5rem] border border-stone-200/80 bg-stone-50/70 p-5">
                                    <h3 class="mb-4 text-lg font-semibold text-stone-900" x-text="category.name"></h3>
                                    <div class="space-y-3">
                                        <template x-for="item in category.items" :key="category.name + item.name">
                                            <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-4 py-3">
                                                <div class="min-w-0">
                                                    <div class="font-medium text-stone-800" x-text="item.name"></div>
                                                    <div x-show="item.description" class="mt-1 text-sm text-stone-500" x-text="item.description"></div>
                                                </div>
                                                <div class="shrink-0 text-right text-sm text-stone-600">
                                                    <div x-show="item.price" x-text="'$' + item.price"></div>
                                                    <div x-show="item.price_cold" x-text="'冷 $' + item.price_cold"></div>
                                                    <div x-show="item.price_hot" x-text="'熱 $' + item.price_hot"></div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </section>
                            </template>
                        </div>
                    </template>

                    <div x-show="menuModal.menu && !menuModal.menu.categories?.length" class="rounded-2xl border border-stone-200 bg-stone-50 p-5 text-stone-500">
                        目前沒有可顯示的菜單項目。
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush
