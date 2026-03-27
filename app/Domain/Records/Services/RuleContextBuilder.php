<?php

namespace App\Domain\Records\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RuleContextBuilder
{
    public function build(?Request $request = null, mixed $authenticatedUser = null): array
    {
        $request ??= request();
        $authenticatedUser ??= Auth::user();

        $authContext = is_object($authenticatedUser) && method_exists($authenticatedUser, 'toArray')
            ? $authenticatedUser->toArray()
            : null;

        return [
            'request' => [
                'body' => $request->all(),
                'param' => $request->route()?->parameters() ?? [],
                'query' => $request->query(),
                'auth' => $authContext,
            ],
        ];
    }
}
