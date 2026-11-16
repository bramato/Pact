<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#075bc7">
    <meta name="description" content="PACT è un protocollo per far collaborare agenti indipendenti: contratti espliciti, domande strutturate e risultati verificabili sopra A2A. Scopri come funziona.">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="it_IT">
    <meta property="og:title" content="PACT — Mettersi d’accordo, prima di agire.">
    <meta property="og:description" content="Un accordo esplicito tra agenti. Dai dati in ingresso a un risultato verificabile, con contratti, chiarimenti e coordinamento sopra A2A.">
    <meta property="og:image" content="{{ url('/assets/overview-editorial.webp') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="icon" href="/assets/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/pact.css?v=2">
    <script defer src="/assets/pact.js?v=1"></script>
    <title>PACT — Mettersi d’accordo, prima di agire.</title>
</head>
<body>
    <a class="skip-link" href="#contenuto">Vai al contenuto</a>
    <header class="masthead wrap">
        <a href="#inizio" class="brand" aria-label="PACT, inizio pagina"><img src="/assets/logo.svg" width="43" height="43" alt=""><span>PACT<span class="brand-period">.</span></span></a>
        <span class="masthead-note">Un accordo tra intelligenze.</span>
        <nav aria-label="Navigazione principale">
            <a href="#il-protocollo">Il protocollo</a>
            <a href="#in-pratica">In pratica</a>
            <a class="nav-source" href="https://github.com/bramato/Pact">GitHub <x-icon name="arrow-up-right" /></a>
        </nav>
    </header>

    <main id="contenuto">
        <section class="hero" id="inizio" aria-labelledby="hero-title">
            <div class="wrap">
                <div class="hero-meta"><span class="eyebrow"><span class="status-dot"></span> Protocol for Agent Coordination and Tasks</span><span class="edition">Proposta indipendente · Bozza 0.2.0</span></div>
                <div class="hero-grid">
                    <div class="hero-copy">
                        <h1 id="hero-title">Mettersi<br>d’accordo,<br><em>prima di agire.</em></h1>
                        <p class="hero-lead">Gli agenti AI possono scambiarsi messaggi.<br> PACT li aiuta a condividere un incarico, risolvere i dubbi e consegnare un risultato verificabile.</p>
                        <a class="button button-dark" href="#il-protocollo">Scopri il protocollo <x-icon name="arrow-down" /></a>
                        <a class="text-link hero-secondary" href="https://github.com/bramato/Pact/blob/main/specification/PACT-CORE-v0.2.md">Leggi la specifica <x-icon /></a>
                    </div>
                    <figure class="hero-art">
                        <span class="art-index">FIG. 01 — UN ACCORDO CONDIVISO</span>
                        <img src="/assets/overview-editorial.webp" width="1536" height="1024" alt="Illustrazione a retino su carta ritagliata: il robot PACT collega due sistemi e presenta un risultato verificato." fetchpriority="high">
                        <figcaption><span class="caption-rule"></span>Agenti diversi. Un contratto comune.</figcaption>
                    </figure>
                </div>
                <div class="hero-bottom"><span>Contratti espliciti. Collaborazione verificabile.</span><a href="#il-protocollo">Un’introduzione a PACT <x-icon name="arrow-down" /></a></div>
            </div>
        </section>

        <section class="intro section wrap" id="il-protocollo" aria-labelledby="intro-title">
            <div class="section-label"><span>01 / Il punto di partenza</span><span>DAL MESSAGGIO ALL’INCARICO</span></div>
            <div class="intro-grid">
                <h2 id="intro-title">Capirsi è un inizio.<br><em>Accordarsi è il passo dopo.</em></h2>
                <div class="prose">
                    <p>Chiedi a un agente di analizzare un documento. Lui risponde. Ma in quale formato? Con quali informazioni? E come stabilisci se ha finito davvero?</p>
                    <p>Quando il lavoro passa da un sistema all’altro, queste domande diventano parte dell’incarico. PACT rende l’accordo esplicito: quali dati inviare, come chiedere ciò che manca e quale risultato accettare.</p>
                    <p>Si appoggia ad <a href="https://a2a-protocol.org/">A2A, il protocollo Agent2Agent</a>, che fornisce scoperta degli agenti, messaggi, task e artefatti. PACT aggiunge le regole dell’accordo nei metadati di un’estensione negoziata. <strong>Lo stato del task resta quello nativo di A2A.</strong></p>
                </div>
            </div>
            <div class="principles" aria-label="I tre principi">
                <div><span class="small-number">I.</span><h3>Prima, il contratto.</h3><p>Chi invia e chi esegue concordano la forma dei dati.</p></div>
                <div><span class="small-number">II.</span><h3>Durante, il confronto.</h3><p>Se manca qualcosa, una domanda precisa sblocca il lavoro.</p></div>
                <div><span class="small-number">III.</span><h3>Alla fine, la verifica.</h3><p>La consegna deve rispettare l’output concordato.</p></div>
            </div>
        </section>

        <section class="chapter section wrap" aria-labelledby="contract-title">
            <div class="chapter-copy">
                <span class="eyebrow blue">02 / Il contratto</span>
                <h2 id="contract-title">La stessa idea<br>di <em>“fatto”.</em></h2>
                <div class="prose"><p>Un testo libero lascia spazio alle interpretazioni. Un contratto definisce invece la struttura esatta di ciò che entra e di ciò che deve uscire.</p><p>Ogni contratto ha un’identità e una versione. Gli schemi JSON sono identificati anche dal loro digest SHA-256: entrambe le parti possono controllare di usare gli stessi byte, prima di validare i dati.</p><p>Il risultato viene verificato rispetto allo schema concordato. <strong>La validità della struttura è controllabile; la qualità del contenuto resta responsabilità dell’applicazione.</strong></p></div>
                <a class="text-link" href="https://github.com/bramato/Pact/blob/main/specification/PACT-CORE-v0.2.md">Esplora PACT Core <x-icon /></a>
            </div>
            <div class="contract-visual">
                <div class="visual-heading"><span>UN ESEMPIO, IN PAROLE SEMPLICI</span><span class="mini-mark">PACT / CORE</span></div>
                <div class="contract-row"><span class="contract-tag">INPUT</span><div><h3>Questo è il documento.</h3><p>Un testo da elaborare, nella struttura prevista.</p><code>{ "text": "Il testo da analizzare…" }</code></div></div>
                <div class="contract-connection"><span></span><x-icon name="arrow-down" /><span>contratto condiviso</span></div>
                <div class="contract-row"><span class="contract-tag output-tag">OUTPUT</span><div><h3>Questo è il risultato atteso.</h3><p>Una lista di parole e un valore di approvazione.</p><code>{ "words": ["…"], "approved": true }</code></div></div>
                <div class="visual-footnote"><x-icon name="check" /> Esempio semplificato della demo, non un messaggio wire completo.</div>
            </div>
        </section>

        <section class="conversation section wrap" aria-labelledby="question-title">
            <figure class="chapter-art"><img src="/assets/concept-editorial.webp" width="1536" height="1024" alt="Collage a retino: il robot confronta le forme di un documento con quelle richieste dal contratto." loading="lazy"><figcaption>FIG. 02 — LA FORMA DEI DATI È PARTE DELL’ACCORDO</figcaption></figure>
            <div class="chapter-copy"><span class="eyebrow blue">03 / I chiarimenti</span><h2 id="question-title">Una buona domanda<br><em>fa parte del lavoro.</em></h2><div class="prose"><p>Un incarico può essere chiaro nella forma e incompleto nei dettagli. Quante parole servono? Quale scelta deve essere approvata?</p><p>In PACT una domanda identifica il task e dichiara le risposte ammesse. Ogni risposta richiama la domanda che risolve: il sistema può sapere quali dubbi sono ancora aperti, anche quando sono più di uno.</p><p>Il lavoro riparte quando tutte le domande obbligatorie che lo bloccano hanno una risposta valida. Una richiesta di chiarimento diventa così un passaggio verificabile della collaborazione.</p></div></div>
        </section>

        <section class="demo-section" id="in-pratica" aria-labelledby="demo-title">
            <div class="wrap">
                <div class="section-label"><span>04 / Un incarico, dall’inizio alla fine</span><span>SEGUI LO SCAMBIO</span></div>
                <div class="demo-intro"><h2 id="demo-title">Dall’accordo<br><em>alla consegna.</em></h2><p>Un chiamante affida un testo a un esecutore.<br>Segui i passaggi di questo esempio illustrativo.</p></div>
                <div class="demo-tabs" role="group" aria-label="Passaggi dell’esempio" hidden>
                    @foreach(['Accordo', 'Incarico', 'Chiarimento', 'Ripresa', 'Consegna'] as $step)
                        <button type="button" data-step="{{ $loop->index }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><span>0{{ $loop->iteration }}</span>{{ $step }}</button>
                    @endforeach
                </div>
                <div class="demo-stage" aria-live="polite" aria-atomic="true">
                    <div class="demo-explanation"><span class="eyebrow" id="demo-label">01 / Accordo</span><h3 id="demo-heading">Prima di iniziare, lo stesso contratto.</h3><p id="demo-description">Il chiamante legge l’Agent Card e negozia l’estensione PACT e il contratto supportato. Le due parti verificano quali schemi useranno per input e output.</p><div class="demo-state"><span id="demo-status">Negoziazione dell’estensione</span><span id="demo-progress">Prima del task</span></div><div class="progress-track" aria-hidden="true"><span id="demo-progress-bar"></span></div></div>
                    <div class="demo-code"><div class="code-toolbar"><span>SCHEMA NARRATIVO · JSON ILLUSTRATIVO</span><button class="copy-button" type="button" hidden>Copia</button></div><pre><code id="demo-json">{
  "extension": "PACT Core 0.2",
  "agreement": {
    "input": "text",
    "output": ["words", "approved"],
    "schemas": "verificati da entrambe le parti"
  }
}</code></pre><span id="copy-feedback" class="sr-only" role="status"></span></div>
                </div>
                <div class="demo-bottom"><span>Una rappresentazione guidata del flusso; nessun agente viene eseguito.</span><button id="next-step" class="text-link" type="button" hidden>Passaggio successivo <x-icon name="arrow-right" /></button></div>
                <noscript><p class="noscript-note">Per esplorare gli altri passaggi attiva JavaScript, oppure <a href="https://github.com/bramato/Pact/blob/main/docs/quickstart.md">leggi il flusso completo nella guida</a>.</p></noscript>
            </div>
        </section>

        <section class="swarm section wrap" id="collaborazione" aria-labelledby="swarm-title">
            <div class="section-label"><span>05 / PACT Swarm</span><span>QUANDO IL LAVORO È DI SQUADRA</span></div>
            <div class="intro-grid"><h2 id="swarm-title">Più agenti.<br><em>Una responsabilità chiara.</em></h2><div class="prose"><p>Alcuni incarichi richiedono più competenze. Il profilo opzionale Swarm aggiunge un coordinatore che divide il lavoro in task figli e ne rispetta le dipendenze.</p><p>Chi aggrega i contributi dichiara quali risultati sono necessari e come valutarli. Le politiche <code>all_required</code> e <code>quorum</code> rendono esplicita la condizione di successo. Anche il risultato finale deve superare la validazione.</p><p>Ogni contributo mantiene la propria provenienza. Core resta la base; Swarm si attiva solo se entrambe le parti lo supportano e lo negoziano.</p></div></div>
            <div class="swarm-figure" aria-label="Esempio di collaborazione: un coordinatore assegna estrazione, revisione e composizione, poi valida il risultato finale.">
                <div class="swarm-node coordinator"><span class="eyebrow">TASK PADRE</span><h3>Coordinatore</h3><p>Assegna, raccoglie, verifica.</p></div>
                <div class="swarm-line" aria-hidden="true"><span></span><x-icon name="arrow-right" /></div>
                <div class="swarm-children"><div><span>01</span><strong>Estrazione</strong><small>Legge il materiale.</small></div><div><span>02</span><strong>Revisione</strong><small>Controlla il contributo.</small></div><div><span>03</span><strong>Composizione</strong><small>Prepara la consegna.</small></div></div>
                <div class="swarm-line" aria-hidden="true"><span></span><x-icon name="arrow-right" /></div>
                <div class="swarm-node result"><x-icon name="check" /><h3>Risultato validato</h3><p>Contributi tracciabili.<br>Un output concordato.</p></div>
            </div>
            <div class="figure-caption"><span>Un flusso illustrativo. Le dipendenze stabiliscono quando ciascun task può partire.</span><a class="text-link" href="https://github.com/bramato/Pact/blob/main/specification/PACT-SWARM-v0.2.md">Leggi Swarm <x-icon /></a></div>
        </section>

        <section class="completion section wrap" aria-labelledby="completion-title">
            <div class="completion-number" aria-hidden="true">100<span>%</span><span class="number-caption">L’AVANZAMENTO RACCONTA IL LAVORO.</span></div>
            <div><span class="eyebrow blue">06 / Il completamento</span><h2 id="completion-title">Finire è più<br>di <em>una percentuale.</em></h2><div class="prose"><p>Un avanzamento al 100% non basta a completare un task. Nemmeno una risposta HTTP positiva.</p><p>Il completamento nativo A2A richiede artefatti conformi al contratto, nessuna domanda obbligatoria irrisolta e, quando prevista, un’aggregazione conclusa. Il chiamante può verificare l’output ricevuto.</p><p>Fasi, contatori, sequenze e identità degli eventi raccontano il percorso. <strong>La consegna valida ne dimostra l’esito.</strong></p></div></div>
        </section>

        <section class="resources section wrap" id="risorse" aria-labelledby="resources-title">
            <div class="section-label"><span>07 / Dalla lettura al codice</span><span>APRI IL PROGETTO</span></div>
            <div class="resource-heading"><h2 id="resources-title">Un protocollo aperto.<br><em>Da leggere, provare, discutere.</em></h2><p>PACT è una proposta indipendente in bozza, senza endorsement ufficiale A2A. Specifiche, implementazioni ed esempi sono disponibili nella repository con licenza MIT.</p></div>
            <div class="resource-list">
                @foreach([
                    ['01', 'Comincia dalla demo', 'Un chiamante TypeScript e un servizio PHP: un task completo, senza chiavi API di modelli AI.', 'docs/quickstart.md', 'Quickstart'],
                    ['02', 'Scegli la tua implementazione', 'SDK indipendenti TypeScript e PHP, con un’integrazione Laravel. Codice e istruzioni nella repository.', 'tree/main/sdk', 'SDK'],
                    ['03', 'Verifica le regole', 'Schemi, vettori condivisi e test di conformità per controllare accordi, output ed eventi.', 'tree/main/conformance', 'Conformità'],
                    ['04', 'Conosci i confini', 'Compatibilità, limiti delle implementazioni e decisioni ancora aperte prima della pubblicazione.', 'docs/implementation.md', 'Stato del progetto'],
                ] as [$number, $title, $description, $path, $label])
                    <a class="resource-row" href="https://github.com/bramato/Pact/{{ str_starts_with($path, 'tree/') ? $path : 'blob/main/'.$path }}"><span class="resource-number">{{ $number }}</span><div><h3>{{ $title }}</h3><p>{{ $description }}</p></div><span class="resource-destination">{{ $label }} <x-icon /></span></a>
                @endforeach
            </div>
        </section>

        <section class="faq section wrap" aria-labelledby="faq-title"><div><span class="eyebrow blue">Qualche chiarimento</span><h2 id="faq-title">Prima di<br><em>cominciare.</em></h2></div><div class="faq-list">
            <details><summary>PACT sostituisce A2A?<x-icon name="arrow-down" /></summary><p>PACT usa A2A per la scoperta degli agenti, i messaggi, i task, gli artefatti e la negoziazione delle estensioni. Aggiunge le regole dei contratti e della collaborazione. Il ciclo di vita nativo del task resta autorevole.</p></details>
            <details><summary>Serve un modello AI per provarlo?<x-icon name="arrow-down" /></summary><p>La demo di riferimento è deterministica: usa TypeScript e PHP e si può eseguire senza una chiave API di un modello AI. Il protocollo descrive gli scambi tra agenti; l’implementazione decide come svolgere il lavoro.</p></details>
            <details><summary>Un output valido è sempre un output corretto?<x-icon name="arrow-down" /></summary><p>La validazione controlla che i dati rispettino la struttura concordata. La verifica dei fatti, l’utilità e la correttezza del contenuto richiedono controlli dell’applicazione. Anche un digest rileva alterazioni, ma da solo non autentica il produttore.</p></details>
            <details><summary>È già uno standard ufficiale?<x-icon name="arrow-down" /></summary><p>PACT 0.2.0 è una proposta indipendente in bozza. La repository include specifiche, SDK ed esempi verificabili; i test disponibili non certificano ogni funzione A2A né l’intera operatività in produzione. La roadmap indica i passi e le decisioni di rilascio.</p></details>
        </div></section>

        <section class="closing" aria-labelledby="closing-title"><div class="closing-inner wrap"><div><span class="eyebrow">IL PROSSIMO PASSO È UN ACCORDO.</span><h2 id="closing-title">Facciamo lavorare<br><em>insieme gli agenti.</em></h2></div><a class="button button-dark" href="https://github.com/bramato/Pact">Esplora PACT su GitHub <x-icon name="github" /></a></div></section>
    </main>

    <footer class="footer wrap"><a class="brand" href="#inizio"><img src="/assets/logo.svg" width="32" height="32" alt=""><span>PACT<span class="brand-period">.</span></span></a><span>Protocol for Agent Coordination and Tasks</span><div><span>Bozza 0.2.0 · MIT</span><a href="https://github.com/bramato/Pact/blob/main/GOVERNANCE.md">Governance <x-icon /></a></div></footer>
</body>
</html>
