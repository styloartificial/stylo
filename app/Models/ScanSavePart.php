<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanSavePart extends Model
{
    protected $fillable = [
        'scan_save_id',
        'img_urls',
        'product_name',
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
