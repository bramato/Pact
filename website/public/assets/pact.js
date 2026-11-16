(() => {
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
