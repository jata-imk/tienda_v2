<?php

namespace App\Services;

use App\Models\TipoIva;
use Illuminate\Database\Eloquent\Collection;

class TipoIvaService
{
    public function index(): Collection
    {
        return TipoIva::all();
    }
}
