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
    public function execute(Collection $collection, string $recordId, array $data): ?array
    {
        Gate::authorize('update-records', $collection);

        $isAuthCollection = $collection->type === CollectionType::Auth;
        $bypassSuperuser = Auth::user()?->getTable() === 'superusers';

        $record = Record::of($collection)
            ->applyRule('update')
            ->findOrFail($recordId);

        if ($isAuthCollection
            && ! $bypassSuperuser
            && isset($data['password'])
            && ! isset($data['old_password'])
            && ! empty($data['old_password'])) {
            throw ValidationException::withMessages([
                'old_password' => 'The old password is required when changing the password.',
            ]);
        }

        if ($isAuthCollection
            && ! $bypassSuperuser
            && isset($data['password'])
            && isset($data['old_password'])
            && ! Hash::check($data['old_password'], $record->password)) {
            throw ValidationException::withMessages([
                'old_password' => 'The old password is incorrect.',
            ]);
        }

        $record->update($data);

        return $record->fresh()->toArray();
    }
}
