<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\RelationIntegrityService;
use App\Domain\Records\Services\UpdateRuleContextBuilder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateRecordAction
{
    public function __construct(
        private readonly RelationIntegrityService $relationIntegrityService,
    ) {}

    public function execute(Collection $collection, string $recordId, array $data): Record
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
                    ->build($collection, $record, $data, $authenticatedUser, request());

                $isAllowed = QueryFilter::for($record->newQuery(), array_keys($context))
                    ->evaluate($rule, $context);

                if (! $isAllowed) {
                    throw new AuthorizationException;
                }
            }
        }

        if ($isAuthCollection && ! $bypassApiRules && (isset($data['email']) || isset($data['password']))) {
            $manageRule = $collection->api_rules['manage'] ?? null;
            $canManageAuthFields = false;

            if ($manageRule !== null) {
                $manageRule = trim($manageRule);
                if ($manageRule === '') {
                    $canManageAuthFields = true;
                } else {
                    $context = app(UpdateRuleContextBuilder::class)
                        ->build($collection, $record, $data, $authenticatedUser, request());

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
            }
        }

        $data = array_diff_key($data, array_flip(['created_at', 'updated_at']));

        $this->relationIntegrityService->validateRelationIds($collection->fields ?? [], $data);

        $record->update($data);
        $record->fresh();

        return $record;
    }
}
