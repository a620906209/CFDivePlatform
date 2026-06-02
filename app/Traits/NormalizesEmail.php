<?php

namespace App\Traits;

trait NormalizesEmail
{
    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
