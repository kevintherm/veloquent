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
        $desiredByName = collect($desiredIndexes)
            ->map(function (mixed $index): Index {
                if ($index instanceof Index) {
                    return $index;
                }

                return Index::fromArray((array) $index);
            })
            ->keyBy(fn (Index $index): string => $index->generateName($table));

        $protected = array_fill_keys($protectedIndexNames, true);

        $existingManagedNames = $this->getManagedIndexNames($table);
        $existingManagedNames = array_values(array_filter(
            $existingManagedNames,
            fn (string $name): bool => ! isset($protected[$name])
        ));

        $desiredNames = $desiredByName->keys()->all();

        $toDrop = array_values(array_diff($existingManagedNames, $desiredNames));
        $toCreate = array_values(array_diff($desiredNames, $existingManagedNames));

        $this->runDDL(function () use ($table, $toDrop, $toCreate, $desiredByName): void {
            if ($toDrop !== []) {
                Schema::table($table, function (Blueprint $blueprint) use ($toDrop): void {
                    foreach ($toDrop as $name) {
                        $this->dropBlueprintIndex($blueprint, $name);
                    }
                });
            }

            if ($toCreate !== []) {
                Schema::table($table, function (Blueprint $blueprint) use ($toCreate, $desiredByName): void {
                    foreach ($toCreate as $name) {
                        /** @var Index $index */
                        $index = $desiredByName->get($name);

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
     * @param  array<int, string>  $protectedIndexNames
     */
    public function dropManagedIndexesForPreviousTableName(string $actualTable, string $previousTable, array $protectedIndexNames = []): void
    {
        $managedNames = $this->getManagedIndexNames($actualTable);
        $protected = array_fill_keys($protectedIndexNames, true);

        $oldPrefix = $previousTable.'_';

        $toDrop = array_values(array_filter(
            $managedNames,
            fn (string $name): bool => str_starts_with($name, $oldPrefix) && ! isset($protected[$name])
        ));

        if ($toDrop === []) {
            return;
        }

        $this->runDDL(function () use ($actualTable, $toDrop): void {
            Schema::table($actualTable, function (Blueprint $blueprint) use ($toDrop): void {
                foreach ($toDrop as $name) {
                    $this->dropBlueprintIndex($blueprint, $name);
                }
            });
        });
    }

    /**
     * @param  array<int, string>  $indexNames
     */
    public function dropIndexesByNames(string $table, array $indexNames): void
    {
        $toDrop = collect($indexNames)
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();

        if ($toDrop === []) {
            return;
        }

        $this->runDDL(function () use ($table, $toDrop): void {
            Schema::table($table, function (Blueprint $blueprint) use ($toDrop): void {
                foreach ($toDrop as $name) {
                    $this->dropBlueprintIndex($blueprint, $name);
                }
            });
        });
    }

    /**
     * @return array<int, string>
     */
    private function getManagedIndexNames(string $table): array
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

                return str_ends_with($name, '_index') || str_ends_with($name, '_unique');
            })
            ->pluck('name')
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

    private function dropBlueprintIndex(Blueprint $blueprint, string $name): void
    {
        if (str_ends_with($name, '_unique')) {
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
