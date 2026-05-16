<?php

namespace Veloquent\Core\Domain\Records\Support;

final class PivotTableName
{
    /**
     * Generate a deterministic pivot table name for two tables and a field.
     */
    public static function for(string $tableA, string $tableB, string $fieldName): string
    {
        $parts = [$tableA, $tableB];
        sort($parts);

        $name = implode('_', $parts).'_'.$fieldName.'_pivot';

        if (strlen($name) > 64) {
            $prefix = config('velo.collection_prefix', '_velo_');
            return $prefix . 'pvt_' . md5($name);
        }

        return $name;
    }
}
