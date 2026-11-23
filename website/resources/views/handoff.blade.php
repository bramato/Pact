<section class="handoff section wrap" id="handoff" aria-labelledby="handoff-title">
    <div class="resource-heading">
        <h2 id="handoff-title">{{ $handoff['heading'] }}</h2>
        <p>{{ $handoff['intro'] }}</p>
    </div>
    <nav class="handoff-versions" aria-labelledby="handoff-version-label">
        <span class="eyebrow" id="handoff-version-label">{{ $handoff['versions'] }}</span>
        <ul>
            @foreach(array_keys($releases) as $edition)
                <li>
                    <a href="{{ $alternateUrls[$locale] }}?version={{ $edition }}#handoff" data-version="{{ $edition }}" @if($edition === $version) aria-current="true" @endif>{{ $edition }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
    <div class="handoff-release" aria-live="polite">
        <div>
            <h3>{{ strtr($handoff['changes'], [':version' => $version]) }}</h3>
            <p class="handoff-protocol">{{ strtr($handoff['protocol'], [':version' => $release['protocol']]) }}</p>
            <ul class="handoff-changes">
                @foreach($releaseCopy['changes'] as $change)
                    <li>{{ $change }}</li>
                @endforeach
            </ul>
        </div>
        <div>
            <h3>{{ $handoff['references'] }}</h3>
            <ul class="handoff-documents">
                @foreach($documents as $key => $url)
                    <li><a href="{{ $url }}">{{ $handoff['documents'][$key] }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
    @if($version === $latest)
        <div class="handoff-release">
            <div>
                <h3>{{ $handoff['unreleased_title'] }}</h3>
                <p>{{ $handoff['unreleased_progress'] }}</p>
            </div>
        </div>
    @endif
    <div class="handoff-list">
        @foreach($handoff['roles'] as $role => $roleCopy)
            <article class="handoff-entry" aria-labelledby="handoff-{{ $role }}-title">
                <h3 id="handoff-{{ $role }}-title">{{ $roleCopy['title'] }}</h3>
                <p>{{ $roleCopy['description'] }}</p>
                <button class="button button-dark handoff-copy" type="button" data-copy-target="handoff-{{ $role }}-text" data-copy-success="{{ $handoff['copied'] }}" data-copy-error="{{ $handoff['copy_error'] }}" aria-describedby="handoff-{{ $role }}-feedback" hidden><span>{{ $roleCopy['copy'] }}</span><x-icon name="copy" /></button>
                <span class="handoff-feedback" id="handoff-{{ $role }}-feedback" role="status"></span>
                <details class="handoff-preview">
                    <summary>{{ $handoff['preview'] }}<x-icon name="arrow-down" /></summary>
                    <pre id="handoff-{{ $role }}-text" tabindex="0" aria-label="{{ $roleCopy['title'] }}">{{ $roleCopy['text'] }}</pre>
                </details>
            </article>
        @endforeach
    </div>
</section>
