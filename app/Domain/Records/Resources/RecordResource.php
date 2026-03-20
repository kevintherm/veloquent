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

            $isSingleRelation = (int) ($field['max_select'] ?? 0) === 1;

            if ($isSingleRelation) {
                if (is_array($data[$fieldName])) {
                    $data[$fieldName] = $data[$fieldName][0] ?? null;
                }

                continue;
            }

            if (is_string($data[$fieldName])) {
                $data[$fieldName] = [$data[$fieldName]];
            }
        }

        foreach (($this->resource->expandedRelations ?? []) as $fieldName => $expandedValue) {
            $data[$fieldName] = $expandedValue;
        }

        return $data;
    }
}
