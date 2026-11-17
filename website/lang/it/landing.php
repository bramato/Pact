<?php

return [
    'meta' => [
        'title' => 'PACT — Mettersi d’accordo, prima di agire.',
        'description' => 'PACT è un protocollo per far collaborare agenti indipendenti: contratti espliciti, domande strutturate e risultati verificabili sopra A2A. Scopri come funziona.',
        'og_description' => 'Un accordo esplicito tra agenti. Dai dati in ingresso a un risultato verificabile, con contratti, chiarimenti e coordinamento sopra A2A.',
    ],
    'accessibility' => [
        'skip' => 'Vai al contenuto',
        'home' => 'PACT, inizio pagina',
        'logo' => 'PACT: un unico sticker con cerchio, triangolo e quadrato in orizzontale e il nome sotto.',
        'nav' => 'Navigazione principale',
        'language' => 'Lingua della pagina',
        'principles' => 'I tre principi',
        'steps' => 'Passaggi dell’esempio',
        'swarm' => 'Esempio di collaborazione: un coordinatore assegna estrazione, revisione e composizione, poi valida il risultato finale.',
    ],
    'nav' => [
        'protocol' => 'Il protocollo',
        'practice' => 'In pratica',
        'handoff' => 'Handoff',
    ],
    'edition' => 'Proposta indipendente · Bozza 0.2.0',
    'hero' => [
        'heading' => 'Mettersi<br>d’accordo,<br><em>prima di agire.</em>',
        'lead' => 'Gli agenti AI possono scambiarsi messaggi.<br> PACT li aiuta a condividere un incarico, risolvere i dubbi e consegnare un risultato verificabile.',
        'discover' => 'Scopri il protocollo',
        'spec' => 'Leggi la specifica',
        'figure' => 'FIG. 01 — UN ACCORDO CONDIVISO',
        'alt' => 'Illustrazione a retino su carta ritagliata: il robot PACT collega due sistemi e presenta un risultato verificato.',
        'caption' => 'Agenti diversi. Un contratto comune.',
        'bottom' => 'Contratti espliciti. Collaborazione verificabile.',
        'introduction' => 'Un’introduzione a PACT',
    ],
    'intro' => [
        'label' => '01 / Il punto di partenza',
        'kicker' => 'DAL MESSAGGIO ALL’INCARICO',
        'heading' => 'Capirsi è un inizio.<br><em>Accordarsi è il passo dopo.</em>',
        'paragraphs' => [
            'Chiedi a un agente di analizzare un documento. Lui risponde. Ma in quale formato? Con quali informazioni? E come stabilisci se ha finito davvero?',
            'Quando il lavoro passa da un sistema all’altro, queste domande diventano parte dell’incarico. PACT rende l’accordo esplicito: quali dati inviare, come chiedere ciò che manca e quale risultato accettare.',
            'Si appoggia ad <a href="https://a2a-protocol.org/">A2A, il protocollo Agent2Agent</a>, che fornisce scoperta degli agenti, messaggi, task e artefatti. PACT aggiunge le regole dell’accordo nei metadati di un’estensione negoziata. <strong>Lo stato del task resta quello nativo di A2A.</strong>',
        ],
        'principles' => [
            [
                'title' => 'Prima, il contratto.',
                'text' => 'Chi invia e chi esegue concordano la forma dei dati.',
            ],
            [
                'title' => 'Durante, il confronto.',
                'text' => 'Se manca qualcosa, una domanda precisa sblocca il lavoro.',
            ],
            [
                'title' => 'Alla fine, la verifica.',
                'text' => 'La consegna deve rispettare l’output concordato.',
            ],
        ],
    ],
    'contract' => [
        'label' => '02 / Il contratto',
        'heading' => 'La stessa idea<br>di <em>“fatto”.</em>',
        'paragraphs' => [
            'Un testo libero lascia spazio alle interpretazioni. Un contratto definisce invece la struttura esatta di ciò che entra e di ciò che deve uscire.',
            'Ogni contratto ha un’identità e una versione. Gli schemi JSON sono identificati anche dal loro digest SHA-256: entrambe le parti possono controllare di usare gli stessi byte, prima di validare i dati.',
            'Il risultato viene verificato rispetto allo schema concordato. <strong>La validità della struttura è controllabile; la qualità del contenuto resta responsabilità dell’applicazione.</strong>',
        ],
        'link' => 'Esplora PACT Core',
        'visual_heading' => 'UN ESEMPIO, IN PAROLE SEMPLICI',
        'input_title' => 'Questo è il documento.',
        'input_text' => 'Un testo da elaborare, nella struttura prevista.',
        'input_code' => '{ "text": "Il testo da analizzare…" }',
        'connection' => 'contratto condiviso',
        'output_title' => 'Questo è il risultato atteso.',
        'output_text' => 'Una lista di parole e un valore di approvazione.',
        'footnote' => 'Esempio semplificato della demo, non un messaggio wire completo.',
    ],
    'questions' => [
        'label' => '03 / I chiarimenti',
        'heading' => 'Una buona domanda<br><em>fa parte del lavoro.</em>',
        'paragraphs' => [
            'Un incarico può essere chiaro nella forma e incompleto nei dettagli. Quante parole servono? Quale scelta deve essere approvata?',
            'In PACT una domanda identifica il task e dichiara le risposte ammesse. Ogni risposta richiama la domanda che risolve: il sistema può sapere quali dubbi sono ancora aperti, anche quando sono più di uno.',
            'Il lavoro riparte quando tutte le domande obbligatorie che lo bloccano hanno una risposta valida. Una richiesta di chiarimento diventa così un passaggio verificabile della collaborazione.',
        ],
        'alt' => 'Collage a retino: il robot confronta le forme di un documento con quelle richieste dal contratto.',
        'caption' => 'FIG. 02 — LA FORMA DEI DATI È PARTE DELL’ACCORDO',
    ],
    'demo' => [
        'label' => '04 / Un incarico, dall’inizio alla fine',
        'kicker' => 'SEGUI LO SCAMBIO',
        'heading' => 'Dall’accordo<br><em>alla consegna.</em>',
        'intro' => 'Un chiamante affida un testo a un esecutore.<br>Segui i passaggi di questo esempio illustrativo.',
        'toolbar' => 'SCHEMA NARRATIVO · JSON ILLUSTRATIVO',
        'note' => 'Una rappresentazione guidata del flusso; nessun agente viene eseguito.',
        'noscript_before' => 'Per esplorare gli altri passaggi attiva JavaScript, oppure',
        'noscript_link' => 'leggi il flusso completo nella guida',
        'client' => [
            'labels' => [
                'copy' => 'Copia',
                'copied' => 'Copiato',
                'copy_success' => 'Esempio copiato negli appunti.',
                'copy_fallback' => 'Seleziona il codice',
                'copy_error' => 'Copia automatica non disponibile: seleziona il codice e copialo manualmente.',
                'next' => 'Passaggio successivo',
                'restart' => 'Ricomincia',
            ],
            'steps' => [
                [
                    'tab' => 'Accordo',
                    'label' => '01 / Accordo',
                    'heading' => 'Prima di iniziare, lo stesso contratto.',
                    'description' => 'Il chiamante legge l’Agent Card e negozia l’estensione PACT e il contratto supportato. Le due parti verificano quali schemi useranno per input e output.',
                    'status' => 'Negoziazione dell’estensione',
                    'progress' => 'Prima del task',
                    'percent' => 0,
                    'data' => [
                        'extension' => 'PACT Core 0.2',
                        'agreement' => [
                            'input' => 'text',
                            'output' => [
                                'words',
                                'approved',
                            ],
                            'schemas' => 'verificati da entrambe le parti',
                        ],
                    ],
                ],
                [
                    'tab' => 'Incarico',
                    'label' => '02 / Incarico',
                    'heading' => 'Il lavoro parte da un input valido.',
                    'description' => 'Il chiamante invia il testo nella struttura concordata. L’esecutore verifica l’input e avvia il task. Lo stato nativo A2A è WORKING; PACT aggiunge i dettagli dell’avanzamento.',
                    'status' => 'A2A · WORKING',
                    'progress' => 'Avanzamento illustrativo · 20%',
                    'percent' => 20,
                    'data' => [
                        'input' => [
                            'text' => 'Gli agenti collaborano con un accordo esplicito.',
                        ],
                        'task' => [
                            'status' => 'WORKING',
                        ],
                        'progress' => [
                            'stage' => 'elaborazione',
                            'percent' => 20,
                        ],
                    ],
                ],
                [
                    'tab' => 'Chiarimento',
                    'label' => '03 / Chiarimento',
                    'heading' => 'Manca un dettaglio. Si chiede.',
                    'description' => 'L’esecutore deve sapere quante parole restituire. Apre una domanda obbligatoria con risposte ammesse. A2A segnala INPUT_REQUIRED: la domanda deve essere risolta prima di riprendere.',
                    'status' => 'A2A · INPUT_REQUIRED',
                    'progress' => 'In attesa di una risposta',
                    'percent' => 40,
                    'data' => [
                        'task' => [
                            'status' => 'INPUT_REQUIRED',
                        ],
                        'question' => [
                            'id' => 'q-parole',
                            'text' => 'Quante parole vuoi nel risultato?',
                            'allowed' => [
                                3,
                                5,
                                7,
                            ],
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'tab' => 'Ripresa',
                    'label' => '04 / Ripresa',
                    'heading' => 'Una risposta collegata alla sua domanda.',
                    'description' => 'Il chiamante sceglie cinque parole e identifica la domanda a cui risponde. L’esecutore valida la risposta. Quando tutte le domande obbligatorie che bloccano il task sono risolte, il lavoro riparte.',
                    'status' => 'A2A · WORKING',
                    'progress' => 'Avanzamento illustrativo · 60%',
                    'percent' => 60,
                    'data' => [
                        'answer' => [
                            'questionId' => 'q-parole',
                            'value' => 5,
                        ],
                        'validation' => 'risposta ammessa',
                        'task' => [
                            'status' => 'WORKING',
                        ],
                    ],
                ],
                [
                    'tab' => 'Consegna',
                    'label' => '05 / Consegna',
                    'heading' => 'Il risultato rispetta l’accordo.',
                    'description' => 'L’esecutore consegna l’artefatto e il chiamante ne verifica i dati rispetto allo schema. L’output valido e l’assenza di domande obbligatorie irrisolte consentono il completamento. Il solo 100% non sarebbe sufficiente.',
                    'status' => 'A2A · COMPLETED',
                    'progress' => 'Output validato',
                    'percent' => 100,
                    'data' => [
                        'artifact' => [
                            'words' => [
                                'Gli',
                                'agenti',
                                'collaborano',
                                'con',
                                'un',
                            ],
                            'approved' => true,
                        ],
                        'outputValidation' => 'valido',
                        'task' => [
                            'status' => 'COMPLETED',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'swarm' => [
        'label' => '05 / PACT Swarm',
        'kicker' => 'QUANDO IL LAVORO È DI SQUADRA',
        'heading' => 'Più agenti.<br><em>Una responsabilità chiara.</em>',
        'paragraphs' => [
            'Alcuni incarichi richiedono più competenze. Il profilo opzionale Swarm aggiunge un coordinatore che divide il lavoro in task figli e ne rispetta le dipendenze.',
            'Chi aggrega i contributi dichiara quali risultati sono necessari e come valutarli. Le politiche <code>all_required</code> e <code>quorum</code> rendono esplicita la condizione di successo. Anche il risultato finale deve superare la validazione.',
            'Ogni contributo mantiene la propria provenienza. Core resta la base; Swarm si attiva solo se entrambe le parti lo supportano e lo negoziano.',
        ],
        'parent' => 'TASK PADRE',
        'coordinator' => 'Coordinatore',
        'coordinator_text' => 'Assegna, raccoglie, verifica.',
        'children' => [
            [
                'title' => 'Estrazione',
                'text' => 'Legge il materiale.',
            ],
            [
                'title' => 'Revisione',
                'text' => 'Controlla il contributo.',
            ],
            [
                'title' => 'Composizione',
                'text' => 'Prepara la consegna.',
            ],
        ],
        'result' => 'Risultato validato',
        'result_text' => 'Contributi tracciabili.<br>Un output concordato.',
        'caption' => 'Un flusso illustrativo. Le dipendenze stabiliscono quando ciascun task può partire.',
        'link' => 'Leggi Swarm',
    ],
    'completion' => [
        'number_caption' => 'L’AVANZAMENTO RACCONTA IL LAVORO.',
        'label' => '06 / Il completamento',
        'heading' => 'Finire è più<br>di <em>una percentuale.</em>',
        'paragraphs' => [
            'Un avanzamento al 100% non basta a completare un task. Nemmeno una risposta HTTP positiva.',
            'Il completamento nativo A2A richiede artefatti conformi al contratto, nessuna domanda obbligatoria irrisolta e, quando prevista, un’aggregazione conclusa. Il chiamante può verificare l’output ricevuto.',
            'Fasi, contatori, sequenze e identità degli eventi raccontano il percorso. <strong>La consegna valida ne dimostra l’esito.</strong>',
        ],
    ],
    'handoff' => [
        'label' => '07 / Integra PACT',
        'kicker' => 'DUE RUOLI, DUE HANDOFF',
        'heading' => 'Portalo nel tuo progetto.<br><em>Parti dal ruolo giusto.</em>',
        'intro' => 'Copia l’handoff nella chat dell’assistente che lavora sul tuo progetto Laravel. Troverà obiettivo, istruzioni di integrazione e verifiche da completare.',
        'preview' => 'Leggi l’handoff completo',
        'copied' => 'Handoff copiato. Incollalo nella chat del progetto.',
        'copy_error' => 'Copia automatica non disponibile. Il testo è selezionato: copialo manualmente.',
        'note' => 'Il master coordina le squadre personali; il client espone l’agente dell’applicazione. Collegali con il consenso dell’utente.',
        'guide' => 'Guida all’installazione Laravel',
        'roles' => [
            'master' => [
                'title' => 'Il progetto che coordina.',
                'description' => 'Per l’applicazione da cui ogni utente collega i propri agenti, invia incarichi e segue i risultati.',
                'copy' => 'Copia handoff master',
                'text' => <<<'HANDOFF'
Integra PACT in questo progetto Laravel con ruolo MASTER.

Obiettivo: ogni utente autenticato deve poter collegare agenti di altre applicazioni alle proprie squadre, inviare incarichi e consultarne risultati e domande.

Versione minima per questo flusso: pact-protocol/laravel 0.2.1. Riferimento sorgente: bramato/Pact, tag v0.2.1; protocollo Core 0.2.0.

Fonti da leggere prima di modificare il progetto:
- https://github.com/bramato/Pact/blob/v0.2.1/docs/laravel-installation.md
- https://github.com/bramato/Pact/blob/v0.2.1/specification/PACT-LARAVEL-BINDING-v0.1.md
- https://github.com/bramato/Pact/blob/v0.2.1/docs/releases.md

1. Esamina le istruzioni del progetto, PHP/Laravel, database, autenticazione e agente AI esistente. Il pacchetto supporta Laravel 12.41.1+ e 13 con il relativo PHP compatibile. Riusa login, guard e provider dell’applicazione. In locale usa Herd con il nome della cartella seguito da .test, il suo schema HTTP/HTTPS e APP_URL coerente.

2. Installa o aggiorna la distribuzione versionata con composer require pact-protocol/laravel:^0.2.1 pact-protocol/core:^0.2.1 --with-all-dependencies. Controlla composer show pact-protocol/laravel e composer show pact-protocol/core, poi verifica che il codice effettivamente caricato da vendor esponga PactManager::connectDomain(). Se il progetto usa repository Composer path o VCS locali, confrontali con la distribuzione pubblicata e aggiorna i soli override PACT necessari alla risoluzione della versione; preserva gli altri repository. Per lavorare dal sorgente usa il checkout del tag v0.2.1, esegui lì npm ci --ignore-scripts e npm run build:sdk e assegna la versione locale 0.2.1 a entrambi i repository Composer path sdk/php e sdk/laravel. Conserva composer.json e composer.lock aggiornati nel progetto.

3. Verifica database, APP_KEY e APP_URL, poi esegui:
php artisan config:clear
php artisan pact:install --role=master --no-interaction
Per peer locali HTTP aggiungi all’installazione un --allow-local-host per ciascun host, incluso il master. In produzione usa HTTPS. Conserva APP_KEY, database e storage/app/pact tra i deploy; la reinstallazione mantiene identità e chiavi e non cambia ruolo.

Se PACT era già installato, riesegui pact:install --no-interaction dopo l’aggiornamento per applicare le nuove migrazioni: identità, connessioni e chiavi esistenti vengono conservate. Aggiorna entrambi i server. Confronta le eventuali viste pubblicate e personalizzate con quelle del pacchetto per includere le nuove pagine e verifica pact.auth.middleware nella configurazione.

4. Predisponi php artisan pact:work come processo persistente; non richiede Redis. Usa pact:work --once per controlli puntuali. Esegui php artisan pact:doctor e risolvi le anomalie. Ricostruisci le cache di configurazione e route dopo l’installazione.

5. Il collegamento parte dal MASTER: aggiungi in navigazione route('pact.agents') o route('pact.connect'). Il pacchetto offre il pulsante «Collega agente»: l’utente inserisce solo il dominio del client, il master legge /.well-known/pact e registra automaticamente il proprio client OAuth. Non chiedere registrazioni manuali né client ID. Per CLI usa:
php artisan pact:connect DOMINIO_CLIENT --domain --user=ID_UTENTE_MASTER
L’utente accede al client, autorizza l’agente e ritorna al master, dove sceglie una propria squadra o ne crea una. Le email possono essere diverse. Solo dopo la scelta il worker consegna al client le credenziali di ritorno e conferma entrambe le direzioni. Mostra attesa, errore o collegamento confermato. Un agente di un account remoto appartiene a una squadra per master; per spostarlo conferma prima la disconnessione. Entrambi i peer devono supportare discovery/registrazione e usare lo stesso prefisso PACT. Se manca il client, completa il master e indica il dominio necessario per il collaudo.

6. Nell’interfaccia autenticata usa PactLaravel\Facades\Pact: connectDomain(), teams(), createTeam(), members(), sendMessage(), task(), answer() e cancel(). Passa la squadra scelta come argomento opzionale delle operazioni; senza argomento si usa quella personale. Mantieni il controllo di proprietà della squadra. Mostra collegamento, membri, stato task, domande e risultati. Riusa chiavi di idempotenza per i tentativi dello stesso comando. Usa i contratti supportati da entrambi i peer e valida gli artefatti: una ricevuta o HTTP 200 non significano completamento. Per gli strumenti dell’agente usa il binding MCP documentato e le credenziali previste, senza inventare endpoint.

7. Mantieni ogni operazione nella squadra autorizzata dell’utente corrente. La federazione non concede accesso generale a conversazioni o strumenti. Quando il client è disponibile, collauda dalla UI il percorso con il solo dominio, consenso remoto, scelta o creazione della squadra e conferma dell’attivazione. Verifica sia un incarico dal master al client sia una chiamata autenticata del client al roster del master. Prova anche accesso negato a squadre altrui, consenso annullato, collegamento scaduto, invio ripetuto della scelta senza duplicati, un incarico completo, domande/risposte, cancellazione e disconnessione con pact:disconnect CONNECTION_ID --user=ID_UTENTE_MASTER.

Consegna modifiche funzionanti, verifiche eseguite, configurazione del worker e istruzioni operative. Distingui ciò che è verificato da ciò che attende un client o il consenso dell’utente. Non mostrare token o chiavi nel resoconto.
HANDOFF,
            ],
            'client' => [
                'title' => 'Il progetto che contribuisce.',
                'description' => 'Per l’applicazione che mette il proprio agente e le sue capacità a disposizione degli utenti collegati.',
                'copy' => 'Copia handoff client',
                'text' => <<<'HANDOFF'
Integra PACT in questo progetto Laravel con ruolo CLIENT.

Obiettivo: esporre un agente per ciascun utente dell’applicazione, collegabile a un master PACT con consenso OAuth, ed eseguire incarichi con le capacità AI e i permessi già presenti nel progetto.

Versione minima per questo flusso: pact-protocol/laravel 0.2.1. Riferimento sorgente: bramato/Pact, tag v0.2.1; protocollo Core 0.2.0.

Fonti da leggere prima di modificare il progetto:
- https://github.com/bramato/Pact/blob/v0.2.1/docs/laravel-installation.md
- https://github.com/bramato/Pact/blob/v0.2.1/specification/PACT-LARAVEL-BINDING-v0.1.md
- https://github.com/bramato/Pact/blob/v0.2.1/docs/releases.md

1. Esamina le istruzioni del progetto, PHP/Laravel, database, autenticazione, agente AI e strumenti disponibili. Il pacchetto supporta Laravel 12.41.1+ e 13 con il relativo PHP compatibile. Riusa login, guard, provider e chiavi Passport esistenti. In locale usa Herd con il nome della cartella seguito da .test, il suo schema HTTP/HTTPS e APP_URL coerente.

2. Installa o aggiorna la distribuzione versionata con composer require pact-protocol/laravel:^0.2.1 pact-protocol/core:^0.2.1 --with-all-dependencies. Controlla composer show pact-protocol/laravel e composer show pact-protocol/core, poi verifica che il codice effettivamente caricato da vendor esponga PactManager::connectDomain(). Se il progetto usa repository Composer path o VCS locali, confrontali con la distribuzione pubblicata e aggiorna i soli override PACT necessari alla risoluzione della versione; preserva gli altri repository. Per lavorare dal sorgente usa il checkout del tag v0.2.1, esegui lì npm ci --ignore-scripts e npm run build:sdk e assegna la versione locale 0.2.1 a entrambi i repository Composer path sdk/php e sdk/laravel. Conserva composer.json e composer.lock aggiornati nel progetto.

3. Verifica database, APP_KEY e APP_URL, poi esegui:
php artisan config:clear
php artisan pact:install --role=client --no-interaction
Per peer locali HTTP aggiungi all’installazione un --allow-local-host per ciascun host, inclusi client e master. In produzione usa HTTPS. L’installazione gestisce le migrazioni PACT e quelle Passport mancanti. Conserva APP_KEY, database, chiavi Passport e storage/app/pact tra i deploy; non sostituire credenziali esistenti.

Se PACT era già installato, riesegui pact:install --no-interaction dopo l’aggiornamento per applicare le nuove migrazioni: identità, connessioni e chiavi esistenti vengono conservate. Aggiorna entrambi i server. Confronta le eventuali viste pubblicate e personalizzate con quelle del pacchetto per includere le nuove pagine e verifica pact.auth.middleware nella configurazione.

4. Predisponi php artisan pact:work come processo persistente; non richiede Redis. Usa pact:work --once per controlli puntuali. Esegui php artisan pact:doctor e risolvi le anomalie. Ricostruisci le cache di configurazione e route dopo l’installazione.

5. Il client pubblica /.well-known/pact e la registrazione OAuth pubblica automaticamente. Non occorre conoscere prima il master o eseguire pact:oauth-client. Consegna solo il dominio di questa applicazione: dal MASTER l’utente preme «Collega agente», inserisce il dominio, accede qui, approva e ritorna al master per scegliere la squadra. Il worker del master completa lo scambio delle credenziali nelle due direzioni. I peer devono usare lo stesso prefisso PACT. Se l’app è multitenant, gestisci qui la scelta di account/tenant con il flusso esistente e pact.auth.middleware; configura IdentityResolver con un soggetto locale stabile che includa il contesto autorizzato. La stessa identità viene ricontrollata all’approvazione. Le email non devono coincidere. Puoi personalizzare le pagine con php artisan vendor:publish --tag=pact-views. Se manca il master, completa il client e consegna il dominio per il collaudo.

6. Verifica prima la connettività con il contratto diagnostico predefinito, che non usa AI. Poi implementa PactLaravel\Contracts\AgentHandler con handle(AgentContext $context): AgentResult e collega l’agente reale dell’applicazione. Configura handler, contract_path e contract_registry in config/pact.php, con schemi locali e digest dei byte esatti; riusa il contratto agent-chat incluso quando adatto. Restituisci output conforme con AgentResult::completed(); usa question(), waiting() o error() per gli altri esiti. Rendi idempotenti gli effetti esterni usando context->invocationId.

7. Esegui ogni incarico con i permessi di context->userId, incluso il tenant autorizzato. ConversationReader nega le letture per impostazione predefinita: abilita solo quelle autorizzate ai partecipanti reali. La federazione non concede accesso generale a dati o strumenti. Verifica discovery pubblica, registrazione ripetuta con lo stesso client ID e callback vincolato al master. Quando il master è disponibile, prova il flusso dal solo dominio, consenso, attivazione e chiamate autenticate in entrambe le direzioni. Per il multitenant verifica che cambiare tenant prima dell’approvazione richieda un nuovo consenso. Collauda un incarico con output validato, isolamento tra utenti, retry idempotenti, domande/ripresa e revoca con pact:disconnect CONNECTION_ID --user=ID_UTENTE_CLIENT.

Consegna modifiche funzionanti, verifiche eseguite, configurazione del worker, capacità esposte e dominio da passare al master. Distingui il ping diagnostico dall’integrazione AI reale e segnala ciò che attende un master o il consenso dell’utente. Non mostrare token o chiavi nel resoconto.
HANDOFF,
            ],
        ],
    ],
    'resources' => [
        'label' => '08 / Dalla lettura al codice',
        'kicker' => 'APRI IL PROGETTO',
        'heading' => 'Un protocollo aperto.<br><em>Da leggere, provare, discutere.</em>',
        'intro' => 'PACT è una proposta indipendente in bozza, senza endorsement ufficiale A2A. Specifiche, implementazioni ed esempi sono disponibili nella repository con licenza MIT.',
        'items' => [
            [
                'title' => 'Comincia dalla demo',
                'description' => 'Un chiamante TypeScript e un servizio PHP: un task completo, senza chiavi API di modelli AI.',
                'path' => 'docs/quickstart.md',
                'label' => 'Quickstart',
            ],
            [
                'title' => 'Scegli la tua implementazione',
                'description' => 'SDK indipendenti TypeScript e PHP, con un’integrazione Laravel. Codice e istruzioni nella repository.',
                'path' => 'tree/main/sdk',
                'label' => 'SDK',
            ],
            [
                'title' => 'Verifica le regole',
                'description' => 'Schemi, vettori condivisi e test di conformità per controllare accordi, output ed eventi.',
                'path' => 'tree/main/conformance',
                'label' => 'Conformità',
            ],
            [
                'title' => 'Conosci i confini',
                'description' => 'Compatibilità, limiti delle implementazioni e decisioni ancora aperte prima della pubblicazione.',
                'path' => 'docs/implementation.md',
                'label' => 'Stato del progetto',
            ],
        ],
    ],
    'faq' => [
        'label' => 'Qualche chiarimento',
        'heading' => 'Prima di<br><em>cominciare.</em>',
        'items' => [
            [
                'question' => 'PACT sostituisce A2A?',
                'answer' => 'PACT usa A2A per la scoperta degli agenti, i messaggi, i task, gli artefatti e la negoziazione delle estensioni. Aggiunge le regole dei contratti e della collaborazione. Il ciclo di vita nativo del task resta autorevole.',
            ],
            [
                'question' => 'Serve un modello AI per provarlo?',
                'answer' => 'La demo di riferimento è deterministica: usa TypeScript e PHP e si può eseguire senza una chiave API di un modello AI. Il protocollo descrive gli scambi tra agenti; l’implementazione decide come svolgere il lavoro.',
            ],
            [
                'question' => 'Un output valido è sempre un output corretto?',
                'answer' => 'La validazione controlla che i dati rispettino la struttura concordata. La verifica dei fatti, l’utilità e la correttezza del contenuto richiedono controlli dell’applicazione. Anche un digest rileva alterazioni, ma da solo non autentica il produttore.',
            ],
            [
                'question' => 'È già uno standard ufficiale?',
                'answer' => 'PACT 0.2.0 è una proposta indipendente in bozza. La repository include specifiche, SDK ed esempi verificabili; i test disponibili non certificano ogni funzione A2A né l’intera operatività in produzione. La roadmap indica i passi e le decisioni di rilascio.',
            ],
        ],
    ],
    'closing' => [
        'eyebrow' => 'IL PROSSIMO PASSO È UN ACCORDO.',
        'heading' => 'Facciamo lavorare<br><em>insieme gli agenti.</em>',
        'cta' => 'Esplora PACT su GitHub',
    ],
    'footer' => [
        'edition' => 'Bozza 0.2.0 · MIT',
        'governance' => 'Governance',
    ],
];
