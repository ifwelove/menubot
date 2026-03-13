@extends('layouts.app')

@section('title', '附近門市 - 飲料店菜單查詢')
@section('description', '查詢你附近的飲料店門市')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #map {
        height: 400px;
        width: 100%;
        border-radius: 0.75rem;
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
<div class="container mx-auto px-4 py-8" x-data="nearbyStores(@js($initialBrand ?? ''))">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">附近門市</h1>
        <p class="text-gray-600">找到你附近的飲料店</p>
    </div>

    <!-- Brand Filter -->
    <div class="mb-6">
        <label for="brandSelect" class="block text-sm font-medium text-gray-700 mb-2">選擇品牌</label>
        <select
            id="brandSelect"
            x-model="selectedBrand"
            @change="onBrandChange()"
            class="w-full md:w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-400 focus:border-transparent"
        >
            <option value="">全部品牌</option>
            @foreach($shops as $code => $name)
                <option value="{{ $code }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <!-- Loading State -->
    <div x-show="isLoading" class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center mb-6">
        <svg class="animate-spin h-8 w-8 mx-auto text-gray-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="text-gray-500">正在取得你的位置...</p>
    </div>

    <!-- Error State -->
    <div x-show="error && !isLoading" class="bg-red-50 border border-red-200 rounded-xl p-6 mb-6">
        <div class="flex items-center">
            <svg class="w-6 h-6 text-red-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-red-700" x-text="error"></p>
        </div>
        <p class="text-red-600 text-sm mt-2">請允許瀏覽器存取你的位置，或手動搜尋店家。</p>
    </div>

    <!-- Map -->
    <div x-show="!isLoading" class="mb-8">
        <div id="map" class="shadow-sm border border-gray-200"></div>
    </div>

    <!-- Nearby Stores List -->
    <div x-show="!isLoading && filteredStores.length > 0">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            附近的店家
            <span class="text-sm font-normal text-gray-500" x-text="'（共 ' + filteredStores.length + ' 家）'"></span>
        </h2>

        <div class="space-y-4">
            <template x-for="store in filteredStores" :key="store.name + store.address">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between">
                        <div class="flex-grow">
                            <h3 class="font-semibold text-gray-800" x-text="store.brand_name + ' - ' + store.name"></h3>
                            <p class="text-sm text-gray-600 mt-1" x-text="store.address"></p>
                            <p class="text-sm text-gray-500 mt-1" x-show="store.tel" x-text="'電話: ' + store.tel"></p>
                        </div>
                        <div class="flex flex-col items-end gap-2 ml-4">
                            <span class="text-sm font-medium text-gray-600 bg-gray-100 px-3 py-1 rounded-full" x-text="formatDistance(store.distance)"></span>
                            <button
                                @click="openNavigation(store)"
                                class="text-sm text-white bg-gray-600 hover:bg-gray-700 px-3 py-1 rounded-lg transition-colors"
                            >
                                導航
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- No Results -->
    <div x-show="!isLoading && !error && filteredStores.length === 0 && userLocation" class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        <p class="text-gray-500">附近 5 公里內沒有找到飲料店</p>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush
