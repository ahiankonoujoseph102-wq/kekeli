<?php

namespace App\Knowledge;

/**
 * The vectors of the passages, kept as text: base 64 of 4-byte floats (768 numbers make about 4 kilobytes). The
 * Runner gives vectors of length 1, so their similarity is a simple dot product (between -1 and 1).
 */
class Vectors
{
    /** @param  list<float|int>  $vector */
    public static function pack(array $vector): string
    {
        return base64_encode(pack('g*', ...array_map('floatval', $vector)));
    }

    /** @return list<float> */
    public static function unpack(?string $packed): array
    {
        if ($packed === null || $packed === '') {
            return [];
        }
        $bytes = base64_decode($packed, true);

        return $bytes === false || $bytes === '' ? [] : array_values(unpack('g*', $bytes));
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function dot(array $a, array $b): float
    {
        $count = min(count($a), count($b));
        $sum = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $sum += $a[$i] * $b[$i];
        }

        return $sum;
    }
}
