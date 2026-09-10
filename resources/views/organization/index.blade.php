<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Organization &amp; Configuration — The TOEFL House</title>
    @vite('resources/js/product-theme.css')
    <link rel="stylesheet" href="{{ asset('css/toefl-house-ultimate.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toefl-house-route-state.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toefl-house-operations.css') }}">
    @viteReactRefresh
    @vite(['resources/js/organization.tsx'])
</head>
<body>
<div id="organization-console" data-api-base="{{ url('/api/v1') }}" data-csrf-token="{{ csrf_token() }}"></div>
</body>
</html>