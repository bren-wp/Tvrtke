<?php
declare(strict_types=1);

namespace Tvrtke;

/**
 * Error status without response body, credentials or tokens.
 * A short Retry-After delay may be present for upstream throttling.
 */
final class SudregHttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, public readonly ?int $retryAfter = null)
    {
        parent::__construct('Sudreg API HTTP ' . $status);
    }
}
