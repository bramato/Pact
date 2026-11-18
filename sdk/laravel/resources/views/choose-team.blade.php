@extends('pact::layout')
@section('title', 'Scegli la squadra')
@section('context', 'Ritorno al coordinatore')
@section('content')
<div class="pact-grid">
    <section aria-labelledby="pact-title">
        <span class="pact-eyebrow">03 / Trova il suo posto</span>
        <h1 id="pact-title">Dove mettiamo <em>l’agente?</em></h1>
        <p class="pact-lead">Hai autorizzato l’agente su <strong class="pact-domain">{{ $application->url }}</strong>. Scegli la squadra in cui potrà collaborare, oppure creane una nuova.</p>
        @include('pact::partials.steps', ['step' => 3])
    </section>
    <div>
        <form class="pact-panel" method="post" action="{{ route('pact.connect.finish', ['link' => $pending->id]) }}">
            @csrf
            <p class="pact-panel-title">Una squadra con cui lavorare</p>
            <label for="team_id">Squadra</label>
            <select id="team_id" name="team_id" @if($errors->has('team_id')) aria-invalid="true" aria-describedby="team-error" @endif>@foreach($teams as $team)<option value="{{ $team['id'] }}" @selected(old('team_id') === $team['id'])>{{ $team['name'] }}</option>@endforeach</select>
            @error('team_id')<p id="team-error" class="pact-field-error">{{ $message }}</p>@enderror
            <label for="new_team">Oppure crea una nuova squadra</label>
            <input id="new_team" name="new_team" maxlength="100" value="{{ old('new_team') }}" placeholder="Nome della nuova squadra" aria-describedby="new-team-help{{ $errors->has('new_team') ? ' new-team-error' : '' }}" @if($errors->has('new_team')) aria-invalid="true" @endif>
            <p id="new-team-help" class="pact-hint">Se inserisci un nome, l’agente verrà aggiunto alla nuova squadra.</p>
            @error('new_team')<p id="new-team-error" class="pact-field-error">{{ $message }}</p>@enderror
            <div class="pact-actions"><button class="pact-button" type="submit">Collega alla squadra @include('pact::partials.icon', ['name' => 'arrow-right'])</button></div>
        </form>
        <form class="pact-actions" method="post" action="{{ route('pact.connect.cancel', ['link' => $pending->id]) }}">@csrf<button class="pact-button pact-button-secondary" type="submit">Annulla collegamento</button></form>
    </div>
</div>
@endsection
