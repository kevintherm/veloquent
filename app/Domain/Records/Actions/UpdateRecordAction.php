<?php

namespace App\Domain\Records\Actions;

use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateRecordAction
{
    public function execute(Collection $collection, string $recordId, array $data): Record
    {
        Gate::authorize('update-records', $collection);

        $isAuthCollection = $collection->type === CollectionType::Auth;
        $authenticatedUser = Auth::user();
        $bypassApiRules = $authenticatedUser instanceof Record && $authenticatedUser->isSuperuser();

        $query = Record::of($collection)->newQuery();

        if (! $bypassApiRules) {
            $query->applyRule('update');
        }

        $record = $query->findOrFail($recordId);

        if ($isAuthCollection
            && ! $bypassApiRules
            && isset($data['password'])
            && ! isset($data['old_password'])
            && ! empty($data['old_password'])) {
            throw ValidationException::withMessages([
                'old_password' => 'The old password is required when changing the password.',
            ]);
        }

        if ($isAuthCollection
            && ! $bypassApiRules
            && isset($data['password'])
            && isset($data['old_password'])
            && ! Hash::check($data['old_password'], $record->password)) {
            throw ValidationException::withMessages([
                'old_password' => 'The old password is incorrect.',
            ]);
        }

        $record->update($data);
        $record->fresh();

        return $record;
    }
}
