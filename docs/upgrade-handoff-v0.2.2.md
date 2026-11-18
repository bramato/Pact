# Handoff — aggiornare un’applicazione a PACT 0.2.2

Copia questo prompt nella chat dell’assistente che lavora sulla repository Laravel da aggiornare. È valido sia per il master sia per un client; va applicato a entrambe le installazioni.

```text
Aggiorna questa applicazione Laravel ai pacchetti PACT 0.2.2, integrando il collegamento degli agenti avviato dal master con il solo dominio e il tema condiviso del sito PACT in uscita, consenso e ritorno.

Leggi AGENTS.md e le istruzioni del progetto, poi consulta:
- https://github.com/bramato/Pact/blob/v0.2.2/docs/laravel-installation.md
- https://github.com/bramato/Pact/blob/v0.2.2/specification/PACT-LARAVEL-BINDING-v0.1.md
- https://github.com/bramato/Pact/blob/v0.2.2/CHANGELOG.md
- https://github.com/bramato/Pact/blob/v0.2.2/docs/connection-interface.md

1. Individua il ruolo PACT già installato (master o client), il login, il database, il guard, il provider, l’eventuale gestione tenant, il worker e le viste/configurazioni pubblicate. In locale usa Herd sul dominio della cartella seguito da .test, con il suo schema HTTP/HTTPS e APP_URL coerente.

2. Aggiorna i pacchetti dalla distribuzione Composer:
composer require pact-protocol/laravel:^0.2.2 pact-protocol/core:^0.2.2 --with-all-dependencies
Controlla composer show pact-protocol/laravel e composer show pact-protocol/core e il codice realmente caricato da vendor. Se override Composer path/VCS impediscono la versione pubblicata, aggiorna i soli override PACT necessari, preservando le altre dipendenze. Mantieni composer.json e composer.lock nel commit. Il protocollo Core resta 0.2.0: 0.2.2 è la versione dei pacchetti.

3. Conserva ruolo, database, APP_KEY, chiavi Passport e storage/app/pact. Dopo l’aggiornamento esegui:
php artisan config:clear
php artisan pact:install --no-interaction
Questo applica le nuove migrazioni conservando identità e connessioni esistenti. Confronta config e viste pubblicate con quelle aggiornate del pacchetto, mantenendo le personalizzazioni. Ricostruisci le cache previste dal progetto e riavvia il processo pact:work secondo la configurazione esistente. Esegui pact:doctor e risolvi le anomalie effettive.

Prima di modificare le viste, confronta gli override locali con layout e partial del pacchetto. Riusa pact::layout, pact::partials.styles, brand, icon e steps. Mantieni tenant, CSRF, auth_token, callback e route esistenti. Per CSP restrittive passa pactStyleNonce e autorizza solo quel nonce per gli stili e img-src data: per le texture. Non pubblicare con --force sopra viste personalizzate. Verifica consenso, azienda non disponibile, annullamento e ritorno, inclusi i fallback senza JavaScript, a 320/390/430/768 px.

4. Se è un MASTER: aggiungi in navigazione route('pact.agents') o route('pact.connect'). Il pulsante «Collega agente» chiede solo il dominio, registra automaticamente il master sul server remoto e avvia OAuth. Dopo il consenso remoto l’utente torna al master, sceglie una propria squadra o ne crea una; il worker completa l’attivazione nelle due direzioni. Per un’interfaccia personalizzata usa Pact::connectDomain(); passa il team ID autorizzato alle operazioni della facade quando lavori fuori dalla squadra personale.

5. Se è un CLIENT: verifica discovery /.well-known/pact, registrazione automatica e riuso del login esistente. La scelta di account e tenant avviene nel client; integra pact.auth.middleware e IdentityResolver quando necessario per un’identità stabile che includa il contesto autorizzato. Verifica che AgentHandler esegua l’agente reale con i permessi di context->userId e che i contratti configurati corrispondano alle sue capacità.

6. Aggiorna anche l’altro peer prima del collaudo. Verifica il percorso completo: dominio dal master, login/consenso sul client, ritorno, scelta della squadra, stato pending e attivazione confermata dal worker. Prova un incarico master → agente e una chiamata autenticata agente → roster del master. Controlla isolamento tra utenti e squadre, consenso annullato, collegamento scaduto, reinvio della scelta senza duplicati, cambio tenant prima dell’approvazione, rinnovo e disconnessione. Un agente di un account remoto appartiene a una squadra per master; per spostarlo attendi prima la conferma della disconnessione.

7. Esegui i controlli pertinenti al progetto e crea commit locali coerenti, rispettando il workflow Git della repository. Consegna versioni installate, ruolo rilevato, modifiche, verifiche eseguite e qualsiasi collaudo in attesa dell’altro peer o del consenso dell’utente. Mantieni token e chiavi fuori dal resoconto.
```
