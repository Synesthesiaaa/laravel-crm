@props(['row', 'tableName', 'type', 'class' => 'table-actions'])

@php
    $recordId = is_object($row) ? $row->id : ($row['id'] ?? null);
@endphp

<div class="{{ $class }}" x-data="{ async del(form) {
    const ok = await Alpine.store('confirm').ask('Delete record?', 'This record will be permanently removed.');
    if (ok) form.submit();
}}">
    <a href="{{ route('admin.data-master.edit', ['id' => $recordId, 'type' => $type]) }}"
       class="btn-secondary text-xs px-2 py-1">
        <x-icon name="pencil" class="w-3.5 h-3.5" />
        Edit
    </a>
    <form method="POST" action="{{ route('admin.data-master.destroy') }}" x-ref="delFormDM{{ $recordId }}">
        @csrf
        <input type="hidden" name="_table" value="{{ $tableName }}">
        <input type="hidden" name="_id" value="{{ $recordId }}">
        <input type="hidden" name="_type" value="{{ $type }}">
        <button type="button" class="btn-danger text-xs px-2 py-1"
                @click="del($refs['delFormDM{{ $recordId }}'])">
            <x-icon name="trash" class="w-3.5 h-3.5" />
            Delete
        </button>
    </form>
</div>
