<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OiivBtstConfig extends Model
{
    protected $table = 'oiiv_btst_configs';

    protected $fillable = [
        'user_id', 'broker_api_id',
        'sl_percent', 'profit_percent',
        'min_profit_percent', 'enable_10am_sweep', 'sweep_time',
        'old_position_sl_percent', 'old_position_action',
        'symbol_type', 'is_active',
    ];

    protected $casts = [
        'sl_percent'              => 'decimal:2',
        'profit_percent'          => 'decimal:2',
        'min_profit_percent'      => 'decimal:2',
        'old_position_sl_percent' => 'decimal:2',
        'enable_10am_sweep'       => 'boolean',
        'is_active'               => 'boolean',
    ];

    public function user()   { return $this->belongsTo(User::class); }
    public function broker() { return $this->belongsTo(BrokerApi::class, 'broker_api_id'); }

    // ── Price helpers ─────────────────────────────────────────────────────

    public function slTrigger(float $avg): float
    {
        return round($avg * (1 - $this->sl_percent / 100), 2);
    }

    public function profitTarget(float $avg): float
    {
        return round($avg * (1 + $this->profit_percent / 100), 2);
    }

    public function oldSlTrigger(float $avg): float
    {
        return round($avg * (1 - $this->old_position_sl_percent / 100), 2);
    }

    // ── Position age ──────────────────────────────────────────────────────

    /**
     * Fresh = signal_date is today or yesterday.
     * Old   = signal_date ≤ T-2.
     */
    public static function isFresh(string $signalDate): bool
    {
        return $signalDate >= Carbon::yesterday()->toDateString();
    }
}