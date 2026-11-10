<?php

namespace App\Services\Email;

use App\Models\EmailOptOut;
use App\Models\EmailSuppression;
use Illuminate\Validation\ValidationException;

class SuppressionService
{
    public function blocked(string $email, string $purpose): bool
    {
        $email = strtolower(trim($email));
        if ($purpose === 'marketing' && EmailOptOut::where('email', $email)->exists()) {
            return true;
        }

        return EmailSuppression::where('email', $email)->whereIn('purpose', ['all', $purpose])->exists();
    }

    public function suppress(string $email, string $reason, string $purpose = 'all', string $source = 'manual', ?int $actor = null): EmailSuppression
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
            || ! in_array($reason, ['manual', 'hard_bounce', 'complaint', 'unsubscribe'], true)
            || ! in_array($purpose, ['all', 'marketing', 'transactional', 'payroll'], true)) {
            throw ValidationException::withMessages(['suppression' => 'Invalid suppression address, reason or purpose.']);
        }

        return EmailSuppression::firstOrCreate(
            ['email' => $email, 'reason' => $reason, 'purpose' => $purpose],
            ['source' => mb_substr($source, 0, 40), 'created_by_user_id' => $actor],
        );
    }
}
