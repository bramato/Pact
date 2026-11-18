<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#075bc7">
    <title>@yield('title', 'Agenti') · PACT</title>
    @include('pact::partials.styles')
</head>
<body class="pact-ui">
<a class="pact-skip" href="#pact-content">Vai al contenuto</a>
<div class="pact-wrap">
    <header class="pact-masthead">
        @if(config('pact.role') === 'master')
            <a class="pact-brand" href="{{ route('pact.agents') }}" aria-label="PACT — I tuoi agenti">@include('pact::partials.brand')</a>
        @else
            <div class="pact-brand">@include('pact::partials.brand')</div>
        @endif
        <p class="pact-caption">Protocol for Agent<br>Coordination and Tasks</p>
    </header>
    <main id="pact-content" class="pact-paper" tabindex="-1">
        <div class="pact-page-rule"><span>PACT / Connessioni</span><span>@yield('context', 'La tua squadra di agenti')</span></div>
        @if(session('pact.status'))<p class="pact-notice" role="status">{{ session('pact.status') }}</p>@endif
        @if($errors->any())<p class="pact-notice pact-notice-error" role="alert">Controlla i campi indicati per continuare.</p>@endif
        @yield('content')
    </main>
    <footer class="pact-footer"><p>Agenti diversi. Una squadra che lavora insieme.</p><p>Protocol for Agent Coordination and Tasks</p></footer>
</div>
</body></html>
