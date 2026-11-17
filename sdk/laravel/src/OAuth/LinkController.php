<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Http\Request;
use PactLaravel\Contracts\IdentityResolver;
use PactLaravel\Models\Application;
use PactLaravel\Models\Connection;
use PactLaravel\Models\LinkRequest;
use PactLaravel\Models\Outbox;
use PactLaravel\Runtime\Store;

final class LinkController
{
    public function __construct(private readonly IdentityResolver $identities, private readonly Store $store, private readonly ConnectionService $connections) {}

    private function userId(): string
    {
        $user = $this->identities->current();
        abort_unless($user !== null, 401);
        $id = $this->identities->id($user);
        abort_unless($id !== '', 401);

        return $id;
    }

    public function index(Request $request): mixed
    {
        $user = $this->userId();
        $teams = $this->store->teamsFor($user);
        $teamId = $request->query('team', $teams[0]['id']);
        abort_unless(is_string($teamId), 422);
        $team = $this->store->teamFor($user, $teamId);
        $connections = Connection::query()->where('user_id', $user)->where('federation_id', $team->id)->where('status', '!=', 'revoked')->get();
        $failed = Outbox::query()->where('kind', 'activate')->where('state', 'failed')->get()
            ->filter(fn ($job) => $connections->contains(fn ($connection) => ($job->payload['connection_id'] ?? null) === $connection->id && ($job->payload['generation'] ?? null) === ($connection->metadata['generation'] ?? null)))
            ->map(fn ($job) => $job->payload['connection_id'])->all();

        return response()->view('pact::agents', compact('teams', 'team', 'connections', 'failed'))->header('Cache-Control', 'no-store');
    }

    public function create(Request $request): mixed
    {
        return response()->view('pact::connect', ['domain' => is_string($request->query('domain')) ? $request->query('domain') : ''])->header('Cache-Control', 'no-store');
    }

    public function start(Request $request): mixed
    {
        $data = $request->validate(['domain' => 'required|string|max:2048']);
        try {
            $url = $this->connections->connectDomain($data['domain'], $this->userId());
        } catch (\InvalidArgumentException|\DomainException $e) {
            return back()->withErrors(['domain' => $e->getMessage()])->withInput();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return back()->withErrors(['domain' => 'Il server non è raggiungibile. Verifica il dominio e riprova.'])->withInput();
        }

        return redirect()->away($url)->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function team(string $link): mixed
    {
        $pending = LinkRequest::query()->whereKey($link)->where('user_id', $this->userId())->firstOrFail();
        if ($pending->connection_id) {
            $connection = Connection::query()->whereKey($pending->connection_id)->where('user_id', $this->userId())->firstOrFail();

            return redirect()->route('pact.agents', ['team' => $connection->federation_id]);
        }
        abort_unless($pending->expires_at->isFuture() && is_array($pending->payload), 410, 'Collegamento scaduto. Riparti dal dominio del server.');
        $application = Application::query()->findOrFail($pending->application_id);
        $teams = $this->store->teamsFor($this->userId());

        return response()->view('pact::choose-team', compact('pending', 'application', 'teams'))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function finish(Request $request, string $link): mixed
    {
        $data = $request->validate(['team_id' => 'nullable|uuid|required_without:new_team', 'new_team' => 'nullable|string|max:100']);
        // Creating a team is an explicit alternative to selecting an existing one.
        $name = isset($data['new_team']) ? trim($data['new_team']) : null;
        $connection = $this->connections->finishLink($link, $this->userId(), $data['team_id'] ?? null, $name === '' ? null : $name);

        return redirect()->route('pact.agents', ['team' => $connection->federation_id])->with('pact.status', 'Autorizzazione ricevuta. Il collegamento è in attesa della conferma del server.');
    }

    public function cancel(string $link): mixed
    {
        $this->store->atomic(function () use ($link): void {
            $pending = LinkRequest::query()->whereKey($link)->where('user_id', $this->userId())->lockForUpdate()->firstOrFail();
            abort_if($pending->connection_id !== null, 409);
            $pending->update(['payload' => null, 'expires_at' => now()]);
        });

        return redirect()->route('pact.agents')->with('pact.status', 'Collegamento annullato.');
    }

    public function disconnect(string $connection): mixed
    {
        $record = Connection::query()->whereKey($connection)->where('user_id', $this->userId())->firstOrFail();
        $this->connections->disconnect($record->id, $this->userId());

        return redirect()->route('pact.agents', ['team' => $record->federation_id])->with('pact.status', 'Agente scollegato.');
    }
}
