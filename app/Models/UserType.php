<?php

namespace App\Models;

use App\Models\Concerns\NullsUpdatedAtOnCreate;
use App\Services\AuditTrail;
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

            app(AuditTrail::class)->bulkUpdate(
                UserSession::whereIn('id_user', $userType->users()->select('users.id'))->whereNull('revoked_at'),
                ['revoked_at' => now()],
            );
        });
    }

    public function users()
    {
        return $this->hasMany(User::class, 'id_user_type');
    }
}
