<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsinMapping extends Model
{
    protected $table = 'asin_mappings';
    protected $fillable = ['class_name', 'asin_code'];
    public $timestamps = true;
}
