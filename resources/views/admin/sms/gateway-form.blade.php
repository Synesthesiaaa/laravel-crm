@php($prefix = 'gateway-'.($gateway?->id ?? 'new'))
<form method="POST" action="{{ $gateway ? route('admin.sms.gateways.update', $gateway) : route('admin.sms.gateways.store') }}" class="space-y-4 mt-3">
@csrf @if($gateway) @method('PUT') @endif
<div class="grid gap-4 md:grid-cols-2">
@foreach(['name'=>'Gateway name', 'host'=>'Allowlisted IPv4 address', 'username'=>'HTTP username', 'firmware'=>'Exact firmware build'] as $field=>$label)
<div><label class="form-label" for="{{ $prefix }}-{{ $field }}">{{ $label }}</label><input class="form-input" id="{{ $prefix }}-{{ $field }}" name="{{ $field }}" value="{{ $gateway?->$field }}" required maxlength="{{ $field === 'name' ? 150 : 100 }}"></div>
@endforeach
<div><label class="form-label" for="{{ $prefix }}-port">HTTP transport port</label><input class="form-input" id="{{ $prefix }}-port" type="number" name="port" min="1" max="65535" value="{{ $gateway?->port ?? 80 }}" required></div>
<div><label class="form-label" for="{{ $prefix }}-password">HTTP password {{ $gateway ? '(blank preserves existing)' : '' }}</label><input class="form-input" id="{{ $prefix }}-password" name="password" type="password" autocomplete="new-password" maxlength="255" @required(!$gateway)></div>
</div>
<p class="text-sm">Transport: legacy HTTP send.html profile. Credentials travel in a legacy query string; isolate gateway traffic and disable proxy request logging. UDP {{ config('sms.receiver_port') }} is the configured inbound receiver port, not an assumed outbound command port.</p>
<label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="enabled" value="1" @checked($gateway?->enabled)> Gateway enabled</label>
<label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="verified" value="1"> I verified the configured transport profile on this exact firmware.</label>
<label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="inbound_verified" value="1" @checked($gateway?->inbound_verified)> The authenticated req/RECEIVE inbound profile is separately verified.</label>
<div><label class="form-label" for="{{ $prefix }}-note">Verification evidence · reference, date and interfaces tested</label><textarea class="form-input" id="{{ $prefix }}-note" name="verification_note" minlength="30" maxlength="2000">{{ $gateway?->verification_note }}</textarea><p class="text-sm mt-2">Saving without the verification checkbox clears transport verification. Firmware identification alone does not verify compatibility.</p></div>
<button class="btn-primary">Save gateway configuration</button>
</form>
