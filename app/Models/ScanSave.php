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
        'source',
        'group_label',
    ];

    protected $casts = [
        'price' => 'float',
        'rating' => 'float',
        'count_purchase' => 'integer',
        'is_partial' => 'boolean',
    ];

    public function scan()
    {
        return $this->belongsTo(Scan::class, 'scan_id');
    }
    
}