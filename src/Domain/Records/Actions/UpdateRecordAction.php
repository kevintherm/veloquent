<?php

namespace Veloquent\Core\Domain\Records\Actions;

use Veloquent\Core\Domain\Collections\Enums\CollectionType;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\QueryCompiler\Services\QueryFilter;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Records\Services\FileFieldProcessor;
use Veloquent\Core\Domain\Records\Services\RelationIntegrityService;
use Veloquent\Core\Domain\Records\Services\UpdateRuleContextBuilder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateRecordAction
{
    public function __construct(
        private readonly RelationIntegrityService $relationIntegrityService,
        private readonly FileFieldProcessor $fileFieldProcessor,
    ) {}

    public function execute(Collection $collection, string $recordId, array $data, ?Request $request = null): Record
    {
        Gate::authorize('update-records', $collection);

        $isAuthCollection = $collection->type === CollectionType::Auth;
        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        $record = Record::of($collection)->findOrFail($recordId);

        if (! $bypassApiRules) {
            $rule = $collection->api_rules['update'] ?? null;

            if ($rule === null) {
                throw new AuthorizationException;
            }

            $rule = trim($rule);

            if ($rule !== '') {
                $context = app(UpdateRuleContextBuilder::class)
                    ->build($collection, $record, $data, $authenticatedUser, $request ?? request(), $rule);

                $isAllowed = QueryFilter::for($record->newQuery(), array_keys($context))
                    ->evaluate($rule, $context);

                if (! $isAllowed) {
                    throw new AuthorizationException;
                }
            }
        }

        if ($isAuthCollection && ! $bypassApiRules && (isset($data['email']) || isset($data['password']) || isset($data['verified']))) {
            $manageRule = $collection->api_rules['manage'] ?? null;
            $canManageAuthFields = false;

            if ($manageRule !== null) {
                $manageRule = trim($manageRule);
                if ($manageRule === '') {
                    $canManageAuthFields = true;
                } else {
                    $context = app(UpdateRuleContextBuilder::class)
                        ->build($collection, $record, $data, $authenticatedUser, $request ?? request(), $manageRule);

                    $canManageAuthFields = QueryFilter::for($record->newQuery(), array_keys($context))
                        ->evaluate($manageRule, $context);
                }
            }

            if (! $canManageAuthFields) {
                if (isset($data['email'])) {
                    throw ValidationException::withMessages([
                        'email' => 'Email cannot be changed directly. Use the email change flow.',
                    ]);
                }

                if (isset($data['password'])) {
                    throw ValidationException::withMessages([
                        'password' => 'Password cannot be changed directly. Use the password reset flow.',
                    ]);
                }

                unset($data['verified']);
            }
        }

        $data = array_diff_key($data, array_flip(['created_at', 'updated_at']));

        $this->relationIntegrityService->validateRelationIds($collection->fields ?? [], $data);
        $this->relationIntegrityService->validateNoCircularReferences($collection, $recordId, $data);

        $fileProcessing = $this->fileFieldProcessor->processForUpdate(
            $collection,
            $record,
            $data,
            $request ?? request(),
        );

        try {
            $record->update($fileProcessing['data']);
            $record->refresh();
        } catch (Throwable $exception) {
            $this->fileFieldProcessor->deletePaths($fileProcessing['stored_paths']);

            throw $exception;
        }

        $this->fileFieldProcessor->deletePaths($fileProcessing['pending_delete_paths']);

        return $record;
    }
}
