<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MBodyShape extends Model
{
    protected $fillable = ['title', 'description'];

    public function userDetails()
    {
        return $this->hasMany(UserDetail::class, 'body_shape_id');
    }
}