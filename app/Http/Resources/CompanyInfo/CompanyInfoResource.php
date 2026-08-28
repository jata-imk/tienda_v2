<?php

namespace App\Http\Resources\CompanyInfo;

use App\Http\Resources\Currency\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyInfoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'rfc'              => $this->rfc,
            'legalName'        => $this->legal_name,
            'taxRegime'        => $this->tax_regime,
            'idCurrency'       => $this->id_currency,
            'currency'         => $this->currency ? new CurrencyResource($this->currency) : null,
            'logo'             => $this->logo,
            'street'           => $this->street,
            'externalNumber'   => $this->external_number,
            'crossStreetOne'   => $this->cross_street_one,
            'crossStreetTwo'   => $this->cross_street_two,
            'postalCode'       => $this->postal_code,
            'neighborhood'     => $this->neighborhood,
            'city'             => $this->city,
            'stockControl'     => $this->stock_control,
            'quantityIntegers' => $this->quantity_integers,
            'quantityDecimals' => $this->quantity_decimals,
            'gridSettings'     => $this->grid_settings,
            'status'           => $this->status,
            'createdAt'        => $this->created_at?->toDateTimeString(),
            'updatedAt'        => $this->updated_at?->toDateTimeString(),
        ];
    }
}
