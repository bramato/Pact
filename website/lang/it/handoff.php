<?php

return [
    'heading' => 'Handoff per Laravel.',
    'intro' => 'Scegli la versione del pacchetto da installare, poi copia l’handoff master o client nella chat dell’assistente che lavora sul tuo progetto.',
    'versions' => 'Versioni del pacchetto',
    'changes' => 'Novità della :version',
    'protocol' => 'Protocollo Core :version',
    'references' => 'Documentazione della versione',
    'documents' => [
        'guide' => 'Installazione Laravel',
        'core' => 'Specifica Core',
        'binding' => 'Specifica del binding Laravel',
        'question_forms' => 'Moduli per le domande v1',
        'theme' => 'Guida all’interfaccia di collegamento',
        'upgrade' => 'Handoff di aggiornamento',
        'changelog' => 'Changelog',
    ],
    'preview' => 'Leggi l’handoff completo',
    'copied' => 'Handoff copiato. Incollalo nella chat del progetto.',
    'copy_error' => 'La copia automatica non è disponibile. Il testo è selezionato per la copia manuale.',
    'prompt' => <<<'HANDOFF'
Integra PACT :version in questa applicazione Laravel con ruolo :role. Usa il tag sorgente v:version di bramato/Pact. La versione del protocollo Core è :protocol.

:goal

Leggi la documentazione di questa versione:
:sources

Verifica le versioni di Laravel e PHP, il database, l’autenticazione, l’agente e gli strumenti presenti nel progetto. Riusa login, guard e provider dell’applicazione. In locale mantieni in APP_URL il dominio Herd .test e il suo schema HTTP o HTTPS. Per i peer pubblici usa HTTPS.

Installa le versioni esatte riportate qui sotto, anche se una guida collegata usa un esempio Composer diverso:
composer require pact-protocol/laravel::version pact-protocol/core::version --with-all-dependencies

Verifica entrambe le versioni con composer show e conserva composer.json e composer.lock nel progetto. Se un override Composer path o VCS interferisce, confrontalo con il tag selezionato. Per lavorare dal sorgente fai il checkout di v:version, esegui lì npm ci --ignore-scripts e npm run build:sdk e imposta la versione :version per entrambi i pacchetti Composer path.

Verifica database, APP_KEY e APP_URL. Per una nuova installazione esegui:
php artisan config:clear
php artisan pact:install --role=:role --no-interaction

Per i peer HTTP locali aggiungi un --allow-local-host per ciascun host, incluse entrambe le applicazioni. Se PACT è già installato, svuota la cache di configurazione e riesegui php artisan pact:install --no-interaction dopo aver aggiornato i pacchetti. Conserva il database, APP_KEY, le chiavi Passport e storage/app/pact. Confronta le configurazioni e le viste pubblicate con quelle della versione scelta. Esegui php artisan pact:doctor, ricostruisci le cache di configurazione e route e avvia php artisan pact:work sotto un supervisore. Usa pact:work --once per una verifica puntuale.

:linking

:execution

:release_instructions

:verification

Riporta l’integrazione realizzata, i test eseguiti, la configurazione del worker e le verifiche ancora in attesa di un peer o del consenso dell’utente. Tieni token e chiavi private fuori dal resoconto.
HANDOFF,
    'linking' => [
        'manual' => [
            'master' => <<<'HANDOFF'
Chiedi al client di eseguire php artisan pact:oauth-client --master=https://master.example. Registra sul master il client ID pubblico ottenuto e avvia il collegamento:
php artisan pact:application:add --name=documents --url=https://documents.example --client-id=THE_CLIENT_ID
php artisan pact:connect documents --user=ID_UTENTE_MASTER

Usa l’URL restituito o Pact::connectUrl('documents') da una pagina autenticata del master. L’utente accede al client e concede il consenso. Attendi che il worker confermi l’attivazione nella federazione personale del master.
HANDOFF,
            'client' => <<<'HANDOFF'
Registra il master sul client con php artisan pact:oauth-client --master=https://master.example. Consegna al master l’origine di questa applicazione e il client ID pubblico restituito, da usare con pact:application:add e pact:connect. Riusa il login e le chiavi Passport del client per il consenso. Il worker completa l’attivazione nella federazione personale del master.
HANDOFF,
        ],
        'domain' => [
            'master' => <<<'HANDOFF'
Avvia il collegamento dal master tramite route('pact.agents'), route('pact.connect') o Pact::connectDomain(). L’utente sceglie «Collega agente» e inserisce il dominio del client. Il master legge /.well-known/pact e registra automaticamente il proprio client OAuth pubblico. Da CLI usa php artisan pact:connect DOMINIO_CLIENT --domain --user=ID_UTENTE_MASTER.

L’utente accede al client, concede il consenso e torna al master per scegliere o creare una squadra di cui è proprietario. Entrambi i peer usano lo stesso pact.prefix. Mostra gli stati di attivazione in attesa, fallita e confermata. Attendi che il worker confermi le credenziali in entrambe le direzioni prima di inviare lavoro. Conferma la revoca prima di spostare un agente remoto in un’altra squadra.

Usa Pact::teams() e createTeam() e passa la squadra selezionata a members(), sendMessage(), task(), answer() e cancel(). Se ometti la squadra viene usata quella personale.
HANDOFF,
            'client' => <<<'HANDOFF'
Verifica /.well-known/pact e la registrazione automatica del client OAuth pubblico. Consegna al master il dominio di questa applicazione. L’utente avvia lì il collegamento, accede qui e concede il consenso, poi torna al master per scegliere una squadra. I peer usano lo stesso pact.prefix. Il worker del master conferma le credenziali in entrambe le direzioni.

Per la scelta del tenant prima del consenso, usa il flusso autorizzato dell’applicazione in pact.auth.middleware. Collega IdentityResolver a un soggetto stabile per utente locale e tenant e richiedi un nuovo consenso se l’identità cambia prima dell’approvazione.
HANDOFF,
        ],
    ],
    'roles' => [
        'master' => [
            'title' => 'Master',
            'description' => 'Collega gli agenti, assegna incarichi e consulta i risultati nell’applicazione che coordina.',
            'copy' => 'Copia handoff master',
            'goal' => 'Permetti a ogni utente autenticato di collegare agenti remoti, inviare incarichi e consultare domande, avanzamento e risultati validati.',
            'execution' => <<<'HANDOFF'
Usa PactLaravel\Facades\Pact per elencare i membri, inviare lavoro, leggere gli incarichi, rispondere alle domande e annullare le richieste. Limita ogni operazione all’identità locale autorizzata e alle sue squadre o alla sua federazione personale, secondo la versione installata. Riusa le chiavi di idempotenza nei retry e valida gli artefatti rispetto al contratto negoziato. Segui la specifica del binding per le chiamate autenticate al roster e a MCP.
HANDOFF,
            'verification' => <<<'HANDOFF'
Verifica consenso, attivazione confermata, un incarico autenticato e una chiamata autenticata dal client al roster del master. Prova l’accesso da un altro utente, il consenso negato o scaduto, le richieste duplicate, domande e risposte, annullamento e revoca con php artisan pact:disconnect CONNECTION_ID --user=ID_UTENTE_MASTER.
HANDOFF,
        ],
        'client' => [
            'title' => 'Client',
            'description' => 'Collega l’agente e gli strumenti che girano sul server della tua applicazione.',
            'copy' => 'Copia handoff client',
            'goal' => 'Esponi un agente reale sul server del client per ogni account autorizzato. Il pacchetto PACT gestisce il collegamento e la consegna degli incarichi e include un handler diagnostico. L’applicazione client deve fornire il proprio agente, la propria connessione al modello e i propri strumenti.',
            'execution' => <<<'HANDOFF'
Riusa l’agente lato server dell’applicazione se esiste. Altrimenti crealo nel progetto client. Scegli un provider di modelli e un SDK compatibili con il progetto, configura il modello e le sue credenziali in una configurazione accessibile solo al server o in un secret manager e scrivi le istruzioni per un incarico concreto. Registra pochi strumenti lato server con schemi di input espliciti. Inizia con uno strumento in sola lettura, per esempio la ricerca di un documento a cui l’utente ha accesso. Autorizza ogni chiamata in base a utente e tenant locali e prova l’agente direttamente prima di collegarlo a PACT.

Implementa PactLaravel\Contracts\AgentHandler con handle(PactLaravel\Execution\AgentContext $context): PactLaravel\Execution\AgentResult. Risolvi l’utente locale da $context->userId, passa all’agente $context->input e gli eventuali $context->answers e $context->checkpoint e mappa l’esito su AgentResult::completed(), question(), waiting() o error(). Usa $context->invocationId per rendere idempotenti gli effetti dei tool.

Imposta handler, contract_path e contract_registry in config/pact.php. Registra localmente gli schemi di input, output e risposta con il digest dei byte esatti. Riusa il contratto agent-chat incluso se adatto. ConversationReader nega l’accesso per impostazione predefinita. Un’implementazione personalizzata deve verificare i partecipanti effettivi.
HANDOFF,
            'verification' => <<<'HANDOFF'
Usa prima il contratto diagnostico per verificare la connettività. Poi invia un incarico reale dal master e verifica che l’agente client chiami uno strumento autorizzato e restituisca un artefatto valido. Prova isolamento tra utenti e tenant, retry idempotenti, domande e ripresa, accesso al roster con le credenziali di ritorno e revoca con php artisan pact:disconnect CONNECTION_ID --user=ID_UTENTE_CLIENT. Consegna il dominio del client e le capacità effettivamente disponibili al master.
HANDOFF,
        ],
    ],
];
