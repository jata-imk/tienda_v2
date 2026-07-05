<?php

namespace App\Http\Resources\UserType;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'status' => $this->status,
        ];
    }
}
