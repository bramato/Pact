@extends('pact::layout')
@section('title', 'Scegli la squadra')
@section('content')
<h1>Dove mettiamo l’agente?</h1>
<p class="intro">Hai autorizzato l’agente su <strong class="domain">{{ $application->url }}</strong>. Scegli la squadra in cui potrà collaborare.</p>
<form class="card" method="post" action="{{ route('pact.connect.finish', ['link' => $pending->id]) }}">
    @csrf
    <label for="team_id">Squadra</label>
    <select id="team_id" name="team_id">@foreach($teams as $team)<option value="{{ $team['id'] }}" @selected(old('team_id') === $team['id'])>{{ $team['name'] }}</option>@endforeach</select>
    <label for="new_team">Oppure crea una nuova squadra</label>
    <input id="new_team" name="new_team" maxlength="100" value="{{ old('new_team') }}" placeholder="Nome della nuova squadra" aria-describedby="new-team-help">
    <p id="new-team-help" class="muted">Se inserisci un nome, l’agente verrà aggiunto alla nuova squadra.</p>
    <div class="actions"><button type="submit">Collega alla squadra</button></div>
</form>
<form method="post" action="{{ route('pact.connect.cancel', ['link' => $pending->id]) }}">@csrf<button class="secondary" type="submit">Annulla collegamento</button></form>
@endsection
