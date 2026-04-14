@if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
    @endif
    <x-validation-errors />
    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden max-w-2xl">
        <div class="p-6">
            <form method="POST" action="{{ route('admin.data-master.update') }}">
                @csrf
                <input type="hidden" name="_table" value="{{ $tableName }}">
                <input type="hidden" name="_id" value="{{ $record->id ?? $record['id'] }}">
                <input type="hidden" name="_type" value="{{ $type }}">
                @foreach($columns as $col)
                    @if(!in_array($col, ['id', 'created_at', 'updated_at'], true))
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">{{ $col }}</label>
                            <input type="text" name="{{ $col }}" value="{{ is_object($record) ? ($record->$col ?? '') : ($record[$col] ?? '') }}" class="w-full px-3 py-2 border border-gray-200 rounded">
                        </div>
                    @endif
                @endforeach
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Update</button>
                    <a href="{{ route('admin.data-master.index', ['type' => $type]) }}" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>
