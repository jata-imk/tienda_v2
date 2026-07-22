<?php

namespace App\Http\Requests\Product\Concerns;

/**
 * `categories` se recibe como arreglo de ids (`[1, 2]`), pero DevExtreme
 * (dxTagBox) puede mandar los objetos completos (`[{id: 1, desc: "..."}]`).
 * Se normaliza a ids antes de validar.
 */
trait NormalizesCategoriesInput
{
    protected function prepareForValidation(): void
    {
        $categories = $this->input('categories');

        if (!is_array($categories)) {
            return;
        }

        $this->merge([
            'categories' => array_map(
                fn($category) => is_array($category) ? ($category['id'] ?? $category) : $category,
                $categories,
            ),
        ]);
    }
}
