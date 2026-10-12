<div class="grid gap-4 sm:grid-cols-2">
    <div class="form-field">
        <label class="form-label" for="campaign-purpose">Purpose</label>
        <select id="campaign-purpose" name="purpose" class="form-select">
            <option value="marketing" @selected(old('purpose', 'marketing') === 'marketing')>Marketing</option>
            <option value="transactional" @selected(old('purpose') === 'transactional')>Transactional notice</option>
            <option value="payroll" @selected(old('purpose') === 'payroll')>Payroll / individual PDFs</option>
        </select>
        @error('purpose') <p class="form-error" role="alert">{{ $message }}</p> @enderror
    </div>
    <div class="form-field">
        <label class="form-label" for="campaign-mode">Uploaded attachments</label>
        <select id="campaign-mode" name="attachment_mode" class="form-select">
            <option value="none" @selected(old('attachment_mode', 'none') === 'none')>None</option>
            <option value="shared" @selected(old('attachment_mode') === 'shared')>Shared PDFs for everyone</option>
            <option value="recipient_specific" @selected(old('attachment_mode') === 'recipient_specific')>One personal PDF per recipient</option>
            <option value="shared_and_recipient_specific" @selected(old('attachment_mode') === 'shared_and_recipient_specific')>Shared and personal PDFs</option>
        </select>
        @error('attachment_mode') <p class="form-error" role="alert">{{ $message }}</p> @enderror
    </div>
</div>
<p class="text-sm text-[var(--color-on-surface-muted)]">Payroll requires approved policy. A template's generated PDF is an additional attachment; it does not protect uploaded PDFs.</p>
