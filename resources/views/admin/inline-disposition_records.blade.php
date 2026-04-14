<x-page-header title="Disposition Records"
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Disposition Records' => null]" />

<div class="md-card mb-4">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.disposition-records.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.input name="agent" label="Agent" :value="request('agent')" class="w-40" />
            <x-form.select name="disposition" label="Disposition"
                :options="$dispositionCodes->pluck('label','code')->prepend('All dispositions','')->all()"
                :selected="request('disposition')" :empty="false" />
            <x-form.input name="from_date" type="date" label="From" :value="request('from_date')" />
            <x-form.input name="to_date"   type="date" label="To"   :value="request('to_date')" />
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary"><x-icon name="funnel" class="w-4 h-4" /> Filter</button>
                    <a href="{{ route('admin.disposition-records.index') }}" class="btn-ghost">Clear</a>
                </div>
            </div>
        </form>
    </div>
</div>

<x-table.index caption="Disposition records">
    <x-table.head :columns="[
        ['label' => 'Called At'],
        ['label' => 'Phone'],
        ['label' => 'Lead ID'],
        ['label' => 'Agent'],
        ['label' => 'Disposition'],
        ['label' => 'Duration', 'align' => 'right'],
    ]" />
    @if($records->isEmpty())
        <x-table.empty :colspan="6" message="No disposition records." />
    @else
    <tbody>
        @foreach($records as $r)
            <tr>
                <td class="whitespace-nowrap text-[var(--color-on-surface-muted)] text-sm">{{ $r->called_at?->format('Y-m-d H:i:s') }}</td>
                <td class="font-mono text-sm">{{ $r->phone_number ?? '—' }}</td>
                <td class="font-mono text-sm">{{ $r->lead_id ?? '—' }}</td>
                <td>{{ $r->agent }}</td>
                <td>
                    <x-badge type="info">{{ $r->disposition_label ?? $r->disposition_code }}</x-badge>
                </td>
                <td class="text-right font-mono text-sm">{{ $r->call_duration_seconds ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
    @endif
</x-table.index>
<x-table.pagination :paginator="$records" />
