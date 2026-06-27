<?php
 
namespace Veloquent\Core\Domain\Records\Actions;
 
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;
use Veloquent\Core\Domain\Collections\Enums\CollectionFieldType;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Hooks\Contracts\HookRunner;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Veloquent\Core\Domain\QueryCompiler\Services\QueryFilter;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Records\Services\FileFieldProcessor;
use Veloquent\Core\Domain\Records\Services\PivotSyncService;
use Veloquent\Core\Domain\Records\Services\RelationIntegrityService;
use Veloquent\Core\Domain\Records\Services\UpdateRuleContextBuilder;
use Veloquent\Core\Domain\RuleEngine\RuleEngine;
use Veloquent\Core\Domain\SchemaManagement\Support\PivotTableName;
 
class UpdateRecordAction
{
    public function __construct(
        private readonly RelationIntegrityService $relationIntegrityService,
        private readonly FileFieldProcessor $fileFieldProcessor,
        private readonly HookRunner $hookRunner,
        private readonly PivotSyncService $pivotSyncService,
    ) {}
 
    /**
     * Executes the update action for a specific record.
     */
    public function execute(Collection $collection, string $recordId, array $data, ?Request $request = null): Record
    {
        Gate::authorize('update-records', $collection);
 
        /** @var Record $record */
        $record = Record::of($collection)->findOrFail($recordId);
 
        $this->authorizeUpdate($collection, $record, $data, $request);
        $this->protectAuthFields($collection, $record, $data, $request);
 
        $result = DB::transaction(function () use ($collection, $record, $data, $request) {
            $preparedData = $this->runUpdatingHooks($collection, $record, $data, $request);
            $preparedData = array_diff_key($preparedData, array_flip(['created_at', 'updated_at']));
 
            [$mainData, $manyData] = $this->splitManyToManyData($collection, $preparedData);
 
            $this->validateIntegrity($collection, $record->id, $mainData);
            $files = $this->fileFieldProcessor->processForUpdate($collection, $record, $mainData, $request ?? request());
 
            try {
                $record->update($files['data']);
                $record->refresh();
 
                $this->syncManyRelations($collection, $record, $manyData);
                
                return ['record' => $record, 'files' => $files];
            } catch (Throwable $exception) {
                $this->fileFieldProcessor->deletePaths($files['stored_paths']);
                throw $exception;
            }
        });
 
        $this->finalize($collection, $result['record'], $data, $result['files'], $request);
 
        return $result['record'];
    }
 
    /**
     * Checks if the user has permission to update the record.
     */
    private function authorizeUpdate(Collection $collection, Record $record, array $data, ?Request $request): void
    {
        $user = Auth::user();
        if ($user instanceof Record && $user->isSuperuser()) {
            return;
        }
 
        $rule = $collection->api_rules['update'] ?? null;
        if ($rule === null) {
            throw new AuthorizationException;
        }
 
        $rule = trim($rule);
        if ($rule === '') {
            return;
        }
 
        $context = app(UpdateRuleContextBuilder::class)->build($collection, ['record' => $record, 'data' => $data], $user, $request ?? request(), $rule);
        $allowed = RuleEngine::make(array_keys($context))->evaluate($rule, $context);
 
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
 
    /**
     * Prevents direct updates to sensitive authentication fields by non-managers.
     */
    private function protectAuthFields(Collection $collection, Record $record, array &$data, ?Request $request): void
    {
        if ($collection->type !== CollectionType::Auth) {
            return;
        }
        
        $user = Auth::user();
        if ($user instanceof Record && $user->isSuperuser()) {
            return;
        }
 
        $authFields = ['email', 'password', 'verified'];
        $hasAuthFields = collect($authFields)->contains(fn ($f) => array_key_exists($f, $data));
        
        if (! $hasAuthFields) {
            return;
        }
 
        if (! $this->canManageAuth($collection, $record, $data, $request)) {
            if (array_key_exists('email', $data)) {
                throw ValidationException::withMessages(['email' => 'Email cannot be changed directly. Use the email change flow.']);
            }
            if (array_key_exists('password', $data)) {
                if (! array_key_exists('old_password', $data) || ! Hash::check($data['old_password'], $record->password)) {
                    throw ValidationException::withMessages(['old_password' => 'Incorrect old password.']);
                }
                unset($data['old_password']);
            }
            unset($data['verified']);
        }
    }
 
    private function canManageAuth(Collection $collection, Record $record, array $data, ?Request $request): bool
    {
        $rule = $collection->api_rules['manage'] ?? null;
        if ($rule === null) {
            return false;
        }
 
        $rule = trim($rule);
        if ($rule === '') {
            return true;
        }
 
        $context = app(UpdateRuleContextBuilder::class)->build($collection, ['record' => $record, 'data' => $data], Auth::user(), $request ?? request(), $rule);
        return QueryFilter::for($record->newQuery(), array_keys($context))->evaluate($rule, $context);
    }
 
    private function runUpdatingHooks(Collection $collection, Record $record, array $data, ?Request $request): array
    {
        $payload = $this->hookRunner->run(new HookPayload(
            event: 'record.updating',
            collection: $collection,
            record: $record,
            data: $data,
            request: $request ?? request(),
            actor: Auth::user() instanceof Record ? Auth::user() : null,
        ));
 
        return $payload->data;
    }
 
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
 
    private function validateIntegrity(Collection $collection, string $recordId, array $data): void
    {
        $this->relationIntegrityService->validateRelationIds($collection->fields ?? [], $data);
        $this->relationIntegrityService->validateNoCircularReferences($collection, $recordId, $data);
    }
 
    private function syncManyRelations(Collection $collection, Record $record, array $manyData): void
    {
        $fields = collect($collection->fields ?? [])->keyBy('name');
 
        foreach ($manyData as $name => $entries) {
            $targetId = $fields->get($name)['target_collection_id'] ?? null;
            /** @var Collection|null $target */
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
 
    private function finalize(Collection $collection, Record $record, array $data, array $files, ?Request $request): void
    {
        $this->hookRunner->run(new HookPayload(
            event: 'record.updated',
            collection: $collection,
            record: $record,
            data: $data,
            request: $request ?? request(),
            actor: Auth::user() instanceof Record ? Auth::user() : null,
        ));
 
        $this->fileFieldProcessor->deletePaths($files['pending_delete_paths']);
    }
}
