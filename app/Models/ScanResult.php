<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanResult extends Model
{
    protected $fillable = [
        'scan_id',
        'img_urls',
        'summary',
    ];

    public function scan()
    {
        return $this->belongsTo(Scan::class, 'scan_id');
    }
}
