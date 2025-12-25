<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MScanCategory extends Model
{
    protected $fillable = [
        'title',
        'icon',
    ];

    public function scans()
    {
        return $this->hasMany(Scan::class, 'scan_category_id');
    }
}
