<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoUsuario extends Model
{
    protected $table = 'tipos_usuario';

    public $timestamps = false;

    protected $fillable = ['type_user', 'status', 'date_creation'];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'user_type_id');
    }
}
