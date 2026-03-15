<?php

namespace App\Console\Commands;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\ValueObjects\Field;
use App\Domain\SchemaManagement\Services\SchemaChangePlan;
use Illuminate\Console\Command;

class BackfillCollectionFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collections:backfill-fields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill collections.fields with canonical reserved fields and order metadata';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updatedCollections = 0;

        Collection::query()
            ->where('is_system', false)
            ->orderBy('id')
            ->chunk(100, function ($collections) use (&$updatedCollections): void {
                foreach ($collections as $collection) {
                    $isAuthCollection = $collection->type === CollectionType::Auth;
                    $currentFields = $this->normalizeFields($collection->fields ?? []);
                    $mergedFields = SchemaChangePlan::mergeWithSystemFields($currentFields, $isAuthCollection);

                    if ($currentFields === $mergedFields) {
                        continue;
                    }

                    $collection->fields = $mergedFields;
                    $collection->saveQuietly();
                    $updatedCollections++;
                }
            });

        $this->info("Backfilled {$updatedCollections} collection(s).");

        return self::SUCCESS;
    }

    private function normalizeFields(array $fields): array
    {
        return collect($fields)
            ->map(function (array|Field $field): array {
                if ($field instanceof Field) {
                    return $field->toArray();
                }

                return $field;
            })
            ->values()
            ->all();
    }
}
