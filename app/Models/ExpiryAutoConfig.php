<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpiryAutoConfig extends Model
{
    use HasFactory;
    
    protected $table = 'expiry_auto_configs';
    
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'order_type',
        'product',
        'disc_ltp',
        'nifty_quantity',
        'banknifty_quantity',
        'sensex_quantity',
        'pyramid_percent',
        'pyramid_freq',
        'status',
        'last_checked_at'
    ];

    protected $casts = [
        'disc_ltp' => 'decimal:2',
        'nifty_quantity' => 'integer',
        'banknifty_quantity' => 'integer',
        'sensex_quantity' => 'integer',
        'pyramid_percent' => 'integer',
        'pyramid_freq' => 'integer',
        'status' => 'boolean',
        'last_checked_at' => 'datetime'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function orders()
    {
        return $this->hasMany(ExpiryAutoOrder::class, 'config_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public static function getActiveConfigs()
    {
        return self::with('broker')
            ->where('status', true)
            ->get();
    }

    /**
     * Get appropriate quantity based on symbol
     * ✅ CRITICAL: Maps NIFTY, BANKNIFTY, SENSEX to their respective quantities
     */
    public function getQuantityForSymbol($symbol)
    {
        if (stripos($symbol, 'NIFTY') !== false && stripos($symbol, 'BANK') === false) {
            // NIFTY (but not BANKNIFTY)
            return $this->nifty_quantity;
        } elseif (stripos($symbol, 'BANKNIFTY') !== false) {
            // BANKNIFTY
            return $this->banknifty_quantity;
        } elseif (stripos($symbol, 'SENSEX') !== false) {
            // SENSEX
            return $this->sensex_quantity;
        }
        
        // Default to NIFTY quantity for unknown symbols
        return $this->nifty_quantity;
    }

    /**
     * Calculate pyramid quantities
     * Returns [pyramid_1, pyramid_2, pyramid_3]
     */
    public function calculatePyramids($totalQuantity)
    {
        if ($this->pyramid_percent == 100) {
            // 100% in first order
            return [$totalQuantity, null, null];
        } elseif ($this->pyramid_percent == 50) {
            // 50-50 split
            $qty = ceil($totalQuantity / 2);
            return [$qty, $totalQuantity - $qty, null];
        } else { // 33
            // 33-33-34 split (approximately)
            $qty = ceil($totalQuantity / 3);
            $remaining = $totalQuantity - $qty;
            $qty2 = ceil($remaining / 2);
            return [$qty, $qty2, $remaining - $qty2];
        }
    }
}