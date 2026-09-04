<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    use NullsUpdatedAtOnCreate;

    protected $table = 'user_types';

    protected $fillable = ['name', 'code', 'status'];

    protected static function booted(): void
    {
        static::updated(function (UserType $userType) {
            if (! $userType->wasChanged('status') || $userType->status !== 'inactive') {
                return;
            }

            UserSession::whereIn('id_user', $userType->users()->select('users.id'))
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });
    }

    public function users()
    {
        return $this->hasMany(User::class, 'id_user_type');
    }
}
