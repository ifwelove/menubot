@extends('layouts.app')

@section('title', ($keyword ? $keyword . ' 搜尋結果' : '搜尋店家') . ' - 飲料店菜單查詢')
@section('description', '搜尋飲料店品牌與菜單')

@section('content')
<div class="container mx-auto px-4 py-8">
    <nav class="mb-6">
        <a href="{{ route('home') }}" class="text-gray-500 hover:text-gray-700">
            返回首頁
        </a>
    </nav>

    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">搜尋結果</h1>
        @if($keyword)
            <p class="text-gray-600">關鍵字「{{ $keyword }}」共找到 {{ count($results) }} 家店</p>
        @else
            <p class="text-gray-600">請輸入關鍵字搜尋品牌</p>
        @endif
    </div>

    @if(count($results) > 0)
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6">
            @foreach($results as $code => $name)
                @include('components.shop-card', [
                    'code' => $code,
                    'name' => $name,
                    'image' => null,
                ])
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <p class="text-gray-500">找不到符合的店家</p>
        </div>
    @endif
</div>
@endsection
