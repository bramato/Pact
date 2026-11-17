@extends('pact::layout')
@section('title', 'Collega agente')
@section('content')
<h1>Collega agente</h1>
<p class="intro">Inserisci il dominio dell’applicazione che ospita il tuo agente. Accederai su quel server per autorizzare il collegamento, poi sceglierai la squadra.</p>
<form class="card" method="post" action="{{ route('pact.connect.start') }}">
    @csrf
    <label for="domain">Dominio del server</label>
    <input id="domain" name="domain" type="text" inputmode="url" autocomplete="url" placeholder="agente.example.com" value="{{ old('domain', $domain) }}" maxlength="2048" aria-describedby="domain-help" required autofocus>
    <p id="domain-help" class="muted">Puoi inserire il dominio o l’indirizzo completo, ad esempio https://agente.example.com.</p>
    <div class="actions"><button type="submit">Continua sul server</button><a href="{{ route('pact.agents') }}">Annulla</a></div>
</form>
@endsection
