<?php

namespace App\Domain\SchemaManagement\Services;

use App\Domain\Collections\Enums\IndexType;
use App\Domain\Collections\ValueObjects\Index;
use App\Infrastructure\Exceptions\InvalidArgumentException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

readonly class IndexSyncService
{
    /**
     * @param  array<int, Index|array<string, mixed>>  $desiredIndexes
     * @param  array<int, string>  $protectedIndexNames
     */
    public function sync(string $table, array $desiredIndexes, array $protectedIndexNames = []): void
    {
        $desiredByIdentity = collect($desiredIndexes)
            ->map(function (mixed $index): Index {
                if ($index instanceof Index) {
                    return $index;
                }

                return Index::fromArray((array) $index);
            })
            ->keyBy(fn (Index $index): string => $index->identityKey());

        $protected = array_fill_keys($protectedIndexNames, true);

        $existingByIdentity = collect($this->getManagedIndexMetadata($table))
            ->reject(fn (array $index): bool => isset($protected[$index['name']]))
            ->keyBy('identity')
            ->all();

        $toDrop = collect($existingByIdentity)
            ->reject(fn (array $index, string $identity): bool => $desiredByIdentity->has($identity))
            ->values()
            ->all();

        $toCreate = $desiredByIdentity
            ->reject(fn (Index $index, string $identity): bool => array_key_exists($identity, $existingByIdentity))
            ->values();

        $this->runDDL(function () use ($table, $toDrop, $toCreate): void {
            if ($toDrop !== []) {
                Schema::table($table, function (Blueprint $blueprint) use ($toDrop): void {
                    foreach ($toDrop as $entry) {
                        $this->dropBlueprintIndex(
                            $blueprint,
                            (string) ($entry['name'] ?? ''),
                            (string) ($entry['type'] ?? '')
                        );
                    }
                });
            }

            if ($toCreate->isNotEmpty()) {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $toCreate): void {
                    /** @var Index $index */
                    foreach ($toCreate as $index) {
                        $name = $index->generateName($table);

                        if ($index->type === IndexType::Unique->value) {
                            $blueprint->unique($index->columns, $name);

                            continue;
                        }

                        $blueprint->index($index->columns, $name);
                    }
                });
            }
        });
    }

    /**
     * @param  array<int, string>  $columnNames
     */
    public function dropIndexesReferencingColumns(string $table, array $columnNames): void
    {
        $columns = collect($columnNames)
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        $toDrop = collect($this->getManagedIndexMetadata($table))
            ->filter(function (array $index) use ($columns): bool {
                foreach ($index['columns'] as $column) {
                    if (in_array($column, $columns, true)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();

        $this->dropIndexEntries($table, $toDrop);
    }

    /**
     * @param  array<int, string>  $protectedIndexNames
     */
    public function dropManagedIndexesForPreviousTableName(string $actualTable, string $previousTable, array $protectedIndexNames = []): void
    {
        $managedIndexes = $this->getManagedIndexMetadata($actualTable);
        $protected = array_fill_keys($protectedIndexNames, true);

        $oldPrefix = $previousTable.'_';

        $toDrop = collect($managedIndexes)
            ->filter(fn (array $index): bool => str_starts_with($index['name'], $oldPrefix) && ! isset($protected[$index['name']]))
            ->values()
            ->all();

        if ($toDrop === []) {
            return;
        }

        $this->dropIndexEntries($actualTable, $toDrop);
    }

    /**
     * @param  array<int, string>  $indexNames
     */
    public function dropIndexesByNames(string $table, array $indexNames): void
    {
        $nameSet = collect($indexNames)
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();

        if ($nameSet === []) {
            return;
        }

        $toDrop = collect($this->getManagedIndexMetadata($table))
            ->filter(fn (array $index): bool => in_array($index['name'], $nameSet, true))
            ->values()
            ->all();

        $this->dropIndexEntries($table, $toDrop);
    }

    /**
     * @return array<int, array{name: string, columns: array<int, string>, type: string, identity: string}>
     */
    private function getManagedIndexMetadata(string $table): array
    {
        $indexes = Schema::getIndexes($table);
        $prefix = $table.'_';

        return collect($indexes)
            ->filter(function (array $index) use ($prefix): bool {
                $name = (string) ($index['name'] ?? '');

                if ($name === '' || ($index['primary'] ?? false) === true) {
                    return false;
                }

                if (! str_starts_with($name, $prefix)) {
                    return false;
                }

                return true;
            })
            ->map(function (array $index): array {
                $name = (string) ($index['name'] ?? '');
                $columns = collect($index['columns'] ?? [])
                    ->filter(fn (mixed $column): bool => is_string($column) && $column !== '')
                    ->values()
                    ->all();

                $isUnique = (bool) ($index['unique'] ?? false);
                $type = $isUnique ? IndexType::Unique->value : IndexType::Index->value;

                return [
                    'name' => $name,
                    'columns' => $columns,
                    'type' => $type,
                    'identity' => (new Index($columns, $type))->identityKey(),
                ];
            })
            ->values()
            ->all();
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

    /**
     * @param  array<int, array{name: string, type: string}>  $entries
     */
    private function dropIndexEntries(string $table, array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $this->runDDL(function () use ($table, $entries): void {
            Schema::table($table, function (Blueprint $blueprint) use ($entries): void {
                foreach ($entries as $entry) {
                    $this->dropBlueprintIndex(
                        $blueprint,
                        (string) ($entry['name'] ?? ''),
                        (string) ($entry['type'] ?? '')
                    );
                }
            });
        });
    }

    private function dropBlueprintIndex(Blueprint $blueprint, string $name, string $type = ''): void
    {
        if ($name === '') {
            return;
        }

        if ($type === IndexType::Unique->value || str_ends_with($name, '_unique')) {
            $blueprint->dropUnique($name);

            return;
        }

        $blueprint->dropIndex($name);
    }

    private function formatDDLExceptionMessage(Throwable $exception): string
    {
        if ($exception instanceof QueryException && $exception->getPrevious() !== null) {
            $message = $exception->getPrevious()->getMessage();
        } else {
            $message = $exception->getMessage();
        }

        $message = preg_replace('/SQLSTATE\[[^\]]+\]:\s*/', '', $message) ?? $message;
        $message = preg_replace('/\s*\(Connection:.*$/', '', $message) ?? $message;
        $message = preg_replace('/,\s*SQL:.*$/', '', $message) ?? $message;
        $message = trim($message);

        if ($message === '') {
            return 'Schema update failed due to a database error.';
        }

        return 'Schema update failed: '.$message;
    }
}
