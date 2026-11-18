# PACT — stile delle pagine di collegamento

Le schermate del master e il consenso sul client usano la stessa identità editoriale del sito PACT: carta avorio su fondo cobalto, inchiostro scuro, titoli serif con enfasi corsiva, bordi sottili e controlli rettangolari. Il tema è condiviso nel pacchetto Laravel e si carica localmente su ciascuna applicazione.

Il tema condiviso è incluso in `pact-protocol/laravel` dalla versione 0.2.2. Aggiornare il pacchetto e confrontare le eventuali viste pubblicate con quelle nuove, senza sovrascrivere le personalizzazioni dell’applicazione.

## Sorgenti e risorse condivise

La fonte editoriale è `website/public/assets/pact.css`, con la direzione visiva in `website/art-direction.json`. Le primitive delle schermate di collegamento sono:

- `sdk/laravel/resources/views/layout.blade.php`: cornice comune, navigazione accessibile, messaggi e contesto del server.
- `sdk/laravel/resources/views/partials/styles.blade.php`: token e componenti, con classi e proprietà CSS prefissate `pact-`.
- `partials/brand.blade.php`: simbolo compatto ufficiale del sito, accostato al nome PACT come testo.
- `partials/icon.blade.php`: SVG nello stesso linguaggio del wrapper del sito, con tratto da 1,6 e `currentColor`.
- `partials/steps.blade.php`: sequenza server, accesso, squadra; il passaggio corrente ha `aria-current="step"`.

La grana di stampa e il bordo di carta derivano dagli SVG già presenti sul sito. Sono incorporati nel CSS, così la pagina di consenso funziona senza richieste a CDN, font o immagini remoti. Il logo principale del sito conserva il proprio file originale: nelle testate compatte si usa il simbolo SVG ufficiale, senza ridisegnarlo.

## Token editoriali

| Token | Valore | Uso |
| --- | --- | --- |
| `--pact-blue` | `#075bc7` | Fondo esterno, enfasi dei titoli, focus e azioni attive |
| `--pact-blue-dark` | `#064ca7` | Variante cobalto |
| `--pact-paper` | `#eee7d7` | Foglio principale |
| `--pact-paper-light` | `#f5efdf` | Moduli e pannelli |
| `--pact-ink` | `#122923` | Testo, bordi principali e azione primaria |
| `--pact-muted` | `#535c50` | Istruzioni e testo secondario |
| `--pact-line` | `#c6c5b1` | Separatori decorativi |
| `--pact-field-border` | `#6a7267` | Bordo dei campi, più visibile dei separatori |
| `--pact-danger` | `#8a2920` | Errori, sempre accompagnati da testo |
| `--pact-serif` | Georgia, Times New Roman, serif | Titoli editoriali |
| `--pact-sans` | Font di sistema | Corpo, campi e pulsanti |
| `--pact-mono` | SFMono-Regular, Consolas, Liberation Mono | Eyebrow, metadati e numerazione |

Il tema è chiaro anche quando il browser preferisce il tema scuro, come la landing. La lettura non dipende dalla texture: contenuto, campi e messaggi devono restare chiari anche se le decorazioni sono disabilitate. Non aggiungere ombre pesanti, capsule o un secondo colore di marca ai controlli.

## Applicazione ai due lati

| Schermata | Ruolo | Contenuto e azione primaria |
| --- | --- | --- |
| `connect.blade.php` | Master, uscita | Dominio del server; «Continua sul server» |
| `consent.blade.php` | Client, entrata | Origine del coordinatore e permessi nelle due direzioni; «Autorizza collegamento», con annullamento distinto |
| `choose-team.blade.php` | Master, ritorno | Server autorizzato e squadra propria da scegliere o creare; «Collega alla squadra» |
| `agents.blade.php` | Master | Squadra corrente e stati vuoto, attesa, errore, attivo; «Collega agente» |

L’intestazione distingue «Collegamento in uscita», «Collegamento in entrata» e «Ritorno al coordinatore». Sul client il marchio non punta alle route riservate al master. Il dominio del coordinatore è testo leggibile e va sempre escapato; i permessi spiegano cosa concedono le due direzioni. L’account e il tenant restano scelti e autorizzati dall’applicazione client.

Il tema riguarda le pagine PACT. Il login e la scelta del tenant dell’applicazione mantengono i propri flussi; se si vuole allinearne anche la cornice, riusare i token e le primitive in una personalizzazione locale.

## Personalizzare le viste

```sh
php artisan vendor:publish --tag=pact-views
```

Confrontare i file con le eventuali personalizzazioni già presenti in `resources/views/vendor/pact`. Trasferire insieme layout, partial e viste aggiornate. Conservare i nomi dei campi, `@csrf`, `auth_token`, gli ID del collegamento e le azioni delle route: il cambio grafico deve mantenere il percorso di consenso e annullamento esistente.

Per una pagina personalizzata che usa la stessa cornice:

```blade
@extends('pact::layout')
@section('title', 'Titolo della schermata')
@section('context', 'Collegamento in uscita')
@section('content')
    <h1>Un titolo <em>editoriale.</em></h1>
    <p class="pact-lead">Una spiegazione breve dell’azione richiesta.</p>
@endsection
```

I componenti principali sono `pact-grid`, `pact-panel`, `pact-button`, `pact-button-secondary`, `pact-text-link`, `pact-notice`, `pact-domain` e `pact-actions`. In una cornice propria si può includere `pact::partials.styles` e usare `pact-ui` sul contenitore principale, verificando il comportamento nella pagina ospite.

Con una Content Security Policy restrittiva, passare alla vista `pactStyleNonce` con un nonce casuale generato per la risposta e autorizzarlo in `style-src 'nonce-…'`. Le texture incorporate richiedono `img-src data:`. Gli eventuali script di ritorno restano protetti dal nonce dell’applicazione; non aggiungere `unsafe-inline` o origini esterne per caricare il tema.

## Adattamento e accessibilità

I moduli passano da due colonne a una sotto i 760 px. Le azioni diventano verticali sui telefoni più stretti; i controlli hanno un’altezza minima di 52 px e i link di azione un’area di almeno 48 px. I campi mantengono testo a 16 px, label persistenti, hint associati ed errori vicini al controllo con `aria-invalid` e `aria-describedby`.

La pagina consente lo zoom, include le safe area e resta a scorrimento naturale, senza barre di azione fisse che coprano campi o tastiera. Il focus è visibile, è disponibile «Vai al contenuto» e le icone decorative sono escluse dall’albero accessibile. Il consenso non attiva automaticamente la tastiera. Lo stato è comunicato anche dal testo e non solo dal colore. Non sono necessarie animazioni; le preferenze di riduzione del movimento sono rispettate.

Verificare tutte le schermate a 320, 390, 430 e 768 px, anche in landscape, con nomi lunghi, errori, navigazione da tastiera e testo al 200%. Il collaudo OAuth reale deve coprire ancora consenso, annullamento, scelta della squadra e attivazione bidirezionale dopo la personalizzazione.
