<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    protected $fillable = [
        'user_id',
        'gender',
        'date_of_birth',
        'height',
        'weight',
        'skin_tone_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function skinTone()
    {
        return $this->belongsTo(MSkinTone::class, 'skin_tone_id');
    }
}
