<?php

namespace LucaLongo\LaravelHelpdesk\AI\Contracts;

interface AIServiceContract
{
    public function isAvailable(): bool;

    public function getProvider(): string;

    public function getModel(): string;

    public function getCapabilities(): array;

    public function validateConfiguration(): bool;
}
