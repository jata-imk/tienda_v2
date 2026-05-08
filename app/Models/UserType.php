<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    protected $table = 'user_types';

    protected $fillable = ['name', 'status'];

    public function users()
    {
        return $this->hasMany(User::class, 'id_user_type');
    }
}
