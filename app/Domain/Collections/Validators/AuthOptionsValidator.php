<?php

namespace App\Domain\Collections\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthOptionsValidator
{
    public function validate(array $options, array $fields, bool $isAuthCollection): array
    {
        if (! $isAuthCollection) {
            return $options;
        }

        $options['auth_methods'] ??= [];
        $options['auth_methods']['standard'] ??= [];
        $options['auth_methods']['standard']['enabled'] ??= true;
        $options['auth_methods']['standard']['identity_fields'] ??= ['email'];

        $options['auth_methods']['oauth'] ??= [];
        $options['auth_methods']['oauth']['enabled'] ??= false;

        $validator = Validator::make($options, [
            'auth_methods' => 'required|array',

            'auth_methods.standard' => 'required|array',
            'auth_methods.standard.enabled' => 'required|boolean',
            'auth_methods.standard.identity_fields' => 'required|array|min:1',
            'auth_methods.standard.identity_fields.*' => ['string', Rule::in($fields)],

            'auth_methods.oauth' => 'required|array',
            'auth_methods.oauth.enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
