<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MSkinTone extends Model
{
    protected $fillable = [
        'title',
        'description',
    ];

    public function userDetails()
    {
        return $this->hasMany(UserDetail::class, 'skin_tone_id');
    }
}
