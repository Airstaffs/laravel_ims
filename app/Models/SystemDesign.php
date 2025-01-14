<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemDesign extends Model
{
    use HasFactory;

    // Make sure the table name is correct if it's different from the default
    protected $table = 'system_design';

    // Ensure you have the fillable or guarded properties set correctly
    protected $fillable = ['site_title', 'theme_color', 'logo'];
}