<?php

namespace App\Http\Requests\Dashboard;

use App\DTOs\Dashboard\DashboardFiltersDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

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
            dateTo:            $this->endOfDay($this->input('dateTo')),
            lowStockThreshold: (float) $this->input('lowStockThreshold', 5),
        );
    }

    /**
     * El rango de fechas es inclusivo en ambos extremos. Una fecha sin hora
     * (`2026-08-20`) se compararia contra las 00:00:00 y dejaria fuera las
     * ventas de ese mismo dia, asi que se lleva al final del dia. Si el valor
     * ya trae hora se respeta tal cual.
     */
    private function endOfDay(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return Carbon::parse($date)->endOfDay()->toDateTimeString();
    }
}
