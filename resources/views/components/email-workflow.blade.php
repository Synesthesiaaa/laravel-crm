@props(['current' => 'prepare'])
<ol class="email-workflow" aria-label="Email campaign progress">
    @foreach(['prepare' => 'Prepare recipients', 'review' => 'Review and validate', 'delivery' => 'Delivery results'] as $key => $label)
        <li @if($current === $key) aria-current="step" @endif>
            <span class="email-workflow-number" aria-hidden="true">{{ $loop->iteration }}</span>
            <span>{{ $label }}</span>
        </li>
    @endforeach
</ol>
