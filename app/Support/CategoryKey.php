<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clave corta de una categoria (`CAM`, `FIL`), prefijo de las claves de
 * producto. Se deriva del nombre: mayusculas, sin acentos y solo letras.
 */
final class CategoryKey
{
    public const LENGTH = 3;

    /**
     * Primera clave libre para `$name`, en este orden:
     * 1. las primeras letras (`Manga corta` -> `MAN`);
     * 2. la inicial de cada palabra menos la ultima, mas la ultima completa,
     *    cortado a 3 (`Manga larga` -> `MLA`, `Ropa de playa niño` -> `RDP`);
     * 3. la primera opcion con sufijo numerico (`MAN2`, `MAN3`...).
     *
     * Las opciones 1 y 2 siempre miden 3: con cualquier cantidad de palabras la
     * clave cabe en la columna (10) y en el prefijo corto de la clave de producto.
     *
     * @param int|null $ignoreId categoria que se esta editando (su clave no cuenta como ocupada).
     */
    public static function generate(string $name, ?int $ignoreId = null): string
    {
        $words = self::words($name);
        $base  = substr(implode('', $words), 0, self::LENGTH) ?: 'CAT';

        $candidates = [$base];

        if (count($words) > 1) {
            $initials = implode('', array_map(fn(string $word) => $word[0], array_slice($words, 0, -1)));
            $last     = end($words);

            $candidates[] = substr($initials . $last, 0, self::LENGTH);
        }

        foreach ($candidates as $candidate) {
            if (!self::taken($candidate, $ignoreId)) {
                return $candidate;
            }
        }

        for ($n = 2; ; $n++) {
            if (!self::taken($base . $n, $ignoreId)) {
                return $base . $n;
            }
        }
    }

    /**
     * Palabras del nombre ya normalizadas: `100% Lino` -> `['LINO']`.
     *
     * @return string[]
     */
    public static function words(string $name): array
    {
        $ascii = strtoupper(Str::ascii($name));

        return array_values(array_filter(
            preg_split('/[^A-Z]+/', $ascii) ?: [],
            fn(string $word) => $word !== '',
        ));
    }

    private static function taken(string $key, ?int $ignoreId): bool
    {
        return DB::table('categories')
            ->where('key', $key)
            ->when($ignoreId !== null, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
