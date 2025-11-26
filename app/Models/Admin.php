<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // 👇 OBLIGATOIRE pour que le seeder insère dans la table admins
    protected $table = 'admins';

    protected $fillable = [
        'name', 
        'email', 
        'password',
        'first_name',
        'last_name',
        'phone',
        'avatar',
        'session_id',
        'last_login_at',
        'last_login_ip'
    ];

    protected $hidden = [
        'password', 
        'remember_token',
        'session_id'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function getFullNameAttribute()
    {
        if ($this->first_name && $this->last_name) {
            return $this->first_name . ' ' . $this->last_name;
        }
        return $this->name;
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return null;
    }

    public function generateSessionId()
    {
        $this->session_id = Str::uuid()->toString();
        $this->last_login_at = now();
        $this->last_login_ip = request()->ip();
        $this->save();
        
        return $this->session_id;
    }

    public function isValidSession($sessionId)
    {
        return $this->session_id === $sessionId;
    }

    public function invalidateSession()
    {
        $this->session_id = null;
        $this->save();
    }
}
