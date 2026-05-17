<?php

namespace Veloquent\Core\Domain\SchemaManagement\Support;

final class TableName {
    public static function for(string $collectionName, bool $isSystem = false): string
    {
        if ($isSystem) {
            return $collectionName;
        }
        
        return config('velo.collection_prefix', '_velo_') . $collectionName;
    }
}