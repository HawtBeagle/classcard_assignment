<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormOption extends Model
{
    use HasFactory;
    public function formData()
    {
        return $this->hasMany(FormData::class, 'option_id');
    }
}