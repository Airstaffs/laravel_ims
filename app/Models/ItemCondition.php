<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemCondition extends Model
{
    use HasFactory;

    protected $table = 'tblitem_conditions';

    protected $fillable = [
        'item_number',      // itemnumber from your products table
        'product_id',       // ProductID (RT number)
        'condition_type',   // 'receive' or 'release'
        'physical_damage',
        'scratches',
        'dents',
        'cracks',
        'original_packaging',
        'packaging_damaged',
        'missing_accessories',
        'powers_on',
        'all_functions_work',
        'connectivity_tested',
        'display_condition',
        'manual_included',
        'cables_included',
        'warranty_card',
        'overall_condition',
        'notes',
        'inspected_by',
        'inspected_at',
    ];

    protected $casts = [
        'physical_damage' => 'boolean',
        'scratches' => 'boolean',
        'dents' => 'boolean',
        'cracks' => 'boolean',
        'original_packaging' => 'boolean',
        'packaging_damaged' => 'boolean',
        'missing_accessories' => 'boolean',
        'powers_on' => 'boolean',
        'all_functions_work' => 'boolean',
        'connectivity_tested' => 'boolean',
        'display_condition' => 'boolean',
        'manual_included' => 'boolean',
        'cables_included' => 'boolean',
        'warranty_card' => 'boolean',
        'inspected_at' => 'datetime',
    ];

    /**
     * Get the product associated with this condition report
     * Links to your main products table
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'item_number', 'itemnumber');
    }

    /**
     * Get only RECEIVE condition checks
     */
    public function scopeReceiveConditions($query)
    {
        return $query->where('condition_type', 'receive');
    }

    /**
     * Get only RELEASE condition checks
     */
    public function scopeReleaseConditions($query)
    {
        return $query->where('condition_type', 'release');
    }

    /**
     * Get the latest receive condition for an item
     */
    public function scopeLatestReceive($query, $itemNumber)
    {
        return $query->where('item_number', $itemNumber)
                    ->where('condition_type', 'receive')
                    ->latest('created_at');
    }

    /**
     * Get the latest release condition for an item
     */
    public function scopeLatestRelease($query, $itemNumber)
    {
        return $query->where('item_number', $itemNumber)
                    ->where('condition_type', 'release')
                    ->latest('created_at');
    }

    /**
     * Get all condition history for an item (receive + release)
     * Shows the full lifecycle of an item
     */
    public function scopeItemHistory($query, $itemNumber)
    {
        return $query->where('item_number', $itemNumber)
                    ->orderBy('created_at', 'desc');
    }

    /**
     * Check if item has a receive condition logged
     */
    public static function hasReceiveCondition($itemNumber)
    {
        return self::where('item_number', $itemNumber)
                  ->where('condition_type', 'receive')
                  ->exists();
    }

    /**
     * Check if item has a release condition logged
     */
    public static function hasReleaseCondition($itemNumber)
    {
        return self::where('item_number', $itemNumber)
                  ->where('condition_type', 'release')
                  ->exists();
    }

    /**
     * Get condition score (percentage of positive checks)
     * Higher score = better condition
     */
    public function getConditionScoreAttribute()
    {
        $checks = [
            'physical_damage' => false,      // NO damage is good
            'scratches' => false,            // NO scratches is good
            'dents' => false,                // NO dents is good
            'cracks' => false,               // NO cracks is good
            'original_packaging' => true,    // HAS original packaging is good
            'packaging_damaged' => false,    // NO packaging damage is good
            'missing_accessories' => false,  // NO missing accessories is good
            'powers_on' => true,             // DOES power on is good
            'all_functions_work' => true,    // ALL functions work is good
            'connectivity_tested' => true,   // TESTED connectivity is good
            'display_condition' => true,     // GOOD display is good
            'manual_included' => true,       // HAS manual is good
            'cables_included' => true,       // HAS cables is good
            'warranty_card' => true,         // HAS warranty card is good
        ];

        $total = count($checks);
        $positive = 0;

        foreach ($checks as $field => $shouldBeTrue) {
            $value = $this->$field;
            if ($shouldBeTrue && $value) {
                $positive++;
            } elseif (!$shouldBeTrue && !$value) {
                $positive++;
            }
        }

        return round(($positive / $total) * 100, 2);
    }

    /**
     * Get human-readable condition type
     */
    public function getConditionTypeNameAttribute()
    {
        return $this->condition_type === 'receive' ? 'Receive Condition' : 'Release Condition';
    }

    /**
     * Compare receive vs release conditions
     * Returns array showing what changed
     */
    public static function compareConditions($itemNumber)
    {
        $receive = self::latestReceive($itemNumber)->first();
        $release = self::latestRelease($itemNumber)->first();

        if (!$receive || !$release) {
            return null;
        }

        $changes = [];
        $fields = [
            'physical_damage', 'scratches', 'dents', 'cracks',
            'original_packaging', 'packaging_damaged', 'missing_accessories',
            'powers_on', 'all_functions_work', 'connectivity_tested', 'display_condition',
            'manual_included', 'cables_included', 'warranty_card'
        ];

        foreach ($fields as $field) {
            if ($receive->$field !== $release->$field) {
                $changes[$field] = [
                    'receive' => $receive->$field,
                    'release' => $release->$field,
                    'improved' => $release->$field > $receive->$field
                ];
            }
        }

        return [
            'receive_score' => $receive->condition_score,
            'release_score' => $release->condition_score,
            'changes' => $changes,
            'overall_improvement' => $release->condition_score > $receive->condition_score
        ];
    }
}