@if ($errors->any())
    <div class="alert-error mb-6" role="alert">
        <p class="font-semibold mb-2 text-[var(--color-on-surface)]">Please fix the following errors:</p>
        <ul class="list-disc pl-5 space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
