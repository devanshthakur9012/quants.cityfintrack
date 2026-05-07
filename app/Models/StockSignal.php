<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * App\Models\StockSignal
 *
 * @property int         $id
 * @property string      $symbol
 * @property \Carbon\Carbon $signal_date
 * @property string      $signal_type   BUY | SELL | HOLD
 * @property int         $confidence    0–100
 * @property string|null $reason
 * @property array|null  $score_json
 */
class StockSignal extends Model
{
    protected $table = 'stock_signals';

    protected $fillable = [
        'symbol',
        'signal_date',
        'signal_type',
        'confidence',
        'reason',
        'score_json',
    ];

    protected $casts = [
        'signal_date' => 'date:Y-m-d',
        'confidence'  => 'integer',
        'score_json'  => 'array',
    ];

    // ── Signal type constants ─────────────────────────────────────────────────

    public const BUY  = 'BUY';
    public const SELL = 'SELL';
    public const HOLD = 'HOLD';

    // ── Query Scopes ──────────────────────────────────────────────────────────

    public function scopeForSymbol(Builder $q, string $symbol): Builder
    {
        return $q->where('symbol', $symbol);
    }

    public function scopeForDate(Builder $q, string $date): Builder
    {
        return $q->where('signal_date', $date);
    }

    public function scopeBuy(Builder $q): Builder
    {
        return $q->where('signal_type', self::BUY);
    }

    public function scopeSell(Builder $q): Builder
    {
        return $q->where('signal_type', self::SELL);
    }

    public function scopeHold(Builder $q): Builder
    {
        return $q->where('signal_type', self::HOLD);
    }

    /** Signals with confidence >= $min */
    public function scopeStrong(Builder $q, int $min = 65): Builder
    {
        return $q->where('confidence', '>=', $min);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getEmoji(): string
    {
        return match ($this->signal_type) {
            self::BUY  => '🟢',
            self::SELL => '🔴',
            default    => '🟡',
        };
    }
}
