<?php

namespace App\Services\Notifications;

use Carbon\CarbonInterface;

final readonly class NotificationItem
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public string $category,
        public string $source,
        public string $title,
        public string $message,
        public ?CarbonInterface $occurredAt,
        public string $type = 'info',
        public bool $read = false,
        public bool $detailAvailable = true,
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->key,
            'key' => $this->key,
            'category' => $this->category,
            'source' => $this->source,
            'title' => $this->title,
            'message' => $this->message,
            'time' => $this->occurredAt?->diffForHumans() ?? '',
            'created_at' => $this->occurredAt?->toIso8601String(),
            'type' => $this->type,
            'read' => $this->read,
            'detail_available' => $this->detailAvailable,
            ...$this->meta,
        ];
    }

    public function withRead(bool $read): self
    {
        return new self(
            key: $this->key,
            category: $this->category,
            source: $this->source,
            title: $this->title,
            message: $this->message,
            occurredAt: $this->occurredAt,
            type: $this->type,
            read: $read,
            detailAvailable: $this->detailAvailable,
            meta: $this->meta,
        );
    }
}
