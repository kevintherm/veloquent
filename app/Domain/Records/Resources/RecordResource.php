<?php

namespace App\Domain\Records\Resources;

use App\Domain\Collections\Enums\CollectionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        $data['collection_id'] = $this->resource->collection->id;
        $data['collection_name'] = $this->resource->collection->name;


        if ($this->resource->collection->type === CollectionType::Auth) {
            if ($data['email_visibility'] !== true) {
                unset($data['email']);
            }
        }

        return $data;
    }
}
