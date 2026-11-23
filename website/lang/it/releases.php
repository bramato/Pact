<?php

return [
    '0.3.1' => [
        'changes' => [
            'Usa PACT_QUEUE_CONNECTION e PACT_QUEUE per scegliere la coda Laravel che esegue gli incarichi.',
            'Il client può comunicare la fase in corso, per esempio «Sto verificando le fonti». Il master può mostrarla mentre l’agente lavora.',
            'Il servizio per gli incarichi strutturati e il trait ReadsPactCalls estraggono un brief leggibile dal payload.',
            'La guida Laravel descrive il backup e il ripristino su S3 privato delle impostazioni e delle chiavi PACT nelle release con filesystem effimero.',
        ],
        'instructions' => [
            'master' => 'Durante l’esecuzione leggi l’ultimo progress.stage da GetTask. Usa progress.sequence per ignorare gli aggiornamenti precedenti e gestisci una percentuale nulla. Quando mostri moduli v1, conserva i tipi JSON dei campi e valida la risposta rispetto al modulo e allo schema con digest.',
            'client' => 'Usa AgentContext::readableMessage() per leggere i brief strutturati e TaskService::reportProgress() per comunicare le fasi effettive del lavoro. Se imposti PACT_QUEUE_CONNECTION e PACT_QUEUE, affianca a pact:work un worker Laravel per la connessione e la coda selezionate. Verifica che il collegamento esistente possa completare un incarico autorizzato e comunicare l’avanzamento. Invia moduli v1 solo se il master dichiara quella versione e sa mostrarla.',
        ],
    ],
    '0.3.0' => [
        'changes' => [
            'Le domande Core possono includere un modulo v1 con campi tipizzati, valori obbligatori e opzioni con etichette per selezioni singole o multiple.',
            'Gli SDK PHP e TypeScript validano le risposte rispetto al modulo e al contratto. La discovery Laravel espone question_form_versions.',
            'Gli handoff Laravel spiegano come salvare e ripristinare i parametri di installazione e le chiavi PACT nelle release con filesystem effimero.',
        ],
        'instructions' => [
            'master' => 'Per i moduli v1, mostra il testo della domanda e i campi con le rispettive etichette nell’ordine previsto. Conserva i tipi JSON, inclusi false e zero, ometti i campi facoltativi senza risposta e invia un unico oggetto con le chiavi dei campi come valore della risposta correlata. Valida modulo e contratto, mostra gli errori sui campi e segnala i moduli che l’interfaccia non sa mostrare. Prova un campo obbligatorio, la scelta multipla e la ripresa dell’incarico. Mantieni il supporto per le domande senza modulo.',
            'client' => 'Quando l’agente richiede dati strutturati, usa AgentResult::question() con il descrittore del modulo v1 e uno schema di risposta con digest. Abilitalo quando il coordinatore dichiara question_form_versions con valore 1 e sa mostrare il modulo. Mantieni il descrittore invariato finché la domanda è aperta, valida l’oggetto ricevuto rispetto a modulo e schema e riprendi l’incarico originale. Mantieni il flusso delle domande senza modulo per i peer che non supportano v1.',
        ],
    ],
    '0.2.2' => [
        'changes' => [
            'Tema PACT condiviso per collegamento, consenso, scelta della squadra e stato degli agenti.',
            'L’applicazione può fornire un nonce CSP per risposta agli stili inline delle pagine di collegamento.',
        ],
        'instructions' => [
            'master' => 'Allinea le viste personalizzate al tema condiviso della 0.2.2 per collegamento, scelta della squadra e stato degli agenti. Se l’applicazione applica una CSP, passa il nonce della risposta agli stili come indicato nella guida all’interfaccia.',
            'client' => 'Confronta la vista di consenso con il tema condiviso della 0.2.2 e mostra il dominio richiedente e le due direzioni autorizzate. Se l’applicazione applica una CSP, usa il nonce per risposta documentato per gli stili del pacchetto.',
        ],
    ],
    '0.2.1' => [
        'changes' => [
            'Il master può collegare un agente dal solo dominio, con discovery e registrazione OAuth automatica.',
            'L’utente può scegliere o creare una squadra con nome. Il worker conferma le credenziali in entrambe le direzioni prima dell’attivazione.',
        ],
        'instructions' => [
            'master' => 'Se aggiorni dalla 0.2.0, applica la migrazione con pact:install e verifica che la federazione personale e i collegamenti esistenti siano ancora accessibili. Prova a scegliere una squadra di cui sei proprietario e ripeti la scelta per verificare che non duplichi il collegamento.',
            'client' => 'Se aggiorni dalla 0.2.0, verifica che viste di consenso e configurazione pubblicate supportino il nuovo collegamento. Controlla che la stessa richiesta di registrazione restituisca lo stesso client ID e che il cambio di tenant richieda un nuovo consenso.',
        ],
    ],
    '0.2.0' => [
        'changes' => [
            'Prima distribuzione pubblica su Composer dei pacchetti PHP Core e Laravel.',
            'Ruoli master e client con OAuth/PKCE, federazioni personali, worker su database e risultati A2A validati.',
        ],
        'instructions' => [
            'master' => 'Costruisci l’interfaccia intorno alla federazione personale. Usa Pact::members(), sendMessage(), task(), answer() e cancel() con le firme della v0.2.0.',
            'client' => 'Verifica che dopo il consenso l’utente torni nella stessa sessione autenticata del master che ha avviato il collegamento. Per la ripresa dopo una domanda, verifica che il contratto scelto ed entrambi i peer registrino lo schema di risposta utilizzato.',
        ],
    ],
];
