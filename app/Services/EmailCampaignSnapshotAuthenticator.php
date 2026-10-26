<?php

namespace App\Services;

class EmailCampaignSnapshotAuthenticator
{
    public function campaignDigest(int $campaignId, string $manifestHash, array $snapshot): string
    {
        return $this->digest(['campaign_id' => $campaignId, 'manifest_hash' => $manifestHash, 'snapshot' => $snapshot]);
    }

    public function recipientDigest(int $campaignId, int $recipientId, string $manifestHash, array $snapshot): string
    {
        return $this->digest([
            'campaign_id' => $campaignId,
            'recipient_id' => $recipientId,
            'manifest_hash' => $manifestHash,
            'snapshot' => $snapshot,
        ]);
    }

    public function verify(string $expected, string $actual): bool
    {
        return $expected !== '' && hash_equals($expected, $actual);
    }

    private function digest(array $payload): string
    {
        $key = app('encrypter')->getKey();
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash_hmac('sha256', $json, $key);
    }
}
