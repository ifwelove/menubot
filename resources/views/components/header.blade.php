<header class="sticky top-0 z-40 border-b border-stone-200/80 bg-white/85 backdrop-blur">
    <div class="container mx-auto px-4">
        <div class="flex h-16 items-center justify-between">
            <!-- Logo -->
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-stone-100 text-stone-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </span>
                <span>
                    <span class="block text-base font-semibold tracking-tight text-stone-900">飲料店菜單</span>
                    <span class="block text-xs text-stone-500">查品牌、找門市、看價格</span>
                </span>
            </a>

            <!-- Navigation -->
            <nav class="hidden items-center gap-2 md:flex">
                <a href="{{ route('home') }}" class="rounded-full px-4 py-2 text-sm transition-colors {{ request()->routeIs('home') ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900' }}">
                    首頁
                </a>
                <a href="{{ route('nearby') }}" class="rounded-full px-4 py-2 text-sm transition-colors {{ request()->routeIs('nearby') ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900' }}">
                    附近門市
                </a>
                <a href="{{ route('tags') }}" class="rounded-full px-4 py-2 text-sm transition-colors {{ request()->routeIs('tags*') ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900' }}">
                    分類標籤
                </a>
            </nav>

            <!-- Mobile menu button -->
            <div class="md:hidden" x-data="{ open: false }">
                <button @click="open = !open" class="rounded-xl border border-stone-200 p-2 text-stone-600 hover:text-stone-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <!-- Mobile menu -->
                <div x-show="open" x-transition @click.away="open = false" class="absolute left-0 right-0 top-16 border-b border-stone-200 bg-white shadow-lg">
                    <div class="container mx-auto space-y-3 px-4 py-4">
                        <a href="{{ route('home') }}" class="block rounded-xl px-3 py-2 text-stone-600 hover:bg-stone-100 hover:text-stone-900">首頁</a>
                        <a href="{{ route('nearby') }}" class="block rounded-xl px-3 py-2 text-stone-600 hover:bg-stone-100 hover:text-stone-900">附近門市</a>
                        <a href="{{ route('tags') }}" class="block rounded-xl px-3 py-2 text-stone-600 hover:bg-stone-100 hover:text-stone-900">分類標籤</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
