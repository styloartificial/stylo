<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanSave extends Model
{
    protected $fillable = [
        'scan_id',
        'type',
        'img_urls',
        'product_name',
        'rating',
        'count_purchase',
        'price',
        'product_url',
    ];

    public function scan()
    {
        return $this->belongsTo(Scan::class, 'scan_id');
    }

    public function scanSaveParts()
    {
        return $this->hasMany(ScanSavePart::class, 'scan_save_id');
    }
}
