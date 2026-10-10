<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis yang aman ditampilkan ke client (409/422) dengan kode spesifik,
 * mis. VERSION_CONFLICT, CHARGE_ALREADY_PAID, HOUSE_ALREADY_REGISTERED.
 */
class DomainConflict extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }
}
