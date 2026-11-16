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
    'resources' => [
        'label' => '07 / Dalla lettura al codice',
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
