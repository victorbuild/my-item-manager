<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Item Manager</title>

    <!-- 字體 -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet"/>

    <!-- Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class=" bg-[#f5f5f5] text-black p-4 min-h-screen">

<!-- 登入導覽 -->
<header class="mb-4">
    @if (Route::has('login'))
        <nav class="flex justify-end gap-2 text-sm">
            @auth
                <a href="{{ url('/dashboard') }}" class="text-blue-600 underline">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-blue-600 underline">登入</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="text-blue-600 underline">註冊</a>
                @endif
            @endauth
        </nav>
    @endif
</header>

<!-- Vue 掛載點 -->
<main id="app" class="max-w-md mx-auto"></main>

</body>
</html>
