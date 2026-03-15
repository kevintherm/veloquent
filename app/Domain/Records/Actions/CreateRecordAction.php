<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\Records\Models\Record;
use App\Domain\Records\Services\CreateRuleContextBuilder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CreateRecordAction
{
    public function execute(Collection $collection, array $data): Record
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
                    ->build($collection, $data, $authenticatedUser, request());

                $isAllowed = QueryFilter::for(Record::of($collection)->newQuery(), array_keys($context))
                    ->evaluate($rule, $context);

                if (! $isAllowed) {
                    throw new AuthorizationException;
                }
            }
        }

        return Record::of($collection)->create($data);
    }
}
