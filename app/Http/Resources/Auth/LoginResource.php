<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token'       => $this->resource['token'],
            'companyInfo' => $this->resource['companyInfo'],
            'user'        => $this->resource['user'],
        ];
    }
}
