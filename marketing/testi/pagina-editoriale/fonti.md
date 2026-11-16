# Pagina editoriale PACT

- ID: `PACT-WEB-001`
- Destinatari: sviluppatori e lettori tecnici che incontrano PACT per la prima volta.
- Angolo: l’accordo sul lavoro precede l’esecuzione e rende verificabile la consegna.
- Lingua: italiano.
- CTA: repository pubblica `https://github.com/bramato/Pact` e documenti effettivi del progetto.
- Formato: articolo web monopagina, con illustrazioni originali del progetto, diagramma accessibile e sequenza interattiva illustrativa.
- Implementazione: `website/resources/views/pact.blade.php`; lo scenario nel browser non esegue agenti e non rappresenta il formato wire completo.

## Fonti e affermazioni

| ID | Fonte locale | Cosa sostiene |
| --- | --- | --- |
| S-001 | `README.md` | Nome esteso, bozza 0.2.0, proposta indipendente, SDK, stato e licenza MIT |
| S-002 | `docs/editorial/README.md` | Narrazione italiana: contratti, chiarimenti, avanzamento, completamento, Core e Swarm |
| S-003 | `specification/PACT-CORE-v0.2.md` | Schemi e digest, domande correlate, stato A2A autorevole, validazione dell’output |
| S-004 | `specification/PACT-SWARM-v0.2.md` | Swarm opzionale, coordinatore, dipendenze, all_required e quorum |
| S-005 | `docs/quickstart.md` | Demo deterministica TypeScript/PHP, senza API key di modelli AI |
| S-006 | `docs/implementation.md` | Confini di validazione, implementazione e operatività |
| S-007 | `docs/readme/logo.svg`, `docs/editorial/assets/00-overview.png`, `docs/readme/scene-concept.png` | Identità e illustrazioni riusate senza alterazioni |

Fonti lette il 16 novembre 2026. Le specifiche sono la base dei claim; l’interazione nel browser è un esempio didattico. Non si dichiarano certificazione, endorsement A2A, correttezza semantica garantita, SDK già pubblicate nei registry, clienti o benefici quantitativi. La demo illustrativa rende espliciti i passaggi, senza simulare una connessione reale.

La configurazione di hosting è documentata separatamente in `docs/website.md`: nessun database è richiesto dal sito; la demo di riferimento originale conserva invece i propri requisiti.
