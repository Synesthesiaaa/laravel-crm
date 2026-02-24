@props(['type' => 'primary', 'dot' => true])
@php
$types = ['active' => 'badge-active', 'inactive' => 'badge-inactive', 'pending' => 'badge-pending', 'error' => 'badge-error', 'primary' => 'badge-primary', 'info' => 'badge-info'];
$cls   = $types[$type] ?? 'badge-primary';
@endphp
<span class="badge {{ $cls }}">
    @if($dot)<span class="badge-dot" style="background: currentColor"></span>@endif
    {{ $slot }}
</span>
