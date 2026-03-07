<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $table = 'tblannouncements';

    protected $fillable = [
        'title',
        'content',
        'start_at',
        'end_at',
        'is_active',
        'priority',
        'readby',
        'recipients_json',
        'group_filters_json',
        'created_by',
        'created_by_user_id',
        'type',
        'auto_date',
    ];

    protected $casts = [
        'readby' => 'array',
        'recipients_json' => 'array',
        'group_filters_json' => 'array',
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'auto_date' => 'date',
    ];
}
