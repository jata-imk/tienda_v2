<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idUserType' => $this->id_user_type,
            'userType' => $this->userType?->name,
            'roleCode' => $this->userType?->code,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'userName' => $this->user_name,
            'email' => $this->email,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toDateTimeString(),
            'updatedAt' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
