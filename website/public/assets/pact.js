(() => {
  const versionSelector = document.querySelector(".handoff-versions");
  if (versionSelector) {
    const versions = [...versionSelector.querySelectorAll("[data-version]")];
    const release = document.querySelector(".handoff-release");
    const prompts = [...document.querySelectorAll(".handoff-preview pre")];
    const feedback = document.querySelectorAll(".handoff-feedback");
    const languages = document.querySelectorAll(".language-switch a");
    let selected = versionSelector.querySelector("[aria-current]");
    let pending;

    async function selectVersion(link, pushHistory) {
      pending?.abort();
      if (link === selected) return;

      const request = new AbortController();
      pending = request;
      release.setAttribute("aria-busy", "true");

      try {
        const response = await fetch(link.href, {
          mode: "same-origin",
          signal: request.signal,
        });
        if (!response.ok) throw new Error("Release unavailable");

        const page = new DOMParser().parseFromString(
          await response.text(),
          "text/html",
        );
        if (request.signal.aborted) return;

        const nextRelease = page.querySelector(".handoff-release");
        const nextVersion = page.querySelector(
          ".handoff-versions [aria-current]",
        );
        const nextPrompts = prompts.map((prompt) => page.getElementById(prompt.id));
        if (
          !nextRelease ||
          nextVersion?.href !== link.href ||
          nextPrompts.includes(null)
        ) {
          throw new Error("Unexpected release content");
        }

        if (pushHistory) history.pushState(null, "", link.href);
        release.replaceChildren(...nextRelease.children);
        prompts.forEach((prompt, index) => {
          prompt.textContent = nextPrompts[index].textContent;
        });
        feedback.forEach((status) => {
          status.textContent = "";
        });
        for (const language of languages) {
          const localized = page.querySelector(
            `.language-switch a[lang="${language.lang}"]`,
          );
          if (localized) language.href = localized.href;
        }
        selected.removeAttribute("aria-current");
        link.setAttribute("aria-current", "true");
        selected = link;
      } catch {
        if (request.signal.aborted) return;
        if (pushHistory) window.location.assign(link.href);
        else window.location.reload();
      } finally {
        if (pending === request) {
          pending = undefined;
          release.removeAttribute("aria-busy");
        }
      }
    }

    versionSelector.addEventListener("click", (event) => {
      const link = event.target.closest("a[data-version]");
      if (
        !link ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
      ) return;

      event.preventDefault();
      selectVersion(link, true);
    });

    window.addEventListener("popstate", () => {
      const version = new URLSearchParams(window.location.search).get("version");
      const link =
        versions.find((option) => option.dataset.version === version) ?? versions[0];
      selectVersion(link, false);
    });
  }

  document.querySelectorAll("[data-copy-target]").forEach((button) => {
    const source = document.getElementById(button.dataset.copyTarget);
    const feedback = document.getElementById(
      button.getAttribute("aria-describedby"),
    );
    if (!source || !feedback) return;

    button.addEventListener("click", async () => {
      const text = source.textContent;
      try {
        if (navigator.clipboard?.writeText) {
          await navigator.clipboard.writeText(text);
        } else {
          // Herd's HTTP origins may not expose the Clipboard API.
          const field = document.createElement("textarea");
          field.value = text;
          field.readOnly = true;
          field.style.position = "fixed";
          field.style.opacity = "0";
          document.body.append(field);
          try {
            field.select();
            if (!document.execCommand("copy")) throw new Error("Copy failed");
          } finally {
            field.remove();
            button.focus({ preventScroll: true });
          }
        }
        if (text === source.textContent) {
          feedback.textContent = button.dataset.copySuccess;
        }
      } catch {
        if (text !== source.textContent) return;

        source.closest("details").open = true;
        source.focus();
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(source);
        selection.removeAllRanges();
        selection.addRange(range);
        feedback.textContent = button.dataset.copyError;
      }
    });
    button.hidden = false;
  });

  const tabs = document.querySelector(".demo-tabs");
  if (!tabs) return;

  const payload = document.querySelector("#demo-data");
  if (!payload) return;
  const { steps, labels } = JSON.parse(payload.textContent);

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
    next.firstChild.textContent = `${index === steps.length - 1 ? labels.restart : labels.next} `;
    copy.textContent = labels.copy;
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
      copy.textContent = labels.copied;
      feedback.textContent = labels.copy_success;
    } catch {
      copy.textContent = labels.copy_fallback;
      feedback.textContent = labels.copy_error;
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
