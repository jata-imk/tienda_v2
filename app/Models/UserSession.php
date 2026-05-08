<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    protected $table = 'user_sessions';

    protected $fillable = ['id_user', 'token_hash', 'expires_at', 'revoked_at'];

    protected $casts = [
        'expires_at'  => 'datetime',
        'revoked_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function isActive(): bool
    {
        return is_null($this->revoked_at) && Carbon::now()->lt($this->expires_at);
    }

    public function getStatusAttribute(): string
    {
        if (!is_null($this->revoked_at)) {
            return 'revoked';
        }

        if (Carbon::now()->gte($this->expires_at)) {
            return 'expired';
        }

        return 'active';
    }
}
