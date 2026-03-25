<?php

namespace App\Domain\Collections\Events;

use App\Domain\Collections\Models\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CollectionTruncated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Collection $collection,
        public int $deletedCount
    ) {}
}
