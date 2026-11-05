<?php
declare(strict_types=1);

namespace Bsv\Module\Ergebnisse\Site\Helper;

\defined('_JEXEC') or die;

/**
 * Fehler beim Abruf der BSV-API.
 * getHttpStatus() ist 0 bei Verbindungsproblemen (kein HTTP-Status vorhanden).
 */
class BsvApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private int $httpStatus = 0,
        private ?int $retryAfter = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /** Sekunden bis zum nächsten erlaubten Abruf (nur bei HTTP 429). */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
