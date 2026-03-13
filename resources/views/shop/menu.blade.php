@extends('layouts.app')

@section('title', ($menu['shop_name'] ?? '店家') . ' 菜單 - 飲料店菜單查詢')
@section('description', '查看 ' . ($menu['shop_name'] ?? '') . ' 的完整菜單與價格')

@section('content')
@php
    $drinkPool = [];

    foreach (($menu['menu_items'] ?? []) as $category => $categoryData) {
        $items = isset($categoryData['items']) ? $categoryData['items'] : $categoryData;

        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $item) {
            if (!is_array($item) || empty($item['name'])) {
                continue;
            }

            $drinkPool[] = [
                'category' => $category,
                'name' => $item['name'],
                'description' => $item['description'] ?? '',
                'price' => $item['price'] ?? '',
                'price_cold' => $item['price_cold'] ?? ($item['cold'] ?? ''),
                'price_hot' => $item['price_hot'] ?? ($item['hot'] ?? ''),
            ];
        }
    }
@endphp

<div class="container mx-auto px-4 py-8" x-data='drinkPicker(@json($drinkPool))'>
    <!-- Breadcrumb -->
    <nav class="mb-6">
        <a href="{{ route('home') }}" class="text-gray-500 hover:text-gray-700">
            <span class="inline-flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                返回首頁
            </span>
        </a>
    </nav>

    <!-- Shop Header -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
        <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
            <!-- Shop Image -->
            <div class="w-32 h-32 flex-shrink-0">
                @if(!empty($menu['image_url']))
                    <img src="{{ $menu['image_url'] }}" alt="{{ $menu['shop_name'] }}" class="w-full h-full object-contain">
                @else
                    <div class="w-full h-full bg-gray-100 rounded-lg flex items-center justify-center">
                        <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                @endif
            </div>

            <!-- Shop Info -->
            <div class="flex-grow text-center md:text-left">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">{{ $menu['shop_name'] ?? '店家' }}</h1>
                <div class="mb-4 flex flex-wrap justify-center md:justify-start gap-3">
                    <a
                        href="{{ $orderUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-stone-700"
                    >
                        立即訂購
                    </a>
                </div>
                <div class="flex flex-wrap justify-center md:justify-start gap-4 text-gray-600">
                    @if(!empty($menu['website_url']))
                        <a href="{{ $menu['website_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center hover:text-gray-800">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                            </svg>
                            官方網站
                        </a>
                    @endif
                    @if($storeCount > 0)
                        <a href="{{ route('nearby') }}?brand={{ $brandCode }}" class="inline-flex items-center hover:text-gray-800">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            {{ $storeCount }} 家門市
                        </a>
                    @endif
                </div>

                @if(count($drinkPool) > 0)
                    <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="text-sm font-medium text-gray-800">不知道喝什麼？</div>
                                <p class="mt-1 text-sm text-gray-500">按一下隨機抽一杯，從這家店現有菜單裡幫你選一個品項。</p>
                            </div>
                            <button
                                type="button"
                                @click="pick()"
                                class="inline-flex items-center justify-center rounded-xl bg-stone-900 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-stone-700"
                            >
                                隨機抽一杯
                            </button>
                        </div>

                        <div x-show="selected" x-transition class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600" x-text="selected?.category"></span>
                                <span class="rounded-full bg-stone-900 px-3 py-1 text-xs font-medium text-white">今日手氣</span>
                            </div>
                            <div class="mt-3 text-xl font-bold text-gray-900" x-text="selected?.name"></div>
                            <p x-show="selected?.description" class="mt-2 text-sm text-gray-500" x-text="selected?.description"></p>

                            <div class="mt-4 flex flex-wrap gap-3 text-sm text-gray-700">
                                <span x-show="selected?.price" class="rounded-full bg-gray-100 px-3 py-1" x-text="'$' + selected.price"></span>
                                <span x-show="selected?.price_cold" class="rounded-full bg-blue-50 px-3 py-1 text-blue-700" x-text="'冷 $' + selected.price_cold"></span>
                                <span x-show="selected?.price_hot" class="rounded-full bg-red-50 px-3 py-1 text-red-700" x-text="'熱 $' + selected.price_hot"></span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if(!empty($menu['menu_items']))
        <!-- Category Navigation -->
        <div class="sticky top-16 bg-gray-50 py-4 mb-6 -mx-4 px-4 border-b border-gray-200 overflow-x-auto z-30">
            <div class="flex gap-2 min-w-max">
                @foreach($menu['menu_items'] as $category => $items)
                    <a href="#category-{{ Str::slug($category) }}" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-100 whitespace-nowrap transition-colors">
                        {{ $category }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Menu Categories -->
        <div class="space-y-8">
            @foreach($menu['menu_items'] as $category => $categoryData)
                <div id="category-{{ Str::slug($category) }}" class="scroll-mt-32">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="menu-category-title px-6 pt-6">
                            {{ $category }}
                        </div>
                        <div class="px-6 pb-6">
                            @php
                                // Handle both old format (array of items) and new format (items key)
                                $items = isset($categoryData['items']) ? $categoryData['items'] : $categoryData;
                            @endphp
                            @if(is_array($items))
                                @foreach($items as $item)
                                    @if(is_array($item))
                                        @include('components.menu-item', ['item' => $item])
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-gray-500">目前沒有菜單資料</p>
        </div>
    @endif
</div>
@endsection
