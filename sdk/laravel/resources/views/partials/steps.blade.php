<ol class="pact-steps" aria-label="Passaggi del collegamento">
    @foreach(['Il server', 'L’accesso', 'La squadra'] as $label)
        <li @if($loop->iteration === $step) aria-current="step" @endif><span class="pact-step-number">0{{ $loop->iteration }}</span>{{ $label }}</li>
    @endforeach
</ol>
