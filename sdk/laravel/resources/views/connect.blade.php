@extends('pact::layout')
@section('title', 'Collega agente')
@section('context', 'Collegamento in uscita')
@section('content')
<div class="pact-grid">
    <section aria-labelledby="pact-title">
        <span class="pact-eyebrow">01 / Inizia dal server</span>
        <h1 id="pact-title">Collega <em>agente.</em></h1>
        <p class="pact-lead">Un nuovo agente entra nella tua squadra. Inserisci il dominio dell’applicazione che lo ospita: accederai su quel server, poi tornerai qui per scegliere dove aggiungerlo.</p>
        @include('pact::partials.steps', ['step' => 1])
    </section>
    <form class="pact-panel" method="post" action="{{ route('pact.connect.start') }}">
        @csrf
        <p class="pact-panel-title">L’indirizzo da cui partire</p>
        <label for="domain">Dominio del server</label>
        <input id="domain" name="domain" type="text" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" placeholder="agente.example.com" value="{{ old('domain', $domain) }}" maxlength="2048" aria-describedby="domain-help{{ $errors->has('domain') ? ' domain-error' : '' }}" @if($errors->has('domain')) aria-invalid="true" @endif required>
        <p id="domain-help" class="pact-hint">Puoi inserire il dominio o l’indirizzo completo, ad esempio https://agente.example.com.</p>
        @error('domain')<p id="domain-error" class="pact-field-error">{{ $message }}</p>@enderror
        <div class="pact-actions"><button class="pact-button" type="submit">Continua sul server @include('pact::partials.icon', ['name' => 'arrow-right'])</button><a class="pact-text-link" href="{{ route('pact.agents') }}">Annulla</a></div>
        <p class="pact-hint">L’accesso al tuo account avviene sul sito che ospita l’agente.</p>
    </form>
</div>
@endsection
