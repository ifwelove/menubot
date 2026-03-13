@props(['item'])

<div class="menu-item">
    <div class="flex-grow">
        <span class="menu-item-name">{{ $item['name'] ?? '' }}</span>
        @if(!empty($item['description']))
            <p class="text-sm text-gray-500 mt-1">{{ $item['description'] }}</p>
        @endif
    </div>
    <div class="flex items-center space-x-4 text-sm">
        @if(!empty($item['price_cold']))
            <span class="menu-item-price">
                <span class="text-blue-500">冷</span> ${{ $item['price_cold'] }}
            </span>
        @endif
        @if(!empty($item['price_hot']))
            <span class="menu-item-price">
                <span class="text-red-500">熱</span> ${{ $item['price_hot'] }}
            </span>
        @endif
        @if(empty($item['price_cold']) && empty($item['price_hot']) && !empty($item['price']))
            <span class="menu-item-price">${{ $item['price'] }}</span>
        @endif
    </div>
</div>
