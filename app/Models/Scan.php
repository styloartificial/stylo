<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_id',
        'title',
        'outfit_detail',
        'img_url',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function scanItemCategories()
    {
        return $this->hasMany(ScanItemCategory::class, 'scan_id');
    }

    public function scanSaves()
    {
        return $this->hasMany(ScanSave::class, 'scan_id');
    }

    public function scanResult()
    {
        return $this->hasOne(ScanResult::class, 'scan_id');
    }
}
