<?php
// app/Models/CpMultiTimeOrderConfig.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpMultiTimeOrderConfig extends Model
{
    protected $fillable = [
        'user_id', 'broker_type', 'broker_api_id',
        'order_type', 'product', 'disc_ltp', 'signal_mode', 'quantity',
        'max_price_pct_of_underlying', 'reentry_min_drop_pct',
        'snapshot_times', // ← NEW
        'status', 'last_run_at',
    ];

    protected $casts = [
        'status'                      => 'boolean',
        'disc_ltp'                    => 'float',
        'max_price_pct_of_underlying' => 'float',
        'reentry_min_drop_pct'        => 'float',
        'snapshot_times'              => 'array', // ← NEW — stored as JSON, read back as PHP array
        'last_run_at'                 => 'datetime',
    ];

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function orders()
    {
        return $this->hasMany(CpMultiTimeOrder::class);
    }

    /** Same LIMIT-discount rule as CpOrderConfig::applyDiscount() */
    public function applyDiscount(float $ltp): float
    {
        if ($this->order_type !== 'LIMIT' || $ltp <= 0) return 0;
        $discountPct = (float) ($this->disc_ltp ?? 0);
        $price = $ltp * (1 - ($discountPct / 100));
        return round(max($price, 0.05), 2);
    }
}