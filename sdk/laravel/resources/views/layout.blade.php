<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Agenti') · PACT</title>
    <style>
        :root { color-scheme: light; --ink:#24382f; --paper:#f7f4ec; --line:#d5d9ce; --green:#245c43; }
        * { box-sizing:border-box; } body { margin:0; background:var(--paper); color:var(--ink); font:16px/1.65 system-ui,sans-serif; }
        main { max-width:860px; margin:0 auto; padding:48px 24px 72px; } header { display:flex; align-items:center; justify-content:space-between; gap:16px; border-bottom:1px solid var(--line); padding-bottom:22px; margin-bottom:42px; }
        .brand { letter-spacing:.18em; font-weight:800; text-decoration:none; } a { color:var(--green); } h1 { font:clamp(2rem,6vw,3rem)/1.15 Georgia,serif; margin:0 0 20px; } h2 { font:1.5rem/1.3 Georgia,serif; margin:0; }
        .intro { max-width:60ch; color:#526257; margin-bottom:32px; } .card { background:#fffdf8; border:1px solid var(--line); border-radius:14px; padding:24px; margin:20px 0; } label { display:block; font-weight:650; margin:18px 0 8px; }
        input,select { width:100%; max-width:100%; padding:13px 14px; border:1px solid #88968b; border-radius:7px; font:inherit; background:white; color:var(--ink); }
        button,.button { display:inline-block; border:1px solid var(--green); border-radius:7px; background:var(--green); color:white; padding:12px 20px; font:650 1rem/1.5 system-ui,sans-serif; text-decoration:none; cursor:pointer; }
        .secondary { background:transparent; color:var(--green); } .actions { display:flex; flex-wrap:wrap; align-items:center; gap:14px; margin-top:24px; } .muted,small { color:#526257; } .notice { padding:14px 18px; border-left:3px solid var(--green); background:#e9efe6; } .error { color:#8a2920; background:#fff1ec; border-color:#8a2920; } :focus-visible { outline:3px solid #b16c29; outline-offset:4px; }
        .row { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:16px; } .domain { overflow-wrap:anywhere; } ul { padding-left:22px; } fieldset { border:0; padding:0; margin:0; } legend { font-weight:650; } @media(max-width:500px) { main{padding:24px 18px;} .card{padding:18px;} header{margin-bottom:30px;} .actions button,.actions .button{width:100%;text-align:center;} }
    </style>
</head>
<body><main>
<header><a class="brand" href="{{ route('pact.agents') }}">PACT</a><span class="muted">La tua squadra di agenti</span></header>
@if(session('pact.status'))<p class="notice" role="status">{{ session('pact.status') }}</p>@endif
@if($errors->any())<div class="notice error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main></body></html>
