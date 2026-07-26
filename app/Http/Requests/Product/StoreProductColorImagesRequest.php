<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductColorImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images'   => 'required|array|min:1|max:20',
            'images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:4096',
        ];
    }
}
