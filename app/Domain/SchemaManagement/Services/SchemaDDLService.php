<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Enums\CollectionFieldType;
use App\Domain\SchemaManagement\Policies\SchemaPolicy;
use App\Infrastructure\Exceptions\InvalidArgumentException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

readonly class SchemaDDLService
{
    public function __construct(
        private SchemaPolicy $namingPolicy
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function createTable(string $table, array $columns): void
    {
        $this->namingPolicy->assertValidTableName($table);

        // Conflict action: fail if table exists

        $this->runDDL(function () use ($table, $columns): void {
            Schema::create($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->ulid('id')->primary();

                foreach ($columns as $column) {
                    $this->columnBlueprint($blueprint, $column);
                }

                $blueprint->timestamp('created_at')->useCurrent();
                $blueprint->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function renameTable(string $from, string $to): void
    {
        $this->namingPolicy->assertValidTableName($to);

        if (Schema::hasTable($to)) {
            throw new InvalidArgumentException('Table already exists');
        }

        if (!Schema::hasTable($from)) {
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
            CollectionFieldType::Number => $blueprint->float($name),
            CollectionFieldType::Boolean => $blueprint->boolean($name),
            CollectionFieldType::Datetime => $blueprint->timestamp($name),
            CollectionFieldType::Email => $blueprint->string($name, 255),
            CollectionFieldType::Url => $blueprint->text($name),
            CollectionFieldType::Json => $blueprint->json($name),
            CollectionFieldType::Relation => $blueprint->char($name, 26),
            default => throw new InvalidArgumentException('Unsupported column type: ' . $type)
        };

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
    public function updateTable(string $table, array $before, array $after, bool $isAuthCollection = false): void
    {
        $plan = SchemaChangePlan::buildPlan($before, $after, $isAuthCollection);

        $this->runDDL(function () use ($table, $plan): void {
            Schema::table($table, function (Blueprint $t) use ($plan) {
                foreach ($plan->renames as [$from, $to]) {
                    $t->renameColumn($from, $to);
                }

                foreach ($plan->modifies as [, $field]) {
                    $this->columnBlueprint($t, $field, change: true);
                }

                foreach ($plan->drops as $field) {
                    $t->dropColumn($field['name']);
                }

                foreach ($plan->adds as $field) {
                    $this->columnBlueprint($t, $field);
                }
            });
        });
    }

    public function deleteTable(string $table): void
    {
        $this->runDDL(function () use ($table): void {
            Schema::dropIfExists($table);
        });
    }

    private function runDDL(callable $operation): void
    {
        try {
            if (DB::getDriverName() === 'mysql') {
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

        return 'Schema update failed: ' . $message;
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
