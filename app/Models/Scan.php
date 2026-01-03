<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $fillable = [
        'user_id',
        'ticket_id',
        'title',
        'img_url',
        'scan_category_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scanCategory()
    {
        return $this->belongsTo(MScanCategory::class, 'scan_category_id');
    }

    public function scanResult()
    {
        return $this->hasOne(ScanResult::class, 'scan_id');
    }
    public function scanSaves()
    {
        return $this->hasMany(ScanSave::class, 'scan_id');
    }
}
