<?php

namespace App\Domain\Records\Resources;

use App\Domain\Collections\Enums\CollectionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

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

        if ($this->resource->collection->type === CollectionType::Auth && Auth::user()?->getTable() !== 'superusers') {
            if (isset($data['email_visibility']) && $data['email_visibility'] !== true) {
                unset($data['email']);
            }
        }

        foreach ($this->resource->collection->fields ?? [] as $field) {
            if (($field['type'] ?? null) !== 'relation') {
                continue;
            }

            $fieldName = (string) $field['name'];

            if (! array_key_exists($fieldName, $data)) {
                continue;
            }

            // relation is now always a plain string (FK), so we don't need to wrap/unwrap it here
            // it will be overwritten by expansion logic below if expanded
        }

        if (! empty($this->resource->expandedRelations)) {
            $data['expand'] = [];

            foreach ($this->resource->expandedRelations as $fieldName => $expandedValue) {
                $data['expand'][$fieldName] = $expandedValue;
            }
        }

        return $data;
    }
}
