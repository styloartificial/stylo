<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppHistory extends Model
{
    protected $fillable = [
        'title',
        'description',
        'apk_url',
        'ios_url',
    ];
}
