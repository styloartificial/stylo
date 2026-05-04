<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanSave extends Model
{
    protected $fillable = [
        'scan_id',
        'img_url',
        'is_partial',
    ];

    protected $casts = [
        'is_partial' => 'boolean',
    ];

    public function scan()
    {
        return $this->belongsTo(Scan::class, 'scan_id');
    }

    public function saveItems()
    {
        return $this->hasMany(SaveItem::class, 'scan_save_id');
    }
}