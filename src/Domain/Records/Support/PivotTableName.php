<?php

namespace Veloquent\Core\Domain\Records\Support;

final class PivotTableName
{
    /**
     * Generate a deterministic pivot table name for two tables and a field.
     */
    public static function for(string $tableA, string $tableB, ?string $fieldName = null): string
    {
        $prefix = config('velo.collection_prefix', '_velo_');

        $cleanA = str_starts_with($tableA, $prefix) ? substr($tableA, strlen($prefix)) : $tableA;
        $cleanB = str_starts_with($tableB, $prefix) ? substr($tableB, strlen($prefix)) : $tableB;

        if ($fieldName === null) {
            $name = $cleanA . '_' . $cleanB . '_pivot';
        } else {
            $parts = [$cleanA, $cleanB];
            sort($parts);
            $name = implode('_', $parts) . '_' . $fieldName . '_pivot';
        }

        if (strlen($name) > 64) {
            return $prefix . 'pvt_' . md5($name);
        }

        return $prefix . $name;
    }
}
