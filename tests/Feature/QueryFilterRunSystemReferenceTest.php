<?php

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;
use App\Domain\Records\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

it('resolves @request.auth reference in run mode to query bindings', function () {
    $authCollection = new Collection;
    $authCollection->name = 'users';
    $authCollection->type = CollectionType::Base;
    $authCollection->is_system = false;
    $authCollection->fields = [];
    $authCollection->api_rules = [];

    $user = Record::of($authCollection);
    $user->setAttribute('id', 21);

    Auth::setUser($user);

    $request = Request::create('/api/collections/posts/records', 'GET');
    app()->instance('request', $request);

    $query = Collection::query();

    QueryFilter::for($query, ['id'])->run('id = @request.auth.id');

    expect($query->getBindings())->toBe([21]);
});
