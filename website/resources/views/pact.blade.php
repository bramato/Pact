<!doctype html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#075bc7">
    <meta name="description" content="{{ $copy['meta']['description'] }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ $locales[$locale]['og_locale'] }}">
    @foreach($locales as $code => $language)
        @if($code !== $locale)<meta property="og:locale:alternate" content="{{ $language['og_locale'] }}">@endif
    @endforeach
    <meta property="og:title" content="{{ $copy['meta']['title'] }}">
    <meta property="og:description" content="{{ $copy['meta']['og_description'] }}">
    <meta property="og:image" content="{{ url('/assets/overview-editorial.webp') }}">
    <meta property="og:url" content="{{ $alternateUrls[$locale] }}">
    <link rel="canonical" href="{{ $alternateUrls[$locale] }}">
    @foreach($alternateUrls as $code => $href)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ $href }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alternateUrls[$defaultLocale] }}">
    <link rel="icon" href="/assets/pact-symbol.svg?v=4" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/pact.css?v=8">
    <script defer src="/assets/pact.js?v=3"></script>
    <title>{{ $copy['meta']['title'] }}</title>
</head>
<body>
    <a class="skip-link" href="#contenuto">{{ $copy['accessibility']['skip'] }}</a>
    <header class="masthead wrap">
        <a href="#inizio" class="brand" aria-label="{{ $copy['accessibility']['home'] }}"><img src="/assets/pact-logo-sticker.webp" width="1345" height="1169" alt="{{ $copy['accessibility']['logo'] }}"></a>
        <span class="masthead-note">Protocol for Agent<br>Coordination and Tasks</span>
        <nav aria-label="{{ $copy['accessibility']['nav'] }}">
            <a href="#il-protocollo">{{ $copy['nav']['protocol'] }}</a>
            <a href="#in-pratica">{{ $copy['nav']['practice'] }}</a>
            <a href="#handoff">{{ $copy['nav']['handoff'] }}</a>
            <a class="nav-source" href="https://github.com/bramato/Pact">GitHub <x-icon name="arrow-up-right" /></a>
            <div class="language-switch" role="group" aria-label="{{ $copy['accessibility']['language'] }}">
                @foreach($locales as $code => $language)
                    <a href="{{ $alternateUrls[$code] }}" lang="{{ $code }}" hreflang="{{ $code }}" aria-label="{{ $language['name'] }}" @if($code === $locale) aria-current="page" @endif>{{ strtoupper($code) }}</a>
                @endforeach
            </div>
        </nav>
    </header>

    <main id="contenuto">
        <section class="hero" id="inizio" aria-labelledby="hero-title">
            <div class="wrap">
                <div class="hero-meta"><span class="eyebrow"><span class="status-dot"></span> Protocol for Agent Coordination and Tasks</span><span class="edition">{{ $copy['edition'] }}</span></div>
                <div class="hero-grid">
                    <div class="hero-copy">
                        <h1 id="hero-title">{!! $copy['hero']['heading'] !!}</h1>
                        <p class="hero-lead">{!! $copy['hero']['lead'] !!}</p>
                        <a class="button button-dark" href="#il-protocollo">{{ $copy['hero']['discover'] }} <x-icon name="arrow-down" /></a>
                        <a class="text-link hero-secondary" href="https://github.com/bramato/Pact/blob/main/specification/PACT-CORE-v0.2.md">{{ $copy['hero']['spec'] }} <x-icon /></a>
                    </div>
                    <figure class="hero-art">
                        <span class="art-index">{{ $copy['hero']['figure'] }}</span>
                        <img src="/assets/overview-editorial.webp" width="1536" height="1024" alt="{{ $copy['hero']['alt'] }}" fetchpriority="high">
                        <figcaption><span class="caption-rule"></span>{{ $copy['hero']['caption'] }}</figcaption>
                    </figure>
                </div>
                <div class="hero-bottom"><span>{{ $copy['hero']['bottom'] }}</span><a href="#il-protocollo">{{ $copy['hero']['introduction'] }} <x-icon name="arrow-down" /></a></div>
            </div>
        </section>

        <section class="intro section wrap" id="il-protocollo" aria-labelledby="intro-title">
            <div class="section-label"><span>{{ $copy['intro']['label'] }}</span><span>{{ $copy['intro']['kicker'] }}</span></div>
            <div class="intro-grid">
                <h2 id="intro-title">{!! $copy['intro']['heading'] !!}</h2>
                <div class="prose">@foreach($copy['intro']['paragraphs'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach</div>
            </div>
            <div class="principles" aria-label="{{ $copy['accessibility']['principles'] }}">
                @foreach($copy['intro']['principles'] as $principle)
                    <div><span class="small-number">{{ ['I.', 'II.', 'III.'][$loop->index] }}</span><h3>{{ $principle['title'] }}</h3><p>{{ $principle['text'] }}</p></div>
                @endforeach
            </div>
        </section>

        <section class="chapter section wrap" aria-labelledby="contract-title">
            <div class="chapter-copy">
                <span class="eyebrow blue">{{ $copy['contract']['label'] }}</span>
                <h2 id="contract-title">{!! $copy['contract']['heading'] !!}</h2>
                <div class="prose">@foreach($copy['contract']['paragraphs'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach</div>
                <a class="text-link" href="https://github.com/bramato/Pact/blob/main/specification/PACT-CORE-v0.2.md">{{ $copy['contract']['link'] }} <x-icon /></a>
            </div>
            <div class="contract-visual">
                <div class="visual-heading"><span>{{ $copy['contract']['visual_heading'] }}</span><span class="mini-mark">PACT / CORE</span></div>
                <div class="contract-row"><span class="contract-tag">INPUT</span><div><h3>{{ $copy['contract']['input_title'] }}</h3><p>{{ $copy['contract']['input_text'] }}</p><code>{{ $copy['contract']['input_code'] }}</code></div></div>
                <div class="contract-connection"><span></span><x-icon name="arrow-down" /><span>{{ $copy['contract']['connection'] }}</span></div>
                <div class="contract-row"><span class="contract-tag output-tag">OUTPUT</span><div><h3>{{ $copy['contract']['output_title'] }}</h3><p>{{ $copy['contract']['output_text'] }}</p><code>{ "words": ["…"], "approved": true }</code></div></div>
                <div class="visual-footnote"><x-icon name="check" /> {{ $copy['contract']['footnote'] }}</div>
            </div>
        </section>

        <section class="conversation section wrap" aria-labelledby="question-title">
            <figure class="chapter-art"><img src="/assets/concept-editorial.webp" width="1536" height="1024" alt="{{ $copy['questions']['alt'] }}" loading="lazy"><figcaption>{{ $copy['questions']['caption'] }}</figcaption></figure>
            <div class="chapter-copy"><span class="eyebrow blue">{{ $copy['questions']['label'] }}</span><h2 id="question-title">{!! $copy['questions']['heading'] !!}</h2><div class="prose">@foreach($copy['questions']['paragraphs'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach</div></div>
        </section>

        <section class="demo-section" id="in-pratica" aria-labelledby="demo-title">
            <div class="wrap">
                <div class="section-label"><span>{{ $copy['demo']['label'] }}</span><span>{{ $copy['demo']['kicker'] }}</span></div>
                <div class="demo-intro"><h2 id="demo-title">{!! $copy['demo']['heading'] !!}</h2><p>{!! $copy['demo']['intro'] !!}</p></div>
                @php($firstStep = $copy['demo']['client']['steps'][0])
                <div class="demo-tabs" role="group" aria-label="{{ $copy['accessibility']['steps'] }}" hidden>
                    @foreach($copy['demo']['client']['steps'] as $step)
                        <button type="button" data-step="{{ $loop->index }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><span>0{{ $loop->iteration }}</span>{{ $step['tab'] }}</button>
                    @endforeach
                </div>
                <div class="demo-stage" aria-live="polite" aria-atomic="true">
                    <div class="demo-explanation"><span class="eyebrow" id="demo-label">{{ $firstStep['label'] }}</span><h3 id="demo-heading">{{ $firstStep['heading'] }}</h3><p id="demo-description">{{ $firstStep['description'] }}</p><div class="demo-state"><span id="demo-status">{{ $firstStep['status'] }}</span><span id="demo-progress">{{ $firstStep['progress'] }}</span></div><div class="progress-track" aria-hidden="true"><span id="demo-progress-bar"></span></div></div>
                    <div class="demo-code"><div class="code-toolbar"><span>{{ $copy['demo']['toolbar'] }}</span><button class="copy-button" type="button" hidden>{{ $copy['demo']['client']['labels']['copy'] }}</button></div><pre><code id="demo-json">{{ json_encode($firstStep['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></pre><span id="copy-feedback" class="sr-only" role="status"></span></div>
                </div>
                <div class="demo-bottom"><span>{{ $copy['demo']['note'] }}</span><button id="next-step" class="text-link" type="button" hidden>{{ $copy['demo']['client']['labels']['next'] }} <x-icon name="arrow-right" /></button></div>
                <noscript><p class="noscript-note">{{ $copy['demo']['noscript_before'] }} <a href="https://github.com/bramato/Pact/blob/main/docs/quickstart.md">{{ $copy['demo']['noscript_link'] }}</a>.</p></noscript>
                <script type="application/json" id="demo-data">@json($copy['demo']['client'])</script>
            </div>
        </section>

        <section class="swarm section wrap" id="collaborazione" aria-labelledby="swarm-title">
            <div class="section-label"><span>{{ $copy['swarm']['label'] }}</span><span>{{ $copy['swarm']['kicker'] }}</span></div>
            <div class="intro-grid"><h2 id="swarm-title">{!! $copy['swarm']['heading'] !!}</h2><div class="prose">@foreach($copy['swarm']['paragraphs'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach</div></div>
            <div class="swarm-figure" aria-label="{{ $copy['accessibility']['swarm'] }}">
                <div class="swarm-node coordinator"><span class="eyebrow">{{ $copy['swarm']['parent'] }}</span><h3>{{ $copy['swarm']['coordinator'] }}</h3><p>{{ $copy['swarm']['coordinator_text'] }}</p></div>
                <div class="swarm-line" aria-hidden="true"><span></span><x-icon name="arrow-right" /></div>
                <div class="swarm-children">@foreach($copy['swarm']['children'] as $child)<div><span>0{{ $loop->iteration }}</span><strong>{{ $child['title'] }}</strong><small>{{ $child['text'] }}</small></div>@endforeach</div>
                <div class="swarm-line" aria-hidden="true"><span></span><x-icon name="arrow-right" /></div>
                <div class="swarm-node result"><x-icon name="check" /><h3>{{ $copy['swarm']['result'] }}</h3><p>{!! $copy['swarm']['result_text'] !!}</p></div>
            </div>
            <div class="figure-caption"><span>{{ $copy['swarm']['caption'] }}</span><a class="text-link" href="https://github.com/bramato/Pact/blob/main/specification/PACT-SWARM-v0.2.md">{{ $copy['swarm']['link'] }} <x-icon /></a></div>
        </section>

        <section class="completion section wrap" aria-labelledby="completion-title">
            <div class="completion-number" aria-hidden="true">100<span>%</span><span class="number-caption">{{ $copy['completion']['number_caption'] }}</span></div>
            <div><span class="eyebrow blue">{{ $copy['completion']['label'] }}</span><h2 id="completion-title">{!! $copy['completion']['heading'] !!}</h2><div class="prose">@foreach($copy['completion']['paragraphs'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach</div></div>
        </section>

        <section class="handoff section wrap" id="handoff" aria-labelledby="handoff-title">
            <div class="section-label"><span>{{ $copy['handoff']['label'] }}</span><span>{{ $copy['handoff']['kicker'] }}</span></div>
            <div class="resource-heading"><h2 id="handoff-title">{!! $copy['handoff']['heading'] !!}</h2><p>{{ $copy['handoff']['intro'] }}</p></div>
            <div class="handoff-list">
                @foreach($copy['handoff']['roles'] as $role => $handoff)
                    <article class="handoff-entry" aria-labelledby="handoff-{{ $role }}-title">
                        <span class="eyebrow blue">Laravel / {{ $role }}</span>
                        <h3 id="handoff-{{ $role }}-title">{{ $handoff['title'] }}</h3>
                        <p>{{ $handoff['description'] }}</p>
                        <button class="button button-dark handoff-copy" type="button" data-copy-target="handoff-{{ $role }}-text" data-copy-success="{{ $copy['handoff']['copied'] }}" data-copy-error="{{ $copy['handoff']['copy_error'] }}" aria-describedby="handoff-{{ $role }}-feedback" hidden><span>{{ $handoff['copy'] }}</span><x-icon name="copy" /></button>
                        <span class="handoff-feedback" id="handoff-{{ $role }}-feedback" role="status"></span>
                        <details class="handoff-preview">
                            <summary>{{ $copy['handoff']['preview'] }}<x-icon name="arrow-down" /></summary>
                            <pre id="handoff-{{ $role }}-text" tabindex="0" aria-label="{{ $handoff['title'] }}">{{ $handoff['text'] }}</pre>
                        </details>
                    </article>
                @endforeach
            </div>
            <p class="handoff-note">{{ $copy['handoff']['note'] }} <a href="https://github.com/bramato/Pact/blob/v0.2.1/docs/laravel-installation.md">{{ $copy['handoff']['guide'] }}</a>.</p>
        </section>

        <section class="resources section wrap" id="risorse" aria-labelledby="resources-title">
            <div class="section-label"><span>{{ $copy['resources']['label'] }}</span><span>{{ $copy['resources']['kicker'] }}</span></div>
            <div class="resource-heading"><h2 id="resources-title">{!! $copy['resources']['heading'] !!}</h2><p>{{ $copy['resources']['intro'] }}</p></div>
            <div class="resource-list">
                @foreach($copy['resources']['items'] as $item)
                    <a class="resource-row" href="https://github.com/bramato/Pact/{{ str_starts_with($item['path'], 'tree/') ? $item['path'] : 'blob/main/'.$item['path'] }}"><span class="resource-number">0{{ $loop->iteration }}</span><div><h3>{{ $item['title'] }}</h3><p>{{ $item['description'] }}</p></div><span class="resource-destination">{{ $item['label'] }} <x-icon /></span></a>
                @endforeach
            </div>
        </section>

        <section class="faq section wrap" aria-labelledby="faq-title"><div><span class="eyebrow blue">{{ $copy['faq']['label'] }}</span><h2 id="faq-title">{!! $copy['faq']['heading'] !!}</h2></div><div class="faq-list">
            @foreach($copy['faq']['items'] as $item)
                <details><summary>{{ $item['question'] }}<x-icon name="arrow-down" /></summary><p>{{ $item['answer'] }}</p></details>
            @endforeach
        </div></section>

        <section class="closing" aria-labelledby="closing-title"><div class="closing-inner wrap"><div><span class="eyebrow">{{ $copy['closing']['eyebrow'] }}</span><h2 id="closing-title">{!! $copy['closing']['heading'] !!}</h2></div><a class="button button-dark" href="https://github.com/bramato/Pact">{{ $copy['closing']['cta'] }} <x-icon name="github" /></a></div></section>
    </main>

    <footer class="footer wrap"><a class="brand" href="#inizio" aria-label="{{ $copy['accessibility']['home'] }}"><img src="/assets/pact-logo-sticker.webp" width="1345" height="1169" alt="{{ $copy['accessibility']['logo'] }}"></a><span>Protocol for Agent Coordination and Tasks</span><div><span>{{ $copy['footer']['edition'] }}</span><a href="https://github.com/bramato/Pact/blob/main/GOVERNANCE.md">{{ $copy['footer']['governance'] }} <x-icon /></a></div></footer>
</body>
</html>
