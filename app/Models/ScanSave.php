<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanSave extends Model
{
    protected $fillable = [
        'scan_id',
        'img_url',
        'is_partial',
        'product_name',
        'price',
        'rating',
        'count_purchase',
        'product_url',
    ];

    protected $casts = [
        'is_partial' => 'boolean',
        'price' => 'float',
        'rating' => 'float',
        'count_purchase' => 'integer',
    ];

    public function scan()
    {
        return $this->belongsTo(Scan::class, 'scan_id');
    }
}