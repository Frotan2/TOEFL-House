<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">
    <title>{{ ($view ?? 'workspace') === 'crm' ? 'CRM — The TOEFL House' : (($view ?? 'workspace') === 'management' ? 'Management Workspace — The TOEFL House' : (($view ?? 'workspace') === 'students' ? 'Students & Admissions — The TOEFL House' : (($view ?? 'workspace') === 'academic' ? 'Academic Classes — The TOEFL House' : (($view ?? 'workspace') === 'teachers' ? 'Teacher & Faculty — The TOEFL House' : (($view ?? 'workspace') === 'reporting' ? 'Reporting & Dashboards — The TOEFL House' : 'Employee Workspace — The TOEFL House'))))) }}</title>
    @vite('resources/js/product-theme.css')
    <link rel="stylesheet" href="{{ asset('css/toefl-house-ultimate.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toefl-house-route-state.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toefl-house-placement.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toefl-house-operations.css') }}">
    @vite('resources/js/app.tsx')
</head>
<body>
    <div id="react-console" data-view="{{ $view ?? 'workspace' }}" data-students-view="{{ $students_view ?? 'directory' }}" data-student-id="{{ $student_id ?? '' }}" data-api-base="/api/v1" data-csrf-token="{{ csrf_token() }}"></div>
</body>
</html>
