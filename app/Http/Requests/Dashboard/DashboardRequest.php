<?php

namespace App\Http\Requests\Dashboard;

use App\DTOs\Dashboard\DashboardFiltersDTO;
use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit'             => 'sometimes|integer|min:1|max:50',
            'dateFrom'          => 'sometimes|date',
            'dateTo'            => 'sometimes|date|after_or_equal:dateFrom',
            'lowStockThreshold' => 'sometimes|numeric|min:0',
        ];
    }

    public function toDTO(): DashboardFiltersDTO
    {
        return new DashboardFiltersDTO(
            limit:             (int) $this->input('limit', 5),
            dateFrom:          $this->input('dateFrom'),
            dateTo:            $this->input('dateTo'),
            lowStockThreshold: (float) $this->input('lowStockThreshold', 5),
        );
    }
}
