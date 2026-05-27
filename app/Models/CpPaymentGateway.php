<?php
// FILE: app/Models/CpPaymentGateway.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpPaymentGateway extends Model
{
    protected $table = 'cp_payment_gateways';

    protected $fillable = ['name', 'alias', 'credentials', 'status'];

    protected $casts = [
        'credentials' => 'array',
        'status'      => 'boolean',
    ];

    public function getCredential(string $key): ?string
    {
        return $this->credentials[$key] ?? null;
    }

    public static function active(): ?self
    {
        return static::where('status', true)->first();
    }
}