<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $valor): string
    {
        return htmlspecialchars(
            (string) $valor,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
