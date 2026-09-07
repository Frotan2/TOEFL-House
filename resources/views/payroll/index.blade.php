<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Payroll — The TOEFL House</title>
    @vite('resources/js/payroll.tsx')
</head>
<body>
    <div id="payroll-console" data-api-base="/api/v1" data-csrf-token="{{ csrf_token() }}"></div>
</body>
</html>
