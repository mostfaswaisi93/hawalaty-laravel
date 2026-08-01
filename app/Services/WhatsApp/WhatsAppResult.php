<?php

namespace App\Services\WhatsApp;

final readonly class WhatsAppResult
{
    private function __construct(
        public bool $sent,
        public bool $skipped = false,
        public ?string $providerMessageId = null,
        public ?int $httpStatus = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public int $durationMs = 0,
    ) {}

    public static function sent(?string $providerMessageId = null, ?int $httpStatus = 200, int $durationMs = 0): self
    {
        return new self(
            sent: true,
            providerMessageId: $providerMessageId,
            httpStatus: $httpStatus,
            durationMs: $durationMs,
        );
    }

    public static function failed(?int $httpStatus, ?string $errorCode, ?string $errorMessage, int $durationMs = 0): self
    {
        return new self(
            sent: false,
            httpStatus: $httpStatus,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            durationMs: $durationMs,
        );
    }

    public static function skipped(string $reason): self
    {
        return new self(sent: false, skipped: true, errorCode: $reason);
    }
}
