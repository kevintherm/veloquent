<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\CreateRuleContextBuilder;
use App\Domain\Records\Services\FileFieldProcessor;
use App\Domain\Records\Services\RelationIntegrityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

class CreateRecordAction
{
    public function __construct(
        private readonly RelationIntegrityService $relationIntegrityService,
        private readonly FileFieldProcessor $fileFieldProcessor,
    ) {}

    public function execute(Collection $collection, array $data, ?Request $request = null): Record
    {
        Gate::authorize('create-records', $collection);

        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        if (! $bypassApiRules) {
            $rule = $collection->api_rules['create'] ?? null;

            if ($rule === null) {
                throw new AuthorizationException;
            }

            $rule = trim($rule);

            if ($rule !== '') {
                $context = app(CreateRuleContextBuilder::class)
                    ->build($collection, $data, $authenticatedUser, $request ?? request(), $rule);

                $isAllowed = QueryFilter::for(Record::of($collection)->newQuery(), array_keys($context))
                    ->evaluate($rule, $context);

                if (! $isAllowed) {
                    throw new AuthorizationException;
                }
            }
        }

        $data = array_diff_key($data, array_flip(['created_at', 'updated_at']));

        $this->relationIntegrityService->validateRelationIds($collection->fields ?? [], $data);
        $this->relationIntegrityService->validateNoCircularReferences($collection, null, $data);

        $fileProcessing = $this->fileFieldProcessor->processForCreate(
            $collection,
            $data,
            $request ?? request(),
        );

        try {
            return Record::of($collection)->create($fileProcessing['data']);
        } catch (Throwable $exception) {
            $this->fileFieldProcessor->deletePaths($fileProcessing['stored_paths']);

            throw $exception;
        }
    }
}
