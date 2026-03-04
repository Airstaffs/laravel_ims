<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EwhRecord extends Model
{
    protected $table = 'tblewhrecords';

    protected $fillable = [
        'employee_id',
        'employee_name',
        'payout_date',
        'cutoff_from',
        'cutoff_to',
        'total_days',
        'total_hours',
        'regular_hours',
        'ot_hours',
        'regular_holiday_days',
        'special_holiday_days',
        'attendance_records',
        'status',
        'employee_status',
    ];

    protected $casts = [
        'attendance_records' => 'array',
        'payout_date'        => 'date',
        'cutoff_from'        => 'date',
        'cutoff_to'          => 'date',
        'total_hours'        => 'float',
        'regular_hours'      => 'float',
        'ot_hours'           => 'float',
    ];
}