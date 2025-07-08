<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class tblproduct extends Model
{
    use HasFactory;
    protected $table = 'tblproduct';

    protected $primaryKey = 'itemnumber';

    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'itemnumber',
        'ProductID',
        'ProductTitle',
        'rtid',
        'orderdate',
        'paymentdate',
        'shipdate',
        'datedelivered',
        'seller',
        'materialtype',
        'carrier',
        'listedcondition',
        'paymentmethod',
        'quantity',
        'Discount',
        'tax',
        'priceshipping',
        'refund',
        'description',
        'serialnumber',
        'serialnumberb',
        'serialnumberc',
        'serialnumberd',
        'trackingnumber',
        'trackingnumber2',
        'trackingnumber3',
        'trackingnumber4',
        'trackingnumber5',
        'validation'
    ];


    /**
     * Create a new instance of the model.
     *
     * @param array $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Get the logged in user
        $user = Auth::user();

        // Get company data - assuming user has a company relation or attribute
        $companyColumn = $user ? $user->company : '';

        // Set the table name dynamically
        $this->setTable('tblproduct' . $companyColumn);
    }

    /**
     * Get the current logged-in username
     *
     * @return string|null
     */
    public function getLoggedInUsername()
    {
        return Auth::user() ? Auth::user()->username : null;
    }

    /**
     * Get the user that is associated with this product
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
