<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnePercentAutoConfig extends Model
{
    use HasFactory;
    
    protected $table = 'one_percent_auto_configs';
    
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'move_threshold',
        'option_series',
        'order_type',
        'product',
        'disc_ltp',
        'index_quantity',
        'stock_quantity',
        'pyramid_percent',
        'pyramid_freq',
        'profit_percent',
        'status',
        'last_checked_at'
    ];

    protected $casts = [
        'move_threshold' => 'decimal:2',
        'disc_ltp' => 'decimal:2',
        'profit_percent' => 'decimal:2',
        'index_quantity' => 'integer',
        'stock_quantity' => 'integer',
        'pyramid_percent' => 'integer',
        'pyramid_freq' => 'integer',
        'status' => 'boolean',
        'last_checked_at' => 'datetime',
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
        return $this->hasMany(OnePercentAutoOrder::class, 'config_id');
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
     * Get appropriate quantity based on symbol type
     */
    public function getQuantityForSymbol($tradingSymbol)
    {
        $indexes = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY'];
        
        foreach ($indexes as $index) {
            if (stripos($tradingSymbol, $index) !== false) {
                return $this->index_quantity;
            }
        }
        
        return $this->stock_quantity;
    }

    /**
     * Calculate pyramid quantities
     */
    public function calculatePyramids($totalQuantity)
    {
        if ($this->pyramid_percent == 100) {
            return [$totalQuantity, null, null];
        } elseif ($this->pyramid_percent == 50) {
            $qty = ceil($totalQuantity / 2);
            return [$qty, $totalQuantity - $qty, null];
        } else { // 33
            $qty = ceil($totalQuantity / 3);
            $remaining = $totalQuantity - $qty;
            $qty2 = ceil($remaining / 2);
            return [$qty, $qty2, $remaining - $qty2];
        }
    }

    /**
     * Calculate target sell price based on profit %
     */
    public function calculateTargetSellPrice($buyPrice)
    {
        return round($buyPrice * (1 + ($this->profit_percent / 100)), 2);
    }
}