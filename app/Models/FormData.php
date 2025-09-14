<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormData extends Model 
{
    use HasFactory;
    public function option() 
    {
        return $this->belongsTo(FormOption::class, 'option_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}