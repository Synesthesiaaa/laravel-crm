@if ($errors->any())
<x-alert type="error" :dismissible="true" class="mb-4">
    <ul class="list-disc list-inside space-y-0.5">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</x-alert>
@endif
