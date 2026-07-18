<?php

namespace App\Services\WhatsApp;

/**
 * Outcome of one send attempt. Deliberately a value object rather than a bare
 * bool: Meta's error codes mean completely different operator actions (131047 =
 * outside the 24h window, 132000 = template parameter mismatch, 190 = expired
 * token), so the caller and the logs need to distinguish them.
 *
 * `skipped` is a third state, distinct from `failed`: nothing was wrong, the
 * message simply did not apply (cash order, no number on the bank, feature
 * disabled).
 */
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

    /** @param string $reason machine-readable: disabled|not_bank|no_bank|bank_deleted|no_number|unnormalisable|duplicate */
    public static function skipped(string $reason): self
    {
        return new self(sent: false, skipped: true, errorCode: $reason);
    }
}
