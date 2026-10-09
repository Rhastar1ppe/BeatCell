<?php

/**
 * Reglas y textos del tiempo de una actividad.
 *
 * tiempo_limite SIEMPRE se guarda en segundos; modo_tiempo indica cómo interpretarlo:
 *  - sin_limite:   no hay reloj (tiempo_limite = NULL).
 *  - total:        segundos para toda la actividad (en gestión se captura en minutos).
 *  - por_pregunta: segundos para cada pregunta.
 *
 * Gestión y estudiante usan esta misma clase para validar y mostrar el tiempo,
 * así ambos ven siempre la misma información.
 */
final class TiempoActividad
{
    public const SIN_LIMITE = 'sin_limite';
    public const TOTAL = 'total';
    public const POR_PREGUNTA = 'por_pregunta';
    public const MODOS = [self::SIN_LIMITE, self::TOTAL, self::POR_PREGUNTA];

    public const MIN_TOTAL_MINUTOS = 1;
    public const MAX_TOTAL_MINUTOS = 600;
    public const MIN_PREGUNTA_SEGUNDOS = 5;
    public const MAX_PREGUNTA_SEGUNDOS = 600;

    /**
     * Devuelve un modo válido. Si la fila no trae modo_tiempo (por ejemplo, datos antiguos)
     * se deduce de tiempo_limite; un modo con reloj pero sin segundos equivale a "sin límite".
     */
    public static function normalizarModo($modo, $segundos): string
    {
        $segundos = (int) $segundos;
        if (is_string($modo) && in_array($modo, self::MODOS, true)) {
            return ($modo !== self::SIN_LIMITE && $segundos <= 0) ? self::SIN_LIMITE : $modo;
        }

        return $segundos > 0 ? self::TOTAL : self::SIN_LIMITE;
    }

    /** Convierte segundos a un texto corto: "20 s", "10 min" o "1 min 30 s". */
    public static function formatearDuracion($segundos): string
    {
        $segundos = max(0, (int) $segundos);
        if ($segundos < 60) {
            return $segundos . ' s';
        }

        $minutos = intdiv($segundos, 60);
        $resto = $segundos % 60;

        return $resto === 0 ? $minutos . ' min' : $minutos . ' min ' . $resto . ' s';
    }

    /** Texto para mostrar en listados: "Sin límite", "10 min en total" o "20 s por pregunta". */
    public static function describir($segundos, $modo = null, ?int $numPreguntas = null): string
    {
        $modo = self::normalizarModo($modo, $segundos);
        if ($modo === self::SIN_LIMITE) {
            return 'Sin límite';
        }
        if ($modo === self::TOTAL) {
            return self::formatearDuracion($segundos) . ' en total';
        }

        $texto = self::formatearDuracion($segundos) . ' por pregunta';
        if ($numPreguntas !== null && $numPreguntas > 0) {
            $texto .= ' (≈ ' . self::formatearDuracion((int) $segundos * $numPreguntas) . ' en total)';
        }

        return $texto;
    }

    /** Segundos máximos para completar toda la actividad (0 = sin límite). */
    public static function segundosTotales($segundos, $modo, int $numPreguntas): int
    {
        $segundos = max(0, (int) $segundos);
        $modo = self::normalizarModo($modo, $segundos);
        if ($modo === self::TOTAL) {
            return $segundos;
        }
        if ($modo === self::POR_PREGUNTA) {
            return $segundos * max(0, $numPreguntas);
        }

        return 0;
    }
}
