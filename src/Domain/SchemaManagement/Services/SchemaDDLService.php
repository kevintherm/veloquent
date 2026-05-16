<?php

namespace Veloquent\Core\Domain\SchemaManagement\Services;

use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\SchemaManagement\Policies\SchemaPolicy;
use Veloquent\Core\Infrastructure\Exceptions\InvalidArgumentException;

readonly class SchemaDDLService
{
    public function __construct(
        private SchemaPolicy $namingPolicy
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    public function createTable(string $table, array $columns): void
    {
        $this->namingPolicy->assertValidTableName($table);

        $this->runDDL(function () use ($table, $columns): void {
            Schema::create($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->ulid('id')->primary();

                foreach ($columns as $column) {
                    $this->columnBlueprint($blueprint, $column);
                }

                $blueprint->dateTime('created_at')->useCurrent();
                $blueprint->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function renameTable(string $from, string $to, bool $ignoreMissingFrom = false): void
    {
        $this->namingPolicy->assertValidTableName($to);

        if (Schema::hasTable($to)) {
            throw new InvalidArgumentException('Table already exists');
        }

        if (! Schema::hasTable($from) && ! $ignoreMissingFrom) { 
            throw new InvalidArgumentException('Table does not exist');
        }

        $this->runDDL(function () use ($from, $to): void {
            Schema::rename($from, $to);
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    private function columnBlueprint(Blueprint $blueprint, array $column, ?string $after = null, bool $change = false): void
    {
        $this->namingPolicy->assertValidColumnDefinition($column);

        $name = $column['name'];
        $type = CollectionFieldType::tryFrom($column['type']);

        $col = match ($type) {
            CollectionFieldType::Text => $blueprint->string($name, 255),
            CollectionFieldType::LongText => $blueprint->text($name),
            CollectionFieldType::RichText => $blueprint->text($name),
            CollectionFieldType::Number => $blueprint->float($name),
            CollectionFieldType::Boolean => $blueprint->boolean($name),
            CollectionFieldType::Datetime => $blueprint->dateTime($name),
            CollectionFieldType::Date => $blueprint->date($name),
            CollectionFieldType::Email => $blueprint->string($name, 255),
            CollectionFieldType::Url => $blueprint->text($name),
            CollectionFieldType::Json => $blueprint->json($name),
            CollectionFieldType::File => $blueprint->json($name),
            CollectionFieldType::Relation => $blueprint->char($name, 26),
            CollectionFieldType::RelationMany => null,
            CollectionFieldType::Select => $blueprint->string($name, 255),
            default => throw new InvalidArgumentException('Unsupported column type: '.$type)
        };

        if ($type === CollectionFieldType::RelationMany) {
            return;
        }

        if (($column['nullable'] ?? false) === true) {
            $col->nullable();
        }

        if (array_key_exists('default', $column)) {
            $col->default($column['default']);
        }

        if ($change) {
            $col->change();
        }

        if ($after) {
            $col->after($after);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function applyChange(string $table, SchemaChange $change): void
    {
        $this->runDDL(function () use ($table, $change): void {
            Schema::table($table, function (Blueprint $t) use ($change) {
                foreach ($change->renames as [$from, $to]) {
                    $t->renameColumn($from, $to);
                }

                foreach ($change->modifies as [, $field]) {
                    $this->columnBlueprint($t, $field, change: true);
                }

                foreach ($change->drops as $field) {
                    $t->dropColumn($field['name']);
                }

                foreach ($change->adds as $field) {
                    $this->columnBlueprint($t, $field);
                }
            });
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    /**
     * @throws InvalidArgumentException
     */
    public function updateTable(string $table, array $before, array $after): void
    {
        $this->applyChange($table, SchemaChange::diff($before, $after));
    }

    public function deleteTable(string $table): void
    {
        $this->runDDL(function () use ($table): void {
            Schema::dropIfExists($table);
        });
    }
    
    /**
     * Creates the pivot table only if it does not already exist.
     */
    public function createPivotTable(
        string $pivotTable,
        string $sourceIdCol,
        string $targetIdCol,
        array $extraColumns = [],
    ): void {
        if (! Schema::hasTable($pivotTable)) {
            $this->runDDL(function () use ($pivotTable, $sourceIdCol, $targetIdCol, $extraColumns): void {
                Schema::create($pivotTable, function (Blueprint $blueprint) use ($pivotTable, $sourceIdCol, $targetIdCol, $extraColumns) {
                    $blueprint->ulid('id')->primary();
                    $blueprint->char($sourceIdCol, 26);
                    $blueprint->char($targetIdCol, 26);

                    $this->addPivotColumns($blueprint, $extraColumns);

                    $blueprint->dateTime('created_at')->useCurrent();
                    $blueprint->unique([$sourceIdCol, $targetIdCol], 'idx_' . md5($pivotTable . '_unq'));
                });
            });

            return;
        }

        $existingColumns = Schema::getColumnListing($pivotTable);
        $missing = [];

        if (! in_array($sourceIdCol, $existingColumns)) {
            $missing[] = ['name' => $sourceIdCol, 'type' => 'id'];
        }

        if (! in_array($targetIdCol, $existingColumns)) {
            $missing[] = ['name' => $targetIdCol, 'type' => 'id'];
        }

        if (! in_array('created_at', $existingColumns)) {
            $missing[] = ['name' => 'created_at', 'type' => 'datetime_now'];
        }

        foreach ($extraColumns as $column) {
            $name = is_array($column) ? ($column['name'] ?? null) : $column;
            if ($name && ! in_array($name, $existingColumns)) {
                $missing[] = $column;
            }
        }

        if (empty($missing)) {
            return;
        }

        $this->runDDL(function () use ($pivotTable, $missing): void {
            Schema::table($pivotTable, function (Blueprint $blueprint) use ($missing) {
                $this->addPivotColumns($blueprint, $missing);
            });
        });
    }

    private function addPivotColumns(Blueprint $blueprint, array $columns): void
    {
        foreach ($columns as $column) {
            $columnName = is_array($column) ? ($column['name'] ?? null) : $column;
            $columnType = is_array($column) ? ($column['type'] ?? 'text') : 'text';

            if (! $columnName) {
                continue;
            }

            match ($columnType) {
                'id' => $blueprint->char($columnName, 26),
                'datetime_now' => $blueprint->dateTime($columnName)->useCurrent(),
                'number' => $blueprint->double($columnName)->nullable(),
                'boolean' => $blueprint->boolean($columnName)->nullable(),
                'datetime' => $blueprint->dateTime($columnName)->nullable(),
                'json' => $blueprint->json($columnName)->nullable(),
                'longtext' => $blueprint->longText($columnName)->nullable(),
                'select' => $blueprint->string($columnName)->nullable(),
                default => $blueprint->text($columnName)->nullable(),
            };
        }
    }

    /**
     * Adds columns that are in $newColumns but not yet in the pivot table. Never drops.
     */
    public function syncPivotColumns(string $pivotTable, array $newColumns): void
    {
        if (! Schema::hasTable($pivotTable)) {
            return;
        }

        $existingColumns = Schema::getColumnListing($pivotTable);
        
        $missingColumns = array_filter($newColumns, function ($column) use ($existingColumns) {
            $name = is_array($column) ? ($column['name'] ?? null) : $column;
            return $name && ! in_array($name, $existingColumns);
        });
 
        if (empty($missingColumns)) {
            return;
        }
 
        $this->runDDL(function () use ($pivotTable, $missingColumns): void {
            Schema::table($pivotTable, function (Blueprint $blueprint) use ($missingColumns) {
                $this->addPivotColumns($blueprint, $missingColumns);
            });
        });
    }

    private function runDDL(callable $operation): void
    {
        try {
            $driverName = DB::getDriverName();
            $supportsTransactionalDDL = !in_array($driverName, ['mysql', 'mariadb']);
            
            if ($supportsTransactionalDDL) {
                $operation();

                return;
            }

            DB::transaction(function () use ($operation): void {
                $operation();
            });
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                $this->formatDDLExceptionMessage($e),
                previous: $e
            );
        }
    }

    private function formatDDLExceptionMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException) {
            $knownMessage = $this->formatKnownQueryException($exception);

            if ($knownMessage !== null) {
                return $knownMessage;
            }
        }

        $message = $this->extractDatabaseErrorMessage($exception);

        $message = preg_replace('/SQLSTATE\[[^\]]+\]:\s*/', '', $message) ?? $message;
        $message = preg_replace('/\s*\(Connection:.*$/', '', $message) ?? $message;
        $message = preg_replace('/,\s*SQL:.*$/', '', $message) ?? $message;
        $message = trim($message);

        if ($message === '') {
            return 'Schema update failed due to a database error.';
        }

        return 'Schema update failed: '.$message;
    }

    private function formatKnownQueryException(QueryException $exception): ?string
    {
        $sqlState = is_array($exception->errorInfo) ? ($exception->errorInfo[0] ?? null) : null;
        $driverErrorCode = is_array($exception->errorInfo) ? ($exception->errorInfo[1] ?? null) : null;

        if ($sqlState === '23000' || (int) $driverErrorCode === 1062) {
            return $this->formatDuplicateEntryMessage($exception);
        }

        if ($sqlState !== '22001' && (int) $driverErrorCode !== 1406) {
            return null;
        }

        $message = $this->extractDatabaseErrorMessage($exception);

        if (preg_match("/Data too long for column '([^']+)'(?: at row (\d+))?/i", $message, $matches) !== 1) {
            return 'Schema update failed: value exceeds the allowed column length.';
        }

        $column = $matches[1];
        $row = $matches[2] ?? null;

        if ($row !== null) {
            return "Schema update failed: value is too long for column '{$column}' at row {$row}.";
        }

        return "Schema update failed: value is too long for column '{$column}'.";
    }

    private function formatDuplicateEntryMessage(QueryException $exception): string
    {
        $message = $this->extractDatabaseErrorMessage($exception);

        if (preg_match("/Duplicate entry '([^']*)' for key '([^']+)'/i", $message, $matches) !== 1) {
            return 'Schema update failed: duplicate value violates a unique constraint.';
        }

        $value = $matches[1];
        $key = $matches[2];

        return "Schema update failed: duplicate value '{$value}' violates unique key '{$key}'.";
    }

    private function extractDatabaseErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException && $exception->getPrevious() !== null) {
            return $exception->getPrevious()->getMessage();
        }

        return $exception->getMessage();
    }
}
