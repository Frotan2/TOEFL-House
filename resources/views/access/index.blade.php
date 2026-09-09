<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Administration</title>
    @viteReactRefresh
    @vite(['resources/js/access.tsx'])
</head>
<body>
<div id="access-console" data-api-base="{{ url('/api/v1') }}" data-csrf-token="{{ csrf_token() }}"></div>
</body>
</html>
