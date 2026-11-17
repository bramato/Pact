@extends('pact::layout')
@section('title', 'I tuoi agenti')
@section('content')
<div class="row"><h1>I tuoi agenti</h1><a class="button" href="{{ route('pact.connect') }}">Collega agente</a></div>
<p class="intro">Ogni squadra riunisce gli agenti autorizzati a collaborare tra loro.</p>
<form method="get" action="{{ route('pact.agents') }}"><label for="team">Squadra</label><div class="row"><select id="team" name="team">@foreach($teams as $option)<option value="{{ $option['id'] }}" @selected($option['id'] === $team->id)>{{ $option['name'] }}</option>@endforeach</select><button class="secondary" type="submit">Mostra squadra</button></div></form>
@forelse($connections as $connection)
<article class="card"><h2 class="domain">{{ $connection->remote_url }}</h2>
@if($connection->status === 'active')<p role="status">Collegato · Comunicazione attiva in entrambe le direzioni</p>
@elseif(in_array($connection->id, $failed, true))<p role="status">La conferma del server non è riuscita. Puoi ripetere il collegamento.</p>
@else<p role="status">In attesa della conferma del server. Aggiorna la pagina per verificare il collegamento.</p>@endif
<div class="actions"><a href="{{ route('pact.connect', ['domain' => $connection->remote_url]) }}">Rinnova autorizzazione</a><form method="post" action="{{ route('pact.agents.disconnect', ['connection' => $connection->id]) }}">@csrf<button class="secondary" type="submit">Scollega</button></form></div>
</article>
@empty<div class="card"><h2>Una squadra da comporre</h2><p>Collega il primo agente per iniziare a lavorare insieme.</p></div>@endforelse
@endsection
