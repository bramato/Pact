@props(['name' => 'arrow-up-right'])
<svg {{ $attributes->merge(['class' => 'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('arrow-right')<path d="M5 12h14m-6-6 6 6-6 6"/>@break
        @case('arrow-down')<path d="M12 5v14m-6-6 6 6 6-6"/>@break
        @case('check')<path d="m5 12 4 4L19 6"/>@break
        @case('github')<path d="M9 19c-4.3 1.3-4.3-2.2-6-2.7m12 5v-3.4a3 3 0 0 0-.8-2.3c2.7-.3 5.5-1.3 5.5-6a4.7 4.7 0 0 0-1.3-3.3 4.3 4.3 0 0 0-.1-3.3S17.3 2.7 15 4a12 12 0 0 0-6 0C6.7 2.7 5.7 3 5.7 3a4.3 4.3 0 0 0-.1 3.3 4.7 4.7 0 0 0-1.3 3.3c0 4.7 2.8 5.7 5.5 6a3 3 0 0 0-.8 2.3v3.4"/>@break
        @default<path d="M7 17 17 7M7 7h10v10"/>
    @endswitch
</svg>
