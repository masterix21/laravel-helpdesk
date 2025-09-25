<?php

namespace LucaLongo\LaravelHelpdesk\AI\Contracts;

use LucaLongo\LaravelHelpdesk\AI\Results\TranscriptionResult;

interface TranscribableContract
{
    public function transcribe(string $filePath, ?string $language = null): TranscriptionResult;

    public function supportsFormat(string $mimeType): bool;

    public function getMaxDuration(): int;

    public function getMaxFileSize(): int;
}