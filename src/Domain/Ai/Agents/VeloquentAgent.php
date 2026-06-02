<?php

namespace Veloquent\Core\Domain\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

class VeloquentAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Create a new VeloquentAgent instance.
     */
    public function __construct(
        public string $instructions,
        public iterable $messages = [],
        public ?float $temperature = null,
        public ?array $schema = null
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return $this->instructions;
    }

    /**
     * Get the conversational messages history.
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [];
    }

    /**
     * Get the temperature option for text generation.
     */
    public function temperature(): ?float
    {
        return $this->temperature;
    }
}
