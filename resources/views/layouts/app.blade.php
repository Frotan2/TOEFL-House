<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <base href="{{ url('/') }}/">
    <title>@yield('title', 'Console') — The TOEFL House</title>
    @vite('resources/js/product-theme.css')
    <link rel="stylesheet" href="{{ asset('css/toefl-house-ultimate.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toefl-house-route-state.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toefl-house-operations.css') }}">
    <link rel="stylesheet" href="{{ asset('css/toefl-house-legacy-operations.css') }}">
    <style>
        :root {
            --ink:#162033; --muted:#62718a; --line:#dde5ef; --surface:#fff; --bg:#f8fafc;
            --brand:#163b69; --brand-2:#2d6eaf; --accent:#e6a921; --danger:#a6332c; --ok:#1c7a4c;
            --soft:#f1f5f9; --radius:14px; --shadow:0 8px 24px rgba(15,23,42,.05);
        }
        *{box-sizing:border-box}
        html{min-height:100%;scroll-behavior:smooth}
        body{margin:0;min-height:100%;background:radial-gradient(circle at top left,rgba(49,114,187,.04),transparent 26rem),var(--bg);color:var(--ink);font:14px/1.55 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;text-rendering:optimizeLegibility}
        a{color:var(--brand-2);text-decoration:none;text-underline-offset:3px}
        a:hover{color:var(--brand);text-decoration:underline}
        :focus-visible{outline:3px solid rgba(76,143,227,.35);outline-offset:3px}
        header.top{position:sticky;top:0;z-index:40;background:rgba(255,255,255,.94);border-bottom:1px solid var(--line);backdrop-filter:blur(18px)}
        header.top .bar{max-width:1320px;margin:0 auto;min-height:72px;padding:10px 24px;display:flex;align-items:center;gap:16px}
        header.top .brand{display:inline-flex;align-items:center;gap:9px;color:var(--ink);font-size:14px;font-weight:800;white-space:nowrap}
        header.top .brand::before{content:'T';display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;background:linear-gradient(145deg,#163b69,#3172bb);color:#fff;font-size:18px;font-weight:850}
        header.top .brand span{color:var(--accent)}
        nav.main{display:flex;gap:3px;flex:1;overflow-x:auto;scrollbar-width:none;padding:4px 0}
        nav.main::-webkit-scrollbar{display:none}
        nav.main a{padding:8px 10px;border-radius:9px;color:#52627a;font-size:12px;white-space:nowrap}
        nav.main a:hover{background:#f1f5f9;text-decoration:none;color:var(--ink)}
        header.top .who{margin-left:auto;display:flex;align-items:center;gap:10px;color:#62718a;font-size:12px;white-space:nowrap}
        header.top .who button{border:1px solid #cbd6e4;border-radius:9px;background:transparent;color:#33445d;padding:8px 10px;cursor:pointer}
        header.top .who button:hover{background:#f1f5f9}
        main{max-width:1320px;margin:0 auto;padding:30px 24px 64px}
        .card{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:20px 22px;margin-bottom:18px;box-shadow:0 1px 2px rgba(15,23,42,.03)}
        .card:hover{box-shadow:var(--shadow)}
        .card h1{font-size:26px;letter-spacing:-.03em;margin:0 0 5px}.card h2{font-size:17px;margin:0 0 12px}.sub{color:var(--muted);font-size:13px;margin:0 0 16px}
        table.grid{width:100%;border-collapse:separate;border-spacing:0;font-size:13px;border:1px solid var(--line);border-radius:12px;overflow:hidden}
        table.grid th,table.grid td{text-align:left;padding:11px 12px;border-bottom:1px solid var(--line);vertical-align:top}
        table.grid th{background:#f8fafc;color:#6d7b91;font-size:11px;text-transform:uppercase;letter-spacing:.08em}
        table.grid tr:last-child td{border-bottom:0}.pill{display:inline-flex;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:800;background:#eef3f8;color:#33445d}.pill.ok{background:#eaf7f0;color:#1c7a4c}.pill.held{background:#fff0ee;color:#a6332c}.pill.warn{background:#fff6df;color:#8d6500}
        label{display:block;color:#51627a;font-size:11px;font-weight:800;margin:12px 0 5px}input,select,textarea{width:100%;padding:10px 11px;min-height:42px;border:1px solid #cbd6e4;border-radius:9px;background:#fff;color:var(--ink);font:inherit}textarea{min-height:96px;resize:vertical}input:focus,select:focus,textarea:focus{outline:none;border-color:#3172bb;box-shadow:0 0 0 4px rgba(49,114,187,.1)}
        .row{display:flex;gap:12px;flex-wrap:wrap}.row>div{flex:1 1 200px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;background:var(--brand-2);color:#fff;border:1px solid var(--brand-2);padding:8px 14px;border-radius:9px;font-size:13px;font-weight:750;cursor:pointer}.btn:hover{background:var(--brand);color:#fff;text-decoration:none}.btn.secondary{background:#f3f7fb;color:var(--brand);border-color:#cbd6e4}.btn.danger{background:var(--danger);border-color:var(--danger)}.btn.small{min-height:32px;padding:5px 9px;font-size:12px}
        .actions{margin-top:16px;display:flex;gap:9px;flex-wrap:wrap}.alert{padding:12px 14px;border-radius:11px;font-size:13px;margin-bottom:14px;border:1px solid}.alert.error{background:#fff0ee;color:var(--danger);border-color:#f2c7c3}.alert.ok{background:#eaf7f0;color:var(--ok);border-color:#c8e8d5}.field-error{color:var(--danger);font-size:12px;margin-top:4px}.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:end}.toolbar>div{flex:1 1 160px}.muted{color:var(--muted)}.empty{text-align:center;color:var(--muted);padding:34px 0}footer{max-width:1320px;margin:0 auto 34px;padding:0 24px;color:#78869a;font-size:11px}
        @media (max-width:900px){header.top .bar{align-items:flex-start;flex-wrap:wrap;padding:10px 16px}nav.main{order:3;width:100%}header.top .who{margin-left:0}main{padding:24px 16px 46px}}
        @media (max-width:620px){header.top .who span{display:none}.card{padding:17px}.row{flex-direction:column}.toolbar{flex-direction:column;align-items:stretch}footer{padding:0 16px}}
        @media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
    </style>
    @stack('head')
</head>
<body>
<header class="top">
    <div class="bar">
        <div class="brand">The <span>TOEFL</span> House</div>
        @auth
            <nav class="main">
                <a href="{{ route('home') }}">Home</a>
                <a href="{{ route('organization.index') }}">Organization</a>
                <a href="{{ route('identity.index') }}">Identity &amp; Access</a>
                <a href="{{ route('access.index') }}">Access</a>
                <a href="{{ route('students.index') }}">Students</a>
                <a href="{{ route('crm.index') }}">CRM</a>
                <a href="{{ route('placement.index') }}">Placement</a>
                <a href="{{ route('academic.index') }}">Academic</a>
                <a href="{{ route('teachers.index') }}">Teachers &amp; Faculty</a>
                <a href="{{ route('library.index') }}">Library</a>
                <a href="{{ route('finance.index') }}">Finance</a>
                <a href="{{ route('documents.index') }}">Documents</a>
                <a href="{{ route('privacy.index') }}">Privacy</a>
                <a href="{{ route('communication.index') }}">Communication</a>
                <a href="{{ route('payroll.index') }}">Payroll</a>
                <a href="{{ route('reporting.index') }}">Reporting</a>
                <a href="{{ route('audit.index') }}">Audit</a>
            </nav>
            <div class="who">
                <span>{{ auth()->user()->person?->legal_name ?? auth()->user()->username }}</span>
                <form method="POST" action="{{ route('logout') }}" style="display:inline">
                    @csrf
                    <button type="submit">Sign out</button>
                </form>
            </div>
        @endauth
    </div>
</header>
<main>
    @if (session('error'))
        <div class="alert error">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif
    @yield('content')
</main>
<footer>The TOEFL House — employee console. All operations are authorized, validated, and audited server-side.</footer>
</body>
</html>
