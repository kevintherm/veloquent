<?php

namespace Veloquent\Core\Domain\Hooks\ValueObjects;

use Illuminate\Http\Request;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;

class HookPayload
{
    public function __construct(
        public readonly string $event,
        public readonly Collection $collection,
        public readonly ?Record $record = null,
        public array $data = [],
        public readonly ?Request $request = null,
        public readonly mixed $actor = null,
    ) {}

    public function withData(array $data): self
    {
        $new = clone $this;
        $new->data = $data;
        return $new;
    }
}
