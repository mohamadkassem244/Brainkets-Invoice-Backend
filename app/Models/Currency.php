<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $table = 'currency';

    protected $fillable = [
        'name',
        'shortcut',
        'symbol',
        'decimal_numbers',
        'usd_to_currency',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'decimal_numbers' => 'integer',
        'usd_to_currency' => 'double',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'currency_id');
    }
}
