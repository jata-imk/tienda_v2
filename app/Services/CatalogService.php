<?php

namespace App\Services;

use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Color\ColorResource;
use App\Http\Resources\Currency\CurrencyResource;
use App\Http\Resources\Size\SizeResource;
use App\Http\Resources\SizeGroup\SizeGroupResource;
use App\Http\Resources\UserType\UserTypeResource;
use App\Models\Category;
use App\Models\Color;
use App\Models\Currency;
use App\Models\Size;
use App\Models\SizeGroup;
use App\Models\UserType;

class CatalogService
{
    /**
     * Static catalogs the frontend can cache locally (currencies, categories,
     * colors, sizes, size groups, user types, IVA types). Excludes products,
     * product variants and inventory movements — those grow unbounded and
     * must be paged/queried normally, never bundled here.
     */
    public function all(): array
    {
        return [
            'currencies' => CurrencyResource::collection(Currency::orderBy('name')->get()),
            'categories' => CategoryResource::collection(Category::orderBy('name')->get()),
            'colors'     => ColorResource::collection(Color::orderBy('name')->get()),
            'sizeGroups' => SizeGroupResource::collection(SizeGroup::orderBy('name')->get()),
            'sizes'      => SizeResource::collection(Size::orderBy('sort_order')->get()),
            'userTypes'  => UserTypeResource::collection(UserType::orderBy('name')->get()),
            'ivaTypes'   => $this->ivaTypes(),
        ];
    }

    /**
     * IVA types are a fixed enum (1-4), validated in CreateProductRequest
     * (`typeIva` => 'in:1,2,3,4') — there is no cat_tax... table backing it.
     * TODO: replace these placeholder names with the real labels once provided.
     */
    private function ivaTypes(): array
    {
        return [
            ['id' => 1, 'name' => 'Tipo IVA 1'],
            ['id' => 2, 'name' => 'Tipo IVA 2'],
            ['id' => 3, 'name' => 'Tipo IVA 3'],
            ['id' => 4, 'name' => 'Tipo IVA 4'],
        ];
    }
}
