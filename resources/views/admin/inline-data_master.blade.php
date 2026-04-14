<x-page-header title="Data Master" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Data Master' => null]" />

<x-validation-errors />

<div class="md-card mb-4">
    <div class="p-4">
        <form method="GET" action="{{ route('admin.data-master.index') }}" class="flex flex-wrap items-end gap-4">
            <x-form.select name="type" label="Form Type"
                :options="collect($forms)->mapWithKeys(fn($v,$k) => [$k => $v['name'] ?? $k])->all()"
                :selected="$type" :empty="false" />
            <div class="form-field">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn-primary"><x-icon name="funnel" class="w-4 h-4" /> Load</button>
            </div>
        </form>
    </div>
</div>

@if($tableName)
<x-table.index caption="Data master records">
    <thead>
        <tr>
            @foreach($columns as $col)
                <th>{{ $col }}</th>
            @endforeach
            <th style="text-align: right">Actions</th>
        </tr>
    </thead>
    @if($records->isEmpty())
        <x-table.empty :colspan="count($columns) + 1" message="No records found." />
    @else
    <tbody>
        @foreach($records as $row)
            <tr>
                @foreach($columns as $col)
                    <td>{{ is_object($row) ? ($row->$col ?? '') : ($row[$col] ?? '') }}</td>
                @endforeach
                <td>
                    <div class="table-actions" x-data="{ async del(form) {
                        const ok = await Alpine.store('confirm').ask('Delete record?', 'This record will be permanently removed.');
                        if (ok) form.submit();
                    }}">
                        <a href="{{ route('admin.data-master.edit', ['id' => $row->id ?? $row['id'], 'type' => $type]) }}"
                           class="btn-secondary text-xs px-2 py-1">
                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                            Edit
                        </a>
                        <form method="POST" action="{{ route('admin.data-master.destroy') }}" x-ref="delFormDM{{ $row->id ?? $row['id'] }}">
                            @csrf
                            <input type="hidden" name="_table" value="{{ $tableName }}">
                            <input type="hidden" name="_id" value="{{ $row->id ?? $row['id'] }}">
                            <input type="hidden" name="_type" value="{{ $type }}">
                            <button type="button" class="btn-danger text-xs px-2 py-1"
                                    @click="del($refs['delFormDM{{ $row->id ?? $row['id'] }}'])">
                                <x-icon name="trash" class="w-3.5 h-3.5" />
                                Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
    @endif
</x-table.index>
<x-table.pagination :paginator="$records" />
@else
<div class="md-card">
    <div class="table-empty py-12">
        <x-icon name="list-bullet" class="w-10 h-10 mx-auto mb-2" />
        <p class="text-sm font-medium">Select a form type to load records.</p>
    </div>
</div>
@endif
