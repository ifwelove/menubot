@php
    $image = $image ?? null;
@endphp

<div class="shop-card">
    <div class="shop-card-image">
        @if($image)
            <img src="{{ $image }}" alt="{{ $name }}" class="w-full h-full object-contain" loading="lazy">
        @else
            <div class="w-full h-full flex items-center justify-center text-gray-400">
                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
        @endif
    </div>
    <div class="shop-card-content">
        <h3 class="shop-card-title text-center">{{ $name }}</h3>
        <a href="{{ route('shop.menu', $code) }}" class="shop-card-button block text-center">
            查看菜單
        </a>
    </div>
</div>
