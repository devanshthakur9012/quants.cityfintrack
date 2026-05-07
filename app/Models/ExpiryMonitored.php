<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExpiryMonitored extends Model
{
    protected $table = 'expiry_monitored';
    
    protected $fillable = [
        'symbol',
        'exchange',
        'instrument_token',
        'is_active',
        'last_fetched_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_fetched_at' => 'datetime'
    ];

    /**
     * Check if today is expiry day for this symbol
     */
    public function isTodayExpiry()
    {
        $today = Carbon::today()->format('Y-m-d');
        $closestExpiry = $this->getClosestExpiry();
        
        return $closestExpiry && $closestExpiry->format('Y-m-d') === $today;
    }

    /**
     * Get closest expiry date for this symbol
     */
    public function getClosestExpiry()
    {
        $option = DB::table('zerodha_instruments')
            ->where('name', $this->symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', '>=', Carbon::today())
            ->orderBy('expiry', 'ASC')
            ->first();

        return $option ? Carbon::parse($option->expiry) : null;
    }

    /**
     * Get all symbols with expiry today
     */
    public static function getExpiringToday()
    {
        return self::where('is_active', true)
            ->get()
            ->filter(function ($symbol) {
                return $symbol->isTodayExpiry();
            });
    }
}
