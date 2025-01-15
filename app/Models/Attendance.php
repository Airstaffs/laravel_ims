<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    // Define the table name if it's not plural
    protected $table = 'tblemployeeclocks';

    // Define the fillable fields
    protected $fillable = [
        'userid',
        'TimeIn',
        'TimeOut',
        // Add other fields if needed
    ];
}
