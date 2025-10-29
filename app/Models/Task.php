<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $table = 'tblkanbantasks';

    protected $fillable = [
        'title',
        'description',
        'note',
        'status',
        'priority',
        'mentions',
        'medias',
        'userId',
    ];

    protected $casts = [
        'mentions' => 'array',
        'medias' => 'array',
    ];
}
