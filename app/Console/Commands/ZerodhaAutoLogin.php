<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Helpers\KiteConnectCls;
use App\Models\KiteToken;

class ZerodhaAutoLogin extends Command
{
    protected $signature = 'zerodha:auto-login';
    protected $description = 'Automated Zerodha login and token generation';

    private $zerodha_api_key = '99oiazl0azvr3y6g';
    private $zerodha_api_secret = 'cjv7jjg1o2zws3zrhuk5ubfq11mie7y0';
    private $zerodha_login_id = 'XXQ759';
    private $zerodha_password = 'city@123';
    private $zerodha_totp_secret = '356FIW7RUHLNHDOEXUMJOFRRYWDOSZZP';

    public function handle()
    {
        $this->info("🚀 Starting Zerodha Auto-Login...\n");

        try {
            // Initialize KiteConnect Helper
            $kiteHelper = new KiteConnectCls([
                'accountUserName' => $this->zerodha_login_id,
                'accountPassword' => $this->zerodha_password,
                'totpSecret' => $this->zerodha_totp_secret,
                'apiKey' => $this->zerodha_api_key,
                'apiSecret' => $this->zerodha_api_secret,
            ]);

            $this->line("⏳ Testing connection...");
            if (!$kiteHelper->testConnection()) {
                throw new \Exception("Cannot connect to Zerodha servers");
            }
            $this->info("✅ Connection successful\n");

            // Check if valid token exists
            $this->line("🔍 Checking existing token...");
            $tokenInfo = $kiteHelper->getStoredTokenInfo();
            
            if ($tokenInfo['valid_token_available']) {
                $this->info("✅ Valid token already exists!");
                $this->info("   Expires at: {$tokenInfo['db_expires_at']}");
                $this->newLine();
                $this->info("═══════════════════════════════════════");
                $this->info("✅ LOGIN SUCCESSFUL (Using cached token)");
                $this->info("═══════════════════════════════════════\n");
                return 0;
            }

            $this->warn("⚠️  No valid token found. Generating new token...\n");

            // Generate new session
            $this->line("🔐 Performing login sequence...");
            $kite = $kiteHelper->generateSession();

            // Verify session
            $this->line("✅ Verifying session...");
            $profile = $kite->getProfile();

            $this->newLine();
            $this->info("═══════════════════════════════════════");
            $this->info("✅ LOGIN SUCCESSFUL!");
            $this->info("═══════════════════════════════════════");
            $this->info("User ID: {$profile->user_id}");
            $this->info("Name: {$profile->user_name}");
            $this->info("Email: {$profile->email}");
            $this->info("Token valid for: 24 hours");
            $this->info("═══════════════════════════════════════\n");

            Log::info('Zerodha auto-login successful', [
                'user_id' => $profile->user_id,
                'timestamp' => now()
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("\n❌ Login Failed: " . $e->getMessage());
            
            Log::error('Zerodha auto-login failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
    }
}