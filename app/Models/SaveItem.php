<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaveItem extends Model
{
    protected $fillable = [
        'scan_save_id',
        'product_name',
        'img_url',
        'rating',
        'count_purchase',
        'price',
        'product_url',
    ];

    public function scanSave()
    {
        return $this->belongsTo(ScanSave::class, 'scan_save_id');
    }
}