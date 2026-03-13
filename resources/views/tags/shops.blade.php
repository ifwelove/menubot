@extends('layouts.app')

@section('title', $tagName . ' - 飲料店菜單查詢')
@section('description', '查看所有 ' . $tagName . ' 相關的飲料店')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Breadcrumb -->
    <nav class="mb-6">
        <div class="flex items-center gap-2 text-gray-500">
            <a href="{{ route('home') }}" class="hover:text-gray-700">首頁</a>
            <span>/</span>
            <a href="{{ route('tags') }}" class="hover:text-gray-700">標籤分類</a>
            <span>/</span>
            <span class="text-gray-800">{{ $tagName }}</span>
        </div>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">{{ $tagName }}</h1>
        <p class="text-gray-600">共 {{ count($shopList) }} 家店</p>
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

    @if(count($shopList) === 0)
        <div class="text-center py-12">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
            <p class="text-gray-500">目前沒有店家</p>
        </div>
    @endif
</div>
@endsection
