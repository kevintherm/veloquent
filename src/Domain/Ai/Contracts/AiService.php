<?php

namespace Veloquent\Core\Domain\Ai\Contracts;

use Veloquent\Core\Domain\Collections\Models\Collection;

interface AiService
{
    /**
     * Orchestrate and execute the chatbot interaction.
     */
    public function chat(Collection $collection, array $payload): mixed;
}
