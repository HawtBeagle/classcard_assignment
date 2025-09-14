<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; 
use Illuminate\Database\Eloquent\Model;
use App\Scopes\TenantScope;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'password', 'tenant_id'
    ];

    protected static function booted()
    {
        // Apply tenant scope globally
        static::addGlobalScope(new TenantScope);

        // Auto-set tenant_id when creating a user
        static::creating(function ($user) {
            if (auth()->check()) {
                $user->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    public function formData() 
    {
        return $this->hasMany(FormData::class);
    }
}
