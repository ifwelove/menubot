@extends('layouts.app')

@section('title', '飲料店菜單查詢 - 232家飲料店完整菜單')
@section('description', '全台最完整的飲料店菜單查詢平台，提供232家飲料店的完整菜單與價格資訊')

@section('content')
<div class="page-section py-6 md:py-10">
    <section class="hero-panel px-5 py-8 md:px-10 md:py-12">
        <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1fr),240px] lg:items-start">
            <div>
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-stone-200 bg-stone-100 px-3 py-1 text-sm font-medium text-stone-700">
                    全台飲料店菜單索引
                </div>
                <h1 class="max-w-2xl text-3xl font-bold leading-tight tracking-tight text-stone-900 md:text-4xl lg:text-[3.25rem]">
                    快速找到品牌菜單、分類標籤與附近門市
                </h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-stone-600 md:text-lg">
                    集中整理 232 家飲料店菜單與價格，從品牌搜尋、分類瀏覽到附近門市，一頁就能完成。
                </p>

                <div class="mt-8 max-w-2xl relative" x-data="shopSearch()">
                    <div class="relative">
                        <input
                            type="text"
                            x-model="query"
                            @input.debounce.300ms="search()"
                            @focus="showDropdown = query.length > 0 && results.length > 0"
                            @blur="closeDropdown()"
                            placeholder="搜尋品牌、關鍵字或飲料類型..."
                            class="w-full rounded-[1.4rem] border border-stone-200 bg-white px-5 py-4 pl-12 text-lg shadow-[0_12px_32px_rgba(41,37,36,0.08)] outline-none transition-all focus:border-stone-400 focus:ring-4 focus:ring-stone-200"
                        >
                        <svg class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <div x-show="isLoading" class="absolute right-4 top-1/2 -translate-y-1/2">
                            <svg class="h-5 w-5 animate-spin text-stone-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>

                    <div
                        x-show="showDropdown && results.length > 0"
                        x-transition
                        class="search-dropdown"
                    >
                        <template x-for="shop in results" :key="shop.code">
                            <div
                                @click="selectShop(shop.code)"
                                class="search-result-item"
                            >
                                <span x-text="shop.name" class="font-medium text-stone-800"></span>
                            </div>
                        </template>
                    </div>

                    <div
                        x-show="showDropdown && query.length > 0 && results.length === 0 && !isLoading"
                        x-transition
                        class="search-dropdown"
                    >
                        <div class="px-4 py-3 text-stone-500">找不到符合的店家</div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-3 text-sm text-stone-500">
                    <span class="rounded-full bg-white/80 px-3 py-1">品牌搜尋</span>
                    <span class="rounded-full bg-white/80 px-3 py-1">分類標籤</span>
                    <span class="rounded-full bg-white/80 px-3 py-1">附近門市</span>
                </div>
            </div>

            <div class="space-y-4 lg:self-start">
                <div
                    class="hero-stat"
                    x-data='randomShopPicker(@json($randomShopOptions), @json($randomShop), @json(route("shop.random")), @json($regionOptions), @json($regionShopMap))'
                >
                    <div class="text-sm text-stone-500">隨機抽一家</div>
                    <p class="mt-2 text-sm leading-6 text-stone-500">
                        可以先選縣市和區域，不選也能直接全台隨機抽。
                    </p>
                    <div class="mt-4 grid gap-2">
                        <select
                            x-model="selectedCity"
                            @change="onCityChange()"
                            class="rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm text-stone-700 outline-none transition-colors focus:border-stone-500"
                        >
                            <option value="">全部縣市</option>
                            <template x-for="region in regions" :key="region.name">
                                <option :value="region.name" x-text="region.name"></option>
                            </template>
                        </select>
                        <select
                            x-model="selectedDistrict"
                            @change="onDistrictChange()"
                            :disabled="!selectedCity"
                            class="rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm text-stone-700 outline-none transition-colors focus:border-stone-500 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400"
                        >
                            <option value="">全部區域</option>
                            <template x-for="district in cityDistricts()" :key="district">
                                <option :value="district" x-text="district"></option>
                            </template>
                        </select>
                    </div>
                    <p class="mt-2 text-xs text-stone-500" x-text="`${filterLabel()} 可抽 ${availableCount()} 家品牌`">
                        全台品牌可抽 {{ count($randomShopOptions) }} 家
                    </p>
                    <div class="mt-3 min-h-[3.5rem] text-2xl font-bold leading-tight text-stone-900" x-text="selected?.name || '這個範圍目前沒有可抽的品牌'">
                        {{ $randomShop['name'] ?? '暫時沒有店家可抽' }}
                    </div>
                    <div class="mt-4 grid gap-2">
                        <button
                            type="button"
                            @click="pick()"
                            :disabled="!availableCount()"
                            class="inline-flex items-center justify-center rounded-xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-700 transition-colors hover:border-stone-400 hover:text-stone-900"
                        >
                            重抽一家
                        </button>
                        <a
                            href="{{ $randomShop['url'] ?? route('shop.random') }}"
                            :href="selectedUrl()"
                            class="inline-flex items-center justify-center rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-stone-700"
                        >
                            查看這家菜單
                        </a>
                    </div>
                </div>

                <div class="hero-stat">
                    <div class="text-sm text-stone-500">快速入口</div>
                    <div class="mt-3 grid gap-2">
                        <a href="{{ route('nearby') }}" class="inline-flex text-sm font-medium text-stone-700 hover:text-stone-900">
                            找附近門市 →
                        </a>
                        <a href="{{ route('tags') }}" class="inline-flex text-sm font-medium text-stone-700 hover:text-stone-900">
                            從分類標籤開始 →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-10">
        <div class="mb-5 flex items-end justify-between gap-4">
            <div>
                <h2 class="section-title">熱門分類</h2>
                <p class="mt-2 text-sm text-stone-500">先從常見飲料類型開始找，縮小品牌範圍會快很多。</p>
            </div>
            <a href="{{ route('tags') }}" class="hidden text-sm font-medium text-stone-600 hover:text-stone-900 md:inline-flex">
                查看全部標籤
            </a>
        </div>

        <div class="mb-10 flex flex-wrap gap-2">
            <a href="{{ route('home') }}" class="tag-button {{ request()->routeIs('home') && !request()->has('tag') ? 'tag-button-active' : 'tag-button-inactive' }}">
                全部
            </a>
            @foreach($tags as $tagName => $tagShops)
                <a href="{{ route('tags.shops', urlencode($tagName)) }}" class="tag-button tag-button-inactive">
                    {{ $tagName }}
                </a>
            @endforeach
        </div>
    </section>

    <section>
        <div class="mb-5 flex items-end justify-between gap-4">
            <div>
                <h2 class="section-title">品牌列表</h2>
                <p class="mt-2 text-sm text-stone-500">依品牌瀏覽完整菜單與價格，支援手機直接查詢。</p>
            </div>
            <div class="text-sm text-stone-500">
                共 {{ count($shopList) }} 家飲料店
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-4 xl:grid-cols-5">
            @foreach($shopList as $shop)
                @include('components.shop-card', [
                    'code' => $shop['code'],
                    'name' => $shop['name'],
                    'image' => $shop['image']
                ])
            @endforeach
        </div>
    </section>
</div>
@endsection
