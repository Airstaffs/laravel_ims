<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $table = 'tblstores'; // Table name

    protected $primaryKey = 'store_id'; // Primary key

    protected $fillable = [
        'storename',
        'ClientID',
        'clientsecret',
        'refreshtoken',
        'MerchantID',
        'MarketplaceID',
    ];
}
