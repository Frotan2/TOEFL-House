<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Placement Decision System — The TOEFL House</title>
    @vite('resources/js/product-theme.css')
    <link rel="stylesheet" href="{{ asset('css/toefl-house-ultimate.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toefl-house-route-state.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toefl-house-placement.css') }}">
    @viteReactRefresh
    @vite(['resources/js/placement.tsx'])
</head>
<body>
<div id="placement-console" data-api-base="{{ url('/api/v1') }}" data-csrf-token="{{ csrf_token() }}"></div>
</body>
</html>
