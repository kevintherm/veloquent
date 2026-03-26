<?php

namespace App\Domain\Auth\ValueObjects;

use App\Domain\Records\Models\Record;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class TokenData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $token,
        public int $expires_in,
        public string $collection_id,
        public string $collection_name,
        public ?string $record_id = null,
        public ?Record $record = null,
    ) {}

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'expires_in' => $this->expires_in,
            'collection_id' => $this->collection_id,
            'collection_name' => $this->collection_name,
            'record_id' => $this->record_id,
            'record' => $this->record,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            expires_in: $data['expires_in'],
            collection_id: $data['collection_id'],
            collection_name: $data['collection_name'],
            record_id: $data['record_id'] ?? null,
            record: $data['record'] ?? null,
        );
    }
}
