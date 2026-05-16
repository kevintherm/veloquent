<?php

namespace Veloquent\Core\Domain\Records\Actions;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Veloquent\Core\Domain\Hooks\HookRunner;
use Veloquent\Core\Domain\Hooks\HookPayload;
use Veloquent\Core\Domain\Records\Models\Record;
use Illuminate\Auth\Access\AuthorizationException;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\ValueObjects\Field;
use Veloquent\Core\Domain\Records\Support\PivotTableName;
use Veloquent\Core\Domain\Records\Services\PivotSyncService;
use Veloquent\Core\Domain\QueryCompiler\Services\QueryFilter;
use Veloquent\Core\Domain\Records\Services\FileFieldProcessor;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\Records\Services\CreateRuleContextBuilder;
use Veloquent\Core\Domain\Records\Services\RelationIntegrityService;

class CreateRecordAction
{
    public function __construct(
        private readonly RelationIntegrityService $relationIntegrityService,
        private readonly FileFieldProcessor $fileFieldProcessor,
        private readonly HookRunner $hookRunner,
        private readonly PivotSyncService $pivotSyncService,
    ) {}

    public function execute(Collection $collection, array $data, ?Request $request = null): Record
    {
        Gate::authorize('create-records', $collection);

        $this->authorizeCreate($collection, $data, $request);

        $record = DB::transaction(function () use ($collection, $data, $request) {
            $preparedData = $this->runCreatingHooks($collection, $data, $request);
            $preparedData = array_diff_key($preparedData, array_flip(['created_at', 'updated_at']));

            [$mainData, $manyData] = $this->splitManyToManyData($collection, $preparedData);

            $this->validateIntegrity($collection, $mainData);
            $files = $this->fileFieldProcessor->processForCreate($collection, $mainData, $request ?? request());

            try {
                $record = Record::of($collection)->create($files['data']);

                $this->syncManyRelations($collection, $record, $manyData);

                return $record;
            } catch (Throwable $exception) {
                $this->fileFieldProcessor->deletePaths($files['stored_paths']);
                throw $exception;
            }
        });

        $this->finalize($collection, $record, $data, $request);

        return $record;
    }

    /**
     * Checks if the current user has permission to create records based on API rules.
     */
    private function authorizeCreate(Collection $collection, array $data, ?Request $request): void
    {
        $user = Auth::user();
        if ($user instanceof Record && $user->isSuperuser()) {
            return;
        }

        $rule = $collection->api_rules['create'] ?? null;
        if ($rule === null) {
            throw new AuthorizationException;
        }

        $rule = trim($rule);
        if ($rule === '') {
            return;
        }

        $context = app(CreateRuleContextBuilder::class)->build($collection, $data, $user, $request ?? request(), $rule);
        $allowed = QueryFilter::for(Record::of($collection)->newQuery(), array_keys($context))->evaluate($rule, $context);

        if (! $allowed) {
            throw new AuthorizationException;
        }
    }

    /**
     * Runs the 'creating' hooks and returns the modified data.
     */
    private function runCreatingHooks(Collection $collection, array $data, ?Request $request): array
    {
        $payload = $this->hookRunner->run(new HookPayload(
            event: 'record.creating',
            collection: $collection,
            data: $data,
            request: $request ?? request(),
            actor: Auth::user() instanceof Record ? Auth::user() : null,
        ));

        return $payload->data;
    }

    /**
     * Separates Many-to-Many field values from the main record data.
     */
    private function splitManyToManyData(Collection $collection, array $data): array
    {
        $manyFields = collect($collection->fields ?? [])->filter(fn ($f) => ($f['type'] ?? '') === CollectionFieldType::RelationMany->value);
        $main = $data;
        $many = [];

        foreach ($manyFields as $field) {
            $name = $field['name'];
            if (array_key_exists($name, $data)) {
                $many[$name] = $data[$name];
                unset($main[$name]);
            }
        }

        return [$main, $many];
    }

    /**
     * Validates that all IDs provided for relations exist and check for circular refs.
     */
    private function validateIntegrity(Collection $collection, array $data): void
    {
        $this->relationIntegrityService->validateRelationIds($collection->fields ?? [], $data);
        $this->relationIntegrityService->validateNoCircularReferences($collection, null, $data);
    }

    /**
     * Synchronizes pivot table entries for all Many-to-Many fields.
     */
    private function syncManyRelations(Collection $collection, Record $record, array $manyData): void
    {
        $fields = collect($collection->fields ?? [])->keyBy('name');

        foreach ($manyData as $name => $entries) {
            $targetId = $fields->get($name)['target_collection_id'] ?? null;
            $target = $targetId ? Collection::find($targetId) : null;

            if ($target) {
                $pivotTable = PivotTableName::for($collection->getPhysicalTableName(), $target->getPhysicalTableName(), $name);
                $this->pivotSyncService->sync(
                    $pivotTable,
                    'source_id',
                    'target_id',
                    (string) $record->getKey(),
                    (array) $entries
                );
            }
        }
    }

    /**
     * Runs 'created' hooks after the record is successfully saved.
     */
    private function finalize(Collection $collection, Record $record, array $data, ?Request $request): void
    {
        $this->hookRunner->run(new HookPayload(
            event: 'record.created',
            collection: $collection,
            record: $record,
            data: $data,
            request: $request ?? request(),
            actor: Auth::user() instanceof Record ? Auth::user() : null,
        ));
    }
}
