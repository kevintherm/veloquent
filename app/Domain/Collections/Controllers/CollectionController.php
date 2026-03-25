<?php

namespace App\Domain\Collections\Controllers;

use App\Domain\Collections\Actions\CreateCollectionAction;
use App\Domain\Collections\Actions\DeleteCollectionAction;
use App\Domain\Collections\Actions\TruncateCollectionAction;
use App\Domain\Collections\Actions\UpdateCollectionAction;
use App\Domain\Collections\Models\Collection;
use App\Domain\Collections\Requests\StoreCollectionRequest;
use App\Domain\Collections\Requests\UpdateCollectionRequest;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CollectionController extends ApiController
{
    public function __construct(
        private CreateCollectionAction $createCollectionAction,
        private UpdateCollectionAction $updateCollectionAction,
        private DeleteCollectionAction $deleteCollectionAction,
        private TruncateCollectionAction $truncateCollectionAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('list-collections');

        $filters = $request->input('filter') ?? '';
        $sort = $request->input('sort') ?? '';
        $collections = Collection::query()->applySorting($sort)->applyFilter($filters)->get();

        return $this->successResponse($collections);
    }

    public function show(Collection $collection): JsonResponse
    {
        Gate::authorize('view-collections');

        return $this->successResponse($collection);
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        Gate::authorize('create-collections', [$request->validated()]);

        $collection = $this->createCollectionAction->execute([
            ...$request->validated(),
            'fields' => $request->getFields(),
            'indexes' => $request->getIndexes(),
        ]);

        return $this->successResponse($collection);
    }

    public function update(UpdateCollectionRequest $request, Collection $collection): JsonResponse
    {
        Gate::authorize('update-collections', [$collection->toArray()]);

        $payload = $request->validated();
        if ($request->has('fields')) {
            $payload['fields'] = $request->getFields();
        }

        if ($request->has('indexes')) {
            $payload['indexes'] = $request->getIndexes();
        }

        $collection = $this->updateCollectionAction->execute($collection, $payload);

        return $this->successResponse($collection);
    }

    public function destroy(Collection $collection): JsonResponse
    {
        Gate::authorize('delete-collections', [$collection]);

        $defaultAuthCollection = config('velo.default_auth_collection');
        if ($collection->name === $defaultAuthCollection) {
            return $this->errorResponse('Cannot delete default auth collection', 400);
        }

        $this->deleteCollectionAction->execute($collection);

        return $this->successResponse([], 'Collection deleted successfully.');
    }

    public function truncate(Collection $collection): JsonResponse
    {
        Gate::authorize('truncate-collections', $collection);

        $defaultAuthCollection = config('velo.default_auth_collection');
        if ($collection->name === $defaultAuthCollection) {
            return $this->errorResponse('Cannot truncate default auth collection', 400);
        }

        $deletedCount = $this->truncateCollectionAction->execute($collection);

        return $this->successResponse([
            'deleted' => $deletedCount,
        ], 'Collection truncated successfully.');
    }
}
