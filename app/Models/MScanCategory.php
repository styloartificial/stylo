<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MScanCategory extends Model
{
    protected $fillable = [
        'title',
        'icon',
        'type', // ini yang belum ada
    ];
}