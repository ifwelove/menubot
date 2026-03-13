@extends('layouts.app')

@section('title', '飲料店菜單查詢 - 232家飲料店完整菜單')
@section('description', '全台最完整的飲料店菜單查詢平台，提供232家飲料店的完整菜單與價格資訊')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Hero Section -->
    <div class="text-center mb-12">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">飲料店菜單查詢</h1>
        <p class="text-gray-600 mb-8">提供 232 家飲料店的完整菜單與價格</p>

        <!-- Search Bar -->
        <div class="max-w-xl mx-auto relative" x-data="shopSearch()">
            <div class="relative">
                <input
                    type="text"
                    x-model="query"
                    @input.debounce.300ms="search()"
                    @focus="showDropdown = query.length > 0 && results.length > 0"
                    @blur="closeDropdown()"
                    placeholder="搜尋品牌或飲料..."
                    class="w-full px-5 py-4 pl-12 rounded-xl border border-gray-300 shadow-sm focus:ring-2 focus:ring-gray-400 focus:border-transparent transition-all text-lg"
                >
                <svg class="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <div x-show="isLoading" class="absolute right-4 top-1/2 transform -translate-y-1/2">
                    <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>

            <!-- Search Results Dropdown -->
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
                        <span x-text="shop.name" class="font-medium text-gray-800"></span>
                    </div>
                </template>
            </div>

            <!-- No Results -->
            <div
                x-show="showDropdown && query.length > 0 && results.length === 0 && !isLoading"
                x-transition
                class="search-dropdown"
            >
                <div class="px-4 py-3 text-gray-500">找不到符合的店家</div>
            </div>
        </div>
    </div>

    <!-- Tags Filter -->
    <div class="mb-8">
        <div class="flex flex-wrap justify-center gap-2">
            <a href="{{ route('home') }}" class="tag-button {{ request()->routeIs('home') && !request()->has('tag') ? 'tag-button-active' : 'tag-button-inactive' }}">
                全部
            </a>
            @foreach($tags as $tagName => $tagShops)
                <a href="{{ route('tags.shops', urlencode($tagName)) }}" class="tag-button tag-button-inactive">
                    {{ $tagName }}
                </a>
            @endforeach
        </div>
    </div>

    <!-- Shop Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
        @foreach($shopList as $shop)
            @include('components.shop-card', [
                'code' => $shop['code'],
                'name' => $shop['name'],
                'image' => $shop['image']
            ])
        @endforeach
    </div>

    <!-- Shop Count -->
    <div class="text-center mt-12 text-gray-500">
        共 {{ count($shopList) }} 家飲料店
    </div>
</div>
@endsection
