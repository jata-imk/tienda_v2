<?php

namespace App\Http\Resources\Usuario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'nombre'          => $this->name,
            'primerApellido'  => $this->first_name,
            'segundoApellido' => $this->last_name,
            'usuario'         => $this->username,
            'email'           => $this->email,
            'tipoUsuario'     => $this->tipoUsuario?->type_user,
            'status'          => $this->status,
            'dateCreation'    => $this->date_creation?->toDateTimeString(),
        ];
    }
}
