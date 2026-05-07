<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZerodhaAutoConfig extends Model
{
    use HasFactory;
    
    protected $table = 'zerodha_auto_configs';
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'order_type',
        'product',
        'signal_strategy',
        'disc_ltp',
        'profit_percent', 
        'index_quantity',
        'stock_quantity',
        'pyramid_percent',
        'pyramid_freq',
        'status',
        'enable_quality_filter',
        'option_series',
        'option_filter', // ✅ MISSING – MUST ADD
        'last_checked_at'
    ];

    protected $casts = [
        'disc_ltp' => 'decimal:2',
        'profit_percent' => 'decimal:2', 
        'index_quantity' => 'integer',
        'stock_quantity' => 'integer',
        'pyramid_percent' => 'integer',
        'pyramid_freq' => 'integer',
        'status' => 'boolean',
        'enable_quality_filter' => 'boolean', // ✅ ADDED
        'last_checked_at' => 'datetime',
        'option_filter' => 'string', // ✅ ADD
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
        return $this->hasMany(ZerodhaAutoOrder::class, 'config_id');
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
     * Get appropriate quantity based on future type
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

    public function allowsCE(): bool
    {
        return in_array($this->option_filter, ['CE', 'BOTH']);
    }

    public function allowsPE(): bool
    {
        return in_array($this->option_filter, ['PE', 'BOTH']);
    }

}