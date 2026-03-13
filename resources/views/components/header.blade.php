<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <a href="{{ route('home') }}" class="flex items-center space-x-2">
                <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="text-xl font-bold text-gray-800">飲料店菜單</span>
            </a>

            <!-- Navigation -->
            <nav class="hidden md:flex items-center space-x-6">
                <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900 transition-colors {{ request()->routeIs('home') ? 'text-gray-900 font-medium' : '' }}">
                    首頁
                </a>
                <a href="{{ route('nearby') }}" class="text-gray-600 hover:text-gray-900 transition-colors {{ request()->routeIs('nearby') ? 'text-gray-900 font-medium' : '' }}">
                    附近門市
                </a>
                <a href="{{ route('tags') }}" class="text-gray-600 hover:text-gray-900 transition-colors {{ request()->routeIs('tags*') ? 'text-gray-900 font-medium' : '' }}">
                    分類標籤
                </a>
            </nav>

            <!-- Mobile menu button -->
            <div class="md:hidden" x-data="{ open: false }">
                <button @click="open = !open" class="text-gray-600 hover:text-gray-900 p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <!-- Mobile menu -->
                <div x-show="open" @click.away="open = false" class="absolute top-16 left-0 right-0 bg-white border-b border-gray-200 shadow-lg">
                    <div class="container mx-auto px-4 py-4 space-y-3">
                        <a href="{{ route('home') }}" class="block text-gray-600 hover:text-gray-900">首頁</a>
                        <a href="{{ route('nearby') }}" class="block text-gray-600 hover:text-gray-900">附近門市</a>
                        <a href="{{ route('tags') }}" class="block text-gray-600 hover:text-gray-900">分類標籤</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
