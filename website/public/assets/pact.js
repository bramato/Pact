(() => {
  const tabs = document.querySelector(".demo-tabs");
  if (!tabs) return;

  const steps = [
    {
      label: "01 / Accordo",
      heading: "Prima di iniziare, lo stesso contratto.",
      description:
        "Il chiamante legge l’Agent Card e negozia l’estensione PACT e il contratto supportato. Le due parti verificano quali schemi useranno per input e output.",
      status: "Negoziazione dell’estensione",
      progress: "Prima del task",
      percent: 0,
      data: {
        extension: "PACT Core 0.2",
        agreement: {
          input: "text",
          output: ["words", "approved"],
          schemas: "verificati da entrambe le parti",
        },
      },
    },
    {
      label: "02 / Incarico",
      heading: "Il lavoro parte da un input valido.",
      description:
        "Il chiamante invia il testo nella struttura concordata. L’esecutore verifica l’input e avvia il task. Lo stato nativo A2A è WORKING; PACT aggiunge i dettagli dell’avanzamento.",
      status: "A2A · WORKING",
      progress: "Avanzamento illustrativo · 20%",
      percent: 20,
      data: {
        input: { text: "Gli agenti collaborano con un accordo esplicito." },
        task: { status: "WORKING" },
        progress: { stage: "elaborazione", percent: 20 },
      },
    },
    {
      label: "03 / Chiarimento",
      heading: "Manca un dettaglio. Si chiede.",
      description:
        "L’esecutore deve sapere quante parole restituire. Apre una domanda obbligatoria con risposte ammesse. A2A segnala INPUT_REQUIRED: la domanda deve essere risolta prima di riprendere.",
      status: "A2A · INPUT_REQUIRED",
      progress: "In attesa di una risposta",
      percent: 40,
      data: {
        task: { status: "INPUT_REQUIRED" },
        question: {
          id: "q-parole",
          text: "Quante parole vuoi nel risultato?",
          allowed: [3, 5, 7],
          required: true,
        },
      },
    },
    {
      label: "04 / Ripresa",
      heading: "Una risposta collegata alla sua domanda.",
      description:
        "Il chiamante sceglie cinque parole e identifica la domanda a cui risponde. L’esecutore valida la risposta. Quando tutte le domande obbligatorie che bloccano il task sono risolte, il lavoro riparte.",
      status: "A2A · WORKING",
      progress: "Avanzamento illustrativo · 60%",
      percent: 60,
      data: {
        answer: { questionId: "q-parole", value: 5 },
        validation: "risposta ammessa",
        task: { status: "WORKING" },
      },
    },
    {
      label: "05 / Consegna",
      heading: "Il risultato rispetta l’accordo.",
      description:
        "L’esecutore consegna l’artefatto e il chiamante ne verifica i dati rispetto allo schema. L’output valido e l’assenza di domande obbligatorie irrisolte consentono il completamento. Il solo 100% non sarebbe sufficiente.",
      status: "A2A · COMPLETED",
      progress: "Output validato",
      percent: 100,
      data: {
        artifact: {
          words: ["Gli", "agenti", "collaborano", "con", "un"],
          approved: true,
        },
        outputValidation: "valido",
        task: { status: "COMPLETED" },
      },
    },
  ];

  let current = 0;
  const next = document.querySelector("#next-step");
  const copy = document.querySelector(".copy-button");
  const json = document.querySelector("#demo-json");
  const feedback = document.querySelector("#copy-feedback");

  function render(index) {
    current = index;
    const step = steps[index];
    for (const field of [
      "label",
      "heading",
      "description",
      "status",
      "progress",
    ]) {
      document.querySelector(`#demo-${field}`).textContent = step[field];
    }
    json.textContent = JSON.stringify(step.data, null, 2);
    document.querySelector("#demo-progress-bar").style.width =
      `${step.percent}%`;
    tabs
      .querySelectorAll("button")
      .forEach((button, i) =>
        button.setAttribute("aria-pressed", String(i === index)),
      );
    next.firstChild.textContent =
      index === steps.length - 1 ? "Ricomincia " : "Passaggio successivo ";
    copy.textContent = "Copia";
    feedback.textContent = "";
  }

  tabs
    .querySelectorAll("button")
    .forEach((button, index) =>
      button.addEventListener("click", () => render(index)),
    );
  next.addEventListener("click", () => render((current + 1) % steps.length));
  copy.addEventListener("click", async () => {
    try {
      await navigator.clipboard.writeText(json.textContent);
      copy.textContent = "Copiato";
      feedback.textContent = "Esempio copiato negli appunti.";
    } catch {
      copy.textContent = "Seleziona il codice";
      feedback.textContent =
        "Copia automatica non disponibile: seleziona il codice e copialo manualmente.";
      const selection = window.getSelection();
      const range = document.createRange();
      range.selectNodeContents(json);
      selection.removeAllRanges();
      selection.addRange(range);
    }
  });

  tabs.hidden = false;
  next.hidden = false;
  copy.hidden = false;
  render(0);
})();
