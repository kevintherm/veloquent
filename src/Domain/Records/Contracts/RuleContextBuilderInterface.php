<?php

namespace Veloquent\Core\Domain\Records\Contracts;

use Illuminate\Http\Request;
use Veloquent\Core\Domain\Collections\Models\Collection;

interface RuleContextBuilderInterface
{
    /**
     * Build the evaluation context for a rule.
     *
     * @param Collection $collection
     * @param array $payload Data or record details
     * @param mixed $authenticatedUser
     * @param Request|null $request
     * @param string|null $rule
     * @return array
     */
    public function build(
        Collection $collection,
        array $payload,
        mixed $authenticatedUser,
        ?Request $request = null,
        ?string $rule = null
    ): array;
}
