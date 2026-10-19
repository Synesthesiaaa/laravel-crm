<?php

namespace App\Services\Sms;

use App\Models\SmsChannel;
use App\Models\SmsGatewayEvent;
use App\Models\SmsMessage;
use App\Models\SmsOptOut;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GoipReceiver
{
    public function parse(string $packet): array
    {
        if (strlen($packet) > 4096 || ! mb_check_encoding($packet, 'UTF-8') || str_contains($packet, "\0")) {
            throw new InvalidArgumentException('Malformed packet.');
        }
        $data = [];
        $parts = explode(';', $packet);
        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }
            [$key, $value] = array_pad(explode(':', $part, 2), 2, null);
            $key = strtolower($key);
            if ($value === null || isset($data[$key]) || ! preg_match('/^[a-z_]+$/D', $key)) {
                throw new InvalidArgumentException('Malformed packet.');
            }
            $data[$key] = $key === 'msg' ? implode(';', array_slice($parts, $index)) : $value;
            if ($key === 'msg') {
                $data[$key] = substr($data[$key], 4);

                break;
            }
        }

        return $data;
    }

    public function receive(string $packet, string $source): ?string
    {
        try {
            $data = $this->parse($packet);
        } catch (InvalidArgumentException) {
            return null;
        }
        if (count(array_intersect(['req', 'receive', 'deliver'], array_keys($data))) !== 1 || isset($data['deliver'])) {
            return null;
        }
        if (! in_array($source, config('sms.allowed_ips'), true)) {
            return null;
        }

        return DB::transaction(function () use ($data, $packet, $source): ?string {
            $channel = SmsChannel::with('gateway')->where('client_id', $data['id'] ?? '')->lockForUpdate()->first();
            // Inbound STOP must remain available when outbound sending is disabled.
            if (! $channel || ! $channel->gateway->inbound_verified
                || ! hash_equals((string) $channel->client_id, (string) ($data['id'] ?? ''))
                || $channel->gateway->host !== $source || ! $channel->password
                || ! hash_equals($channel->password, (string) ($data['pass'] ?? ''))) {
                return null;
            }
            $channel->update(['last_contact_at' => now()]);
            if (isset($data['req']) && ctype_digit($data['req']) && strlen($data['req']) <= 10 && ! isset($data['receive']) && ! isset($data['msg']) && ! isset($data['deliver'])) {
                return 'reg:'.$data['req'].';status:0;';
            }
            if (! isset($data['receive'], $data['srcnum'], $data['msg']) || ! ctype_digit($data['receive']) || strlen($data['receive']) > 10) {
                return null;
            }

            try {
                $phone = SmsText::phone($data['srcnum']);
            } catch (InvalidArgumentException) {
                return null;
            }
            $key = 'receive:'.$data['receive'];
            $hash = hash('sha256', $packet);
            $existing = SmsGatewayEvent::where('sms_channel_id', $channel->id)->where('event_key', $key)->first();
            if ($existing) {
                return hash_equals($existing->payload_hash, $hash) ? 'RECEIVE '.$data['receive'].' OK' : null;
            }
            $matches = \App\Models\LeadHopper::whereIn('phone_number', [$phone, substr($phone, 1), '0'.substr($phone, 3)])->limit(2)->get();
            $lead = $matches->count() === 1 ? $matches->first() : null;
            $message = SmsMessage::create([
                'sms_channel_id' => $channel->id, 'phone' => $phone, 'direction' => 'inbound', 'status' => 'received',
                'lead_hopper_id' => $lead?->id, 'recipient_name' => $lead?->client_name,
                'body' => $data['msg'], 'idempotency_key' => (string) Str::uuid(),
            ]);
            SmsGatewayEvent::create(['sms_channel_id' => $channel->id, 'event_key' => $key, 'kind' => 'receive', 'payload_hash' => $hash, 'sms_message_id' => $message->id]);
            if (in_array(mb_strtoupper(trim($data['msg'])), ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'], true)) {
                SmsOptOut::firstOrCreate(['phone' => $phone], ['source' => 'inbound_stop']);
                SmsMessage::where('phone', $phone)->where('status', 'queued')->whereHas('campaign', fn ($query) => $query->where('category', 'marketing'))
                    ->update(['status' => 'canceled', 'reason' => 'recipient_opt_out']);
                activity('sms')->performedOn($message)->log('Inbound marketing opt-out recorded');
            }

            // Returning from the transaction commits before the listener transmits the ACK.
            return 'RECEIVE '.$data['receive'].' OK';
        });
    }
}
