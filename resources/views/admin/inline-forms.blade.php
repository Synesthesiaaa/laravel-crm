@if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
    @endif
    <x-validation-errors />

    <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden mb-6">
        <div class="p-6 border-b border-gray-100">
            <form method="GET" action="{{ route('admin.forms.index') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Campaign</label>
                    <select name="campaign" class="px-3 py-2 border border-gray-200 rounded">
                        @foreach($campaigns as $c)
                            <option value="{{ $c->code }}" {{ $selectedCampaign === $c->code ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded">Load</button>
            </form>
        </div>
        <div class="p-6 border-b border-gray-100 bg-gray-50">
            <h3 class="font-bold text-gray-900 mb-2">Add form</h3>
            <form method="POST" action="{{ route('admin.forms.store') }}" class="flex flex-wrap gap-4 items-end">
                @csrf
                <input type="hidden" name="campaign_code" value="{{ $selectedCampaign }}">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Form code (a-z, 0-9, _)</label>
                    <input type="text" name="form_code" value="{{ old('form_code') }}" required pattern="[a-z0-9_]+" class="px-3 py-2 border rounded @error('form_code') border-red-500 @enderror" placeholder="e.g. ezycash">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="px-3 py-2 border rounded @error('name') border-red-500 @enderror">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Table name</label>
                    <input type="text" name="table_name" value="{{ old('table_name') }}" required class="px-3 py-2 border rounded @error('table_name') border-red-500 @enderror">
                </div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Add</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Form code</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Name</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-700">Table</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($forms as $f)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4">{{ $f->form_code }}</td>
                            <td class="py-3 px-4">{{ $f->name }}</td>
                            <td class="py-3 px-4">{{ $f->table_name }}</td>
                            <td class="py-3 px-4 text-right">
                                <button type="button" onclick="document.getElementById('edit-form-{{ $f->id }}').classList.toggle('hidden'); this.textContent = document.getElementById('edit-form-{{ $f->id }}').classList.contains('hidden') ? 'Edit' : 'Cancel';" class="text-indigo-600 hover:underline text-xs mr-2">Edit</button>
                                <a href="{{ route('admin.field-logic.index', ['form' => $f->form_code]) }}" class="text-indigo-600 hover:underline text-xs mr-2">Fields</a>
                                <form method="POST" action="{{ route('admin.forms.destroy') }}" class="inline" onsubmit="return confirm('Deactivate this form?');">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $f->id }}">
                                    <button type="submit" class="text-red-600 hover:underline text-xs">Deactivate</button>
                                </form>
                            </td>
                        </tr>
                        <tr id="edit-form-{{ $f->id }}" class="hidden bg-indigo-50 border-b border-gray-100">
                            <td colspan="4" class="py-4 px-4">
                                <form method="POST" action="{{ route('admin.forms.update', $f) }}" class="flex flex-wrap gap-4 items-end">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="campaign_code" value="{{ $f->campaign_code }}">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Form code</label>
                                        <input type="text" name="form_code" value="{{ old('form_code', $f->form_code) }}" required pattern="[a-z0-9_]+" class="px-3 py-2 border rounded w-32 @error('form_code') border-red-500 @enderror">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Name</label>
                                        <input type="text" name="name" value="{{ old('name', $f->name) }}" required class="px-3 py-2 border rounded w-48 @error('name') border-red-500 @enderror">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Table name</label>
                                        <input type="text" name="table_name" value="{{ old('table_name', $f->table_name) }}" required class="px-3 py-2 border rounded w-40 @error('table_name') border-red-500 @enderror">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" id="form-active-{{ $f->id }}" {{ old('is_active', $f->is_active) ? 'checked' : '' }}>
                                        <label for="form-active-{{ $f->id }}" class="text-sm">Active</label>
                                    </div>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Update</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 px-4 text-center text-gray-500">No forms for this campaign.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
