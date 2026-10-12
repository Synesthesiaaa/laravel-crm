<fieldset class="space-y-4 border-t border-[var(--color-border)] pt-4">
    <legend class="form-label">Private PDF uploads</legend>
    <a href="{{ route('admin.email-campaigns.personalized-template') }}" class="inline-flex min-h-10 items-center text-sm text-[var(--color-primary)] hover:underline">Download personalized PDF CSV template</a>
    <p class="text-sm text-[var(--color-on-surface-muted)]">Personal files must match <code>pdf_filename</code> exactly. Use a ZIP with PDFs at its root, or select multiple PDFs. Shared files go to everyone. Maximum {{ number_format(config('email_campaigns.max_file_bytes') / 1048576) }} MB per PDF; {{ number_format(config('email_campaigns.max_total_bytes') / 1048576) }} MB total.</p>
    <div class="form-field">
        <label for="campaign-zip" class="form-label">Personal PDF ZIP</label>
        <input id="campaign-zip" name="document_zip" type="file" accept=".zip" class="form-input w-full">
        @error('document_zip') <p class="form-error" role="alert">{{ $message }}</p> @enderror
    </div>
    <div class="form-field">
        <label for="campaign-personal" class="form-label">Or select personal PDFs</label>
        <input id="campaign-personal" name="personal_pdfs[]" type="file" accept=".pdf,application/pdf" multiple class="form-input w-full">
        @error('personal_pdfs.*') <p class="form-error" role="alert">{{ $message }}</p> @enderror
    </div>
    <div class="form-field">
        <label for="campaign-shared" class="form-label">Shared PDFs — every recipient receives these</label>
        <input id="campaign-shared" name="shared_pdfs[]" type="file" accept=".pdf,application/pdf" multiple class="form-input w-full">
        @error('shared_pdfs.*') <p class="form-error" role="alert">{{ $message }}</p> @enderror
    </div>
    @error('documents') <p class="form-error" role="alert">{{ $message }}</p> @enderror
</fieldset>
