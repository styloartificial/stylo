<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanItemCategory extends Model
{
    protected $fillable = [
        'scan_id',
        'item_category_id',
        'type',
    ];

    public function scan()
    {
        return $this->belongsTo(Scan::class, 'scan_id');
    }

    public function itemCategory()
    {
        return $this->belongsTo(MScanCategory::class, 'item_category_id');
    }
}