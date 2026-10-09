<?php
declare(strict_types=1);

final class ValidadorDatos
{
    public static function texto($valor, string $campo, bool $nullable = false): ?string
    {
        if ($valor === null && $nullable) {
            return null;
        }
        if (!is_scalar($valor)) {
            throw new InvalidArgumentException($campo . ' debe ser texto.');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            if ($nullable) {
                return null;
            }
            throw new InvalidArgumentException($campo . ' no puede estar vacío.');
        }

        return $texto;
    }

    public static function estado($valor): string
    {
        if (!in_array($valor, ['Activo', 'Inactivo'], true)) {
            throw new InvalidArgumentException('El estado debe ser Activo o Inactivo.');
        }

        return $valor;
    }
}