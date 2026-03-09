<?php

namespace App\Domain\Collections\Controllers;

use App\Domain\Collections\Actions\CreateCollectionAction;
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
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('list-collections', $request->user());

        $collections = Collection::get();

        return $this->successResponse($collections);
    }

    public function show(Request $request, Collection $collection): JsonResponse
    {
        Gate::authorize('view-collections', $request->user());

        return $this->successResponse($collection);
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        Gate::authorize('create-collections', $request->user(), $request->validated());

        $collection = $this->createCollectionAction->execute([
            ...$request->validated(),
            'fields' => $request->getFields(),
        ]);

        return $this->successResponse($collection);
    }

    public function update(UpdateCollectionRequest $request, Collection $collection): JsonResponse
    {
        Gate::authorize('update-collections', $request->user(), $collection);

        $collection = $this->updateCollectionAction->execute($collection, [
            ...$request->validated(),
            'fields' => $request->getFields(),
        ]);

        return $this->successResponse($collection);
    }

    public function destroy(Request $request, Collection $collection): JsonResponse
    {
        Gate::authorize('delete-collections', $request->user(), $collection);

        $collection->delete();

        return $this->successResponse([], 'Collection deleted successfully.');
    }
}
