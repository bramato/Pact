<style>
    .pact-ui {
        color-scheme: light;
        --pact-paper: #eee7d7;
        --pact-paper-light: #f5efdf;
        --pact-ink: #122923;
        --pact-muted: #535c50;
        --pact-line: #c6c5b1;
        --pact-blue: #075bc7;
        --pact-blue-dark: #064ca7;
        --pact-danger: #8a2920;
        --pact-field-border: #6a7267;
        --pact-serif: Georgia, "Times New Roman", serif;
        --pact-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        --pact-mono: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        --pact-space: clamp(20px, 4vw, 48px);
        --pact-control-height: 52px;
        --pact-grain: url("data:image/svg+xml,%3Csvg%0A%20%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%0A%20%20width%3D%22240%22%0A%20%20height%3D%22240%22%0A%20%20viewBox%3D%220%200%20240%20240%22%0A%3E%0A%20%20%3Cfilter%20id%3D%22grain%22%20x%3D%220%22%20y%3D%220%22%20width%3D%22100%25%22%20height%3D%22100%25%22%3E%0A%20%20%20%20%3CfeTurbulence%0A%20%20%20%20%20%20type%3D%22fractalNoise%22%0A%20%20%20%20%20%20baseFrequency%3D%22.78%22%0A%20%20%20%20%20%20numOctaves%3D%223%22%0A%20%20%20%20%20%20stitchTiles%3D%22stitch%22%0A%20%20%20%20%2F%3E%0A%20%20%20%20%3CfeColorMatrix%20type%3D%22saturate%22%20values%3D%220%22%20%2F%3E%0A%20%20%20%20%3CfeComponentTransfer%3E%0A%20%20%20%20%20%20%3CfeFuncR%20type%3D%22discrete%22%20tableValues%3D%220%200%200%201%201%22%20%2F%3E%0A%20%20%20%20%20%20%3CfeFuncG%20type%3D%22discrete%22%20tableValues%3D%220%200%200%201%201%22%20%2F%3E%0A%20%20%20%20%20%20%3CfeFuncB%20type%3D%22discrete%22%20tableValues%3D%220%200%200%201%201%22%20%2F%3E%0A%20%20%20%20%20%20%3CfeFuncA%20type%3D%22table%22%20tableValues%3D%221%201%22%20%2F%3E%0A%20%20%20%20%3C%2FfeComponentTransfer%3E%0A%20%20%3C%2Ffilter%3E%0A%20%20%3Cpath%20fill%3D%22%23fff%22%20filter%3D%22url%28%23grain%29%22%20d%3D%22M0%200h240v240H0z%22%20%2F%3E%0A%3C%2Fsvg%3E%0A");
        margin: 0;
        background: var(--pact-blue);
        color: var(--pact-ink);
        font: 16px/1.65 var(--pact-sans);
        -webkit-font-smoothing: antialiased;
    }
    .pact-ui *, .pact-ui *::before, .pact-ui *::after { box-sizing: border-box; }
    .pact-ui ::selection { background: var(--pact-ink); color: var(--pact-paper); }
    .pact-ui a { color: inherit; text-underline-offset: 5px; text-decoration-thickness: 1px; }
    .pact-ui a:hover { color: var(--pact-blue); }
    .pact-ui :is(a, button, input, select):focus-visible { outline: 3px solid var(--pact-blue); outline-offset: 5px; }
    .pact-ui :is(h1, h2, p, ul, ol) { margin: 0; }
    .pact-ui h1, .pact-ui h2 { font-family: var(--pact-serif); letter-spacing: -.05em; line-height: 1.08; overflow-wrap: anywhere; }
    .pact-ui h1 { max-width: 13ch; font-size: clamp(38px, 5vw, 62px); }
    .pact-ui h1 em { color: var(--pact-blue); font-weight: 400; }
    .pact-ui h2 { font-size: clamp(25px, 3vw, 34px); }
    .pact-ui .pact-wrap { width: min(1120px, 100%); margin-inline: auto; padding: max(16px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(24px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left)); }
    .pact-ui .pact-masthead { display: flex; justify-content: space-between; align-items: center; gap: 24px; padding: 8px 0 24px; color: var(--pact-paper); }
    .pact-ui .pact-brand { display: inline-flex; align-items: center; gap: 12px; min-height: 52px; text-decoration: none; color: var(--pact-paper); }
    .pact-ui .pact-brand:hover { color: white; }
    .pact-ui .pact-brand-mark { width: 64px; height: 64px; flex-shrink: 0; }
    .pact-ui .pact-brand-name { font: 800 32px/1 var(--pact-serif); letter-spacing: -.05em; }
    .pact-ui .pact-caption, .pact-ui .pact-eyebrow, .pact-ui .pact-page-rule, .pact-ui .pact-step-number, .pact-ui .pact-badge { font: 12px/1.6 var(--pact-mono); }
    .pact-ui .pact-caption { max-width: 28ch; text-align: right; }
    .pact-ui .pact-paper { position: relative; isolation: isolate; padding: var(--pact-space); background: var(--pact-paper); }
    .pact-ui .pact-paper::before { content: ""; position: absolute; inset: 0; z-index: -1; pointer-events: none; opacity: .035; background: var(--pact-grain) repeat; }
    .pact-ui .pact-paper::after { content: ""; position: absolute; right: 0; bottom: -8px; left: 0; height: 16px; pointer-events: none; background: var(--pact-paper); mask: url("data:image/svg+xml,%3Csvg%0A%20%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%0A%20%20width%3D%22240%22%0A%20%20height%3D%2216%22%0A%20%20viewBox%3D%220%200%20240%2016%22%0A%3E%0A%20%20%3Cpath%0A%20%20%20%20fill%3D%22white%22%0A%20%20%20%20d%3D%22M0%2C6.50%20L2%2C4.29%20L4%2C5.19%20L6%2C5.00%20L8%2C6.85%20L10%2C6.64%20L12%2C7.41%20L14%2C4.51%20L16%2C5.72%20L18%2C4.31%20L20%2C4.99%20L22%2C6.02%20L24%2C4.30%20L26%2C4.92%20L28%2C6.54%20L30%2C6.16%20L32%2C4.99%20L34%2C6.32%20L36%2C7.11%20L38%2C4.22%20L40%2C7.10%20L42%2C6.71%20L44%2C5.42%20L46%2C4.76%20L48%2C7.65%20L50%2C5.41%20L52%2C4.53%20L54%2C4.55%20L56%2C7.25%20L58%2C6.37%20L60%2C7.11%20L62%2C6.83%20L64%2C6.13%20L66%2C7.70%20L68%2C5.56%20L70%2C6.19%20L72%2C7.19%20L74%2C6.43%20L76%2C7.30%20L78%2C6.28%20L80%2C6.74%20L82%2C4.36%20L84%2C5.02%20L86%2C5.24%20L88%2C4.49%20L90%2C5.04%20L92%2C4.56%20L94%2C5.20%20L96%2C6.49%20L98%2C5.51%20L100%2C5.53%20L102%2C4.95%20L104%2C5.16%20L106%2C7.57%20L108%2C6.53%20L110%2C6.39%20L112%2C4.82%20L114%2C6.82%20L116%2C4.79%20L118%2C5.57%20L120%2C7.76%20L122%2C6.50%20L124%2C6.21%20L126%2C6.66%20L128%2C7.23%20L130%2C6.99%20L132%2C5.02%20L134%2C4.32%20L136%2C5.34%20L138%2C5.16%20L140%2C4.96%20L142%2C7.59%20L144%2C7.35%20L146%2C5.33%20L148%2C6.56%20L150%2C5.62%20L152%2C7.49%20L154%2C5.85%20L156%2C5.15%20L158%2C5.09%20L160%2C6.22%20L162%2C5.15%20L164%2C6.30%20L166%2C7.43%20L168%2C5.64%20L170%2C4.99%20L172%2C7.79%20L174%2C6.03%20L176%2C4.53%20L178%2C4.37%20L180%2C4.59%20L182%2C6.46%20L184%2C7.05%20L186%2C5.72%20L188%2C4.43%20L190%2C5.57%20L192%2C7.79%20L194%2C6.10%20L196%2C7.70%20L198%2C7.30%20L200%2C4.24%20L202%2C6.79%20L204%2C6.65%20L206%2C6.13%20L208%2C5.16%20L210%2C6.51%20L212%2C4.60%20L214%2C5.77%20L216%2C5.83%20L218%2C7.63%20L220%2C7.35%20L222%2C5.15%20L224%2C6.00%20L226%2C4.84%20L228%2C7.49%20L230%2C7.33%20L232%2C5.27%20L234%2C6.50%20L236%2C6.39%20L238%2C4.75%20L240%2C6.95%20L240%2C16%20L0%2C16%20Z%22%0A%20%20%2F%3E%0A%3C%2Fsvg%3E%0A") repeat-x bottom / 240px 16px; }
    .pact-ui .pact-page-rule { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 24px; padding-bottom: 18px; margin-bottom: 36px; border-bottom: 1px solid var(--pact-ink); text-transform: uppercase; letter-spacing: .04em; }
    .pact-ui .pact-eyebrow { display: block; margin-bottom: 16px; color: var(--pact-blue); text-transform: uppercase; letter-spacing: .07em; }
    .pact-ui .pact-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); gap: clamp(28px, 5vw, 64px); align-items: start; }
    .pact-ui .pact-lead { margin-top: 22px; max-width: 56ch; color: var(--pact-muted); }
    .pact-ui .pact-panel { min-width: 0; padding: clamp(20px, 3vw, 32px); border: 1px solid var(--pact-ink); background: var(--pact-paper-light); }
    .pact-ui .pact-panel > :first-child { margin-top: 0; }
    .pact-ui .pact-panel-title { margin-bottom: 22px; font: 12px/1.6 var(--pact-mono); text-transform: uppercase; letter-spacing: .05em; }
    .pact-ui label { display: block; margin: 22px 0 8px; font-weight: 650; }
    .pact-ui input:not([type="hidden"]), .pact-ui select { display: block; width: 100%; min-width: 0; min-height: var(--pact-control-height); padding: 12px 14px; border: 1px solid var(--pact-field-border); border-radius: 0; background: #fffdf7; color: var(--pact-ink); font: inherit; }
    .pact-ui input::placeholder { color: var(--pact-muted); opacity: 1; }
    .pact-ui [aria-invalid="true"] { border-color: var(--pact-danger); }
    .pact-ui .pact-hint { margin-top: 10px; color: var(--pact-muted); font-size: 14px; }
    .pact-ui .pact-field-error { margin-top: 10px; color: var(--pact-danger); font-weight: 600; }
    .pact-ui .pact-button { display: inline-flex; justify-content: center; align-items: center; gap: 16px; min-height: var(--pact-control-height); padding: 13px 20px; border: 1px solid var(--pact-ink); border-radius: 0; background: var(--pact-ink); color: var(--pact-paper-light); font: 650 15px/1.5 var(--pact-sans); text-align: center; text-decoration: none; cursor: pointer; }
    .pact-ui .pact-button:hover { border-color: var(--pact-blue); background: var(--pact-blue); color: var(--pact-paper-light); }
    .pact-ui .pact-button-secondary { background: transparent; color: var(--pact-ink); }
    .pact-ui .pact-button-secondary:hover { border-color: var(--pact-ink); background: var(--pact-ink); color: var(--pact-paper-light); }
    .pact-ui .pact-text-link { display: inline-flex; align-items: center; gap: 10px; min-height: 48px; }
    .pact-ui .pact-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 26px; }
    .pact-ui .pact-actions form { margin: 0; }
    .pact-ui .pact-icon { width: 20px; height: 20px; flex-shrink: 0; }
    .pact-ui .pact-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 34px; padding: 0; list-style: none; }
    .pact-ui .pact-steps li { min-width: 0; padding-top: 12px; border-top: 1px solid var(--pact-line); color: var(--pact-muted); font-size: 14px; }
    .pact-ui .pact-steps [aria-current="step"] { border-color: var(--pact-blue); color: var(--pact-blue); font-weight: 650; }
    .pact-ui .pact-step-number { display: block; margin-bottom: 4px; }
    .pact-ui .pact-notice { padding: 16px 20px; margin-bottom: 28px; border: 1px solid var(--pact-blue); border-left-width: 4px; color: var(--pact-ink); background: var(--pact-paper-light); }
    .pact-ui .pact-notice-error { border-color: var(--pact-danger); }
    .pact-ui .pact-origin { padding-block: 15px; margin-block: 20px; border-block: 1px solid var(--pact-line); }
    .pact-ui .pact-domain { overflow-wrap: anywhere; word-break: normal; }
    .pact-ui .pact-origin .pact-domain { display: block; margin-top: 5px; font-weight: 650; }
    .pact-ui .pact-permissions { display: grid; gap: 14px; margin-top: 20px; padding-left: 20px; }
    .pact-ui .pact-permissions strong { display: block; }
    .pact-ui .pact-footer { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px 24px; padding-top: 32px; color: var(--pact-paper-light); font-size: 12px; }
    .pact-ui .pact-footer p { max-width: 48ch; }
    .pact-ui .pact-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 24px; margin-bottom: 32px; }
    .pact-ui .pact-team-switch { padding-block: 22px; border-block: 1px solid var(--pact-ink); margin-bottom: 28px; }
    .pact-ui .pact-team-switch label { margin-top: 0; }
    .pact-ui .pact-team-controls { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: start; gap: 12px; }
    .pact-ui .pact-agent-list { display: grid; gap: 20px; padding: 0; list-style: none; }
    .pact-ui .pact-agent-name { font: 650 20px/1.35 var(--pact-sans); letter-spacing: -.02em; }
    .pact-ui .pact-badge { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 16px; text-transform: uppercase; letter-spacing: .03em; }
    .pact-ui .pact-status { margin-top: 14px; color: var(--pact-muted); }
    .pact-ui .pact-empty { padding-block: 36px; }
    .pact-ui .pact-skip { position: absolute; top: -120px; left: 20px; z-index: 20; min-height: 48px; padding: 12px 16px; background: var(--pact-paper); color: var(--pact-ink); }
    .pact-ui .pact-skip:focus { top: 16px; }
    .pact-ui .pact-masthead a:focus-visible, .pact-ui .pact-footer a:focus-visible { outline-color: var(--pact-paper); }
    @media (max-width: 760px) {
        .pact-ui .pact-grid { grid-template-columns: minmax(0, 1fr); }
        .pact-ui .pact-caption { max-width: 19ch; font-size: 11px; }
        .pact-ui .pact-page-rule { margin-bottom: 28px; }
        .pact-ui h1 { max-width: 18ch; }
    }
    @media (max-width: 480px) {
        .pact-ui .pact-masthead { gap: 12px; }
        .pact-ui .pact-brand { gap: 8px; }
        .pact-ui .pact-brand-mark { width: 48px; height: 48px; }
        .pact-ui .pact-brand-name { font-size: 28px; }
        .pact-ui .pact-actions { align-items: stretch; flex-direction: column; }
        .pact-ui .pact-actions > *, .pact-ui .pact-actions form .pact-button { width: 100%; }
        .pact-ui .pact-text-link { justify-content: center; }
        .pact-ui .pact-team-controls { grid-template-columns: minmax(0, 1fr); }
    }
    @media (prefers-reduced-motion: reduce) { .pact-ui *, .pact-ui *::before, .pact-ui *::after { scroll-behavior: auto; transition: none; animation: none; } }
    @media (forced-colors: active) { .pact-ui .pact-paper::before, .pact-ui .pact-paper::after { display: none; } .pact-ui .pact-button { border: 1px solid ButtonText; } }
</style>
