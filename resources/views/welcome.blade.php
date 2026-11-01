<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="item-max-quantity" content="{{ (int) config('app.max_item_quantity', 100) }}">
    <title>Item Manager</title>

    <!-- 字體 -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet"/>

    <!-- Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class=" bg-[#f5f5f5] text-black min-h-screen">

<div id="app"></div>

</body>
</html>
