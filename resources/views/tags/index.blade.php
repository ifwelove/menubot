@extends('layouts.app')

@section('title', '飲料標籤分類 - 飲料店菜單查詢')
@section('description', '依照飲料類型分類查詢飲料店')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="text-center mb-12">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">飲料標籤分類</h1>
        <p class="text-gray-600">依照飲料類型快速找到你想要的店家</p>
    </div>

    <!-- Tags Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach($tags as $tagName => $shops)
            <a href="{{ route('tags.shops', urlencode($tagName)) }}" class="group">
                <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-lg hover:-translate-y-1 transition-all">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2 group-hover:text-gray-600">{{ $tagName }}</h3>
                    <p class="text-sm text-gray-500">{{ $tagCounts[$tagName] ?? 0 }} 家店</p>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
