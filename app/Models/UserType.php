<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'user_types';

    protected $fillable = ['name', 'status'];

    public function users()
    {
        return $this->hasMany(User::class, 'id_user_type');
    }
}
