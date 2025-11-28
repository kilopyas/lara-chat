<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    protected $fillable = [
        'room_id',
        'name',
        'user_id',
        'last_active_at',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = [
        'has_password',
    ];

    public function getHasPasswordAttribute(): bool
    {
        return !empty($this->attributes['password']);
    }
}
