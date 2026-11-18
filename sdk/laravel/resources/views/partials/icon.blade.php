<svg class="pact-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('arrow-right')<path d="M5 12h14m-6-6 6 6-6 6" />@break
        @case('check')<path d="m5 12 4 4L19 6" />@break
        @case('clock')<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />@break
        @case('alert')<path d="m12 3 10 18H2L12 3Z" /><path d="M12 9v4m0 4h.01" />@break
    @endswitch
</svg>
