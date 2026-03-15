<?php

use App\Domain\Collections\Models\Collection;
use App\Domain\QueryCompiler\Services\QueryFilter;

it('evaluates a boolean expression against flat context values', function () {
    $filter = QueryFilter::for(Collection::query(), []);

    $result = $filter->evaluate(
        'id = @request.auth.id && role = "admin"',
        [
            'id' => 2,
            'role' => 'admin',
            'request' => [
                'auth' => [
                    'id' => 2,
                    'role' => 'admin',
                ],
            ],
        ]
    );

    expect($result)->toBeTrue();
});

it('treats missing context variables as null during evaluation', function () {
    $filter = QueryFilter::for(Collection::query(), []);

    expect($filter->evaluate('record.id is null', []))->toBeTrue()
        ->and($filter->evaluate('id = @request.auth.id', ['id' => 1]))->toBeFalse();
});

it('evaluates field equals system reference on the right-hand side', function () {
    $filter = QueryFilter::for(Collection::query(), []);

    $result = $filter->evaluate(
        'id = @request.auth.id',
        [
            'id' => 7,
            'request' => [
                'auth' => [
                    'id' => 7,
                ],
            ],
        ]
    );

    expect($result)->toBeTrue();
});
