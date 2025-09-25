<?php

namespace LucaLongo\LaravelHelpdesk\AI\Contracts;

use LucaLongo\LaravelHelpdesk\AI\Results\EmotionalToneResult;

interface ToneAnalyzableContract
{
    public function analyzeTone(string $text): EmotionalToneResult;

    public function analyzeMultiple(array $texts): array;

    public function getMinTextLength(): int;

    public function getMaxTextLength(): int;
}