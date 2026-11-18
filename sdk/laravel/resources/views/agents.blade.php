@extends('pact::layout')
@section('title', 'I tuoi agenti')
@section('content')
<div class="pact-toolbar"><div><span class="pact-eyebrow">Capacità diverse, un lavoro condiviso</span><h1 id="pact-title">I tuoi <em>agenti.</em></h1></div><a class="pact-button" href="{{ route('pact.connect') }}">Collega agente @include('pact::partials.icon', ['name' => 'arrow-right'])</a></div>
<form class="pact-team-switch" method="get" action="{{ route('pact.agents') }}"><label for="team">Squadra</label><div class="pact-team-controls"><select id="team" name="team">@foreach($teams as $option)<option value="{{ $option['id'] }}" @selected($option['id'] === $team->id)>{{ $option['name'] }}</option>@endforeach</select><button class="pact-button pact-button-secondary" type="submit">Mostra squadra</button></div></form>
<ul class="pact-agent-list">
@forelse($connections as $connection)
<li><article class="pact-panel">
    @if($connection->status === 'active')<span class="pact-badge">@include('pact::partials.icon', ['name' => 'check']) Collegato</span>
    @elseif(in_array($connection->id, $failed, true))<span class="pact-badge">@include('pact::partials.icon', ['name' => 'alert']) Da riconnettere</span>
    @else<span class="pact-badge">@include('pact::partials.icon', ['name' => 'clock']) Attivazione in corso</span>@endif
    <h2 class="pact-agent-name pact-domain">{{ $connection->remote_url }}</h2>
    @if($connection->status === 'active')<p class="pact-status" role="status">Comunicazione attiva in entrambe le direzioni. L’agente può lavorare con questa squadra.</p>
    @elseif(in_array($connection->id, $failed, true))<p class="pact-status" role="status">La conferma del server non è riuscita. Puoi ripetere il collegamento.</p>
    @else<p class="pact-status" role="status">In attesa della conferma del server. Aggiorna la pagina per verificare il collegamento.</p>@endif
    <div class="pact-actions"><a class="pact-text-link" href="{{ route('pact.connect', ['domain' => $connection->remote_url]) }}">Rinnova autorizzazione</a><form method="post" action="{{ route('pact.agents.disconnect', ['connection' => $connection->id]) }}">@csrf<button class="pact-button pact-button-secondary" type="submit">Scollega</button></form></div>
</article></li>
@empty<li class="pact-empty"><h2>Una squadra da comporre.</h2><p class="pact-lead">Collega il primo agente per iniziare a lavorare insieme.</p></li>@endforelse
</ul>
@endsection
