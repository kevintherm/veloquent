<?php

namespace App\Domain\Records\Resources;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Records\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Record
 */
class RecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->getAttributes();

        if ($this->collection?->type === CollectionType::Auth) {
            unset($data['password'], $data['token_key']);
        }

        return $data;
    }
}
