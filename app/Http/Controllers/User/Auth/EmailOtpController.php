<?php

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLogin;
use App\Constants\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class EmailOtpController extends Controller
{
    /**
     * OTP length & expiry (seconds)
     */
    const OTP_LENGTH  = 6;
    const OTP_EXPIRY  = 300; // 5 minutes
    const RESEND_WAIT = 30;  // seconds between resends

    // ─────────────────────────────────────────
    // POST /user/send-email-otp
    // ─────────────────────────────────────────
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:180',
        ]);

        $email = strtolower(trim($request->email));

        // ── Rate limit: 1 send per 30 s per email ──
        $rlKey = 'email_otp_send:' . $email;
        if (RateLimiter::tooManyAttempts($rlKey, 1)) {
            $seconds = RateLimiter::availableIn($rlKey);
            return response()->json([
                'status'  => 'error',
                'message' => "Please wait {$seconds} seconds before requesting a new OTP.",
            ], 429);
        }
        RateLimiter::hit($rlKey, self::RESEND_WAIT);

        // ── Check the user exists ──
        $user = User::where('email', $email)->first();
        if (!$user) {
            // Return generic message to avoid user-enumeration
            return response()->json([
                'status'  => 'success',
                'message' => 'If that email is registered, an OTP has been sent.',
            ]);
        }

        // ── Check account is not banned / locked ──
        if ($user->status == Status::BANNED) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Your account has been suspended. Please contact support.',
            ], 403);
        }

        // ── Generate OTP ──
        $otp = str_pad(random_int(0, (int) str_repeat('9', self::OTP_LENGTH)), self::OTP_LENGTH, '0', STR_PAD_LEFT);

        // ── Store in cache (keyed by email) ──
        $cacheKey = 'email_otp:' . $email;
        Cache::put($cacheKey, [
            'otp'        => $otp,
            'attempts'   => 0,
            'created_at' => now()->timestamp,
        ], self::OTP_EXPIRY);

        // ── Send mail ──
        $this->dispatchOtpMail($user, $otp);

        return response()->json([
            'status'  => 'success',
            'message' => 'OTP sent successfully.',
        ]);
    }

    // ─────────────────────────────────────────
    // POST /user/verify-email-otp
    // ─────────────────────────────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:180',
            'otp'   => 'required|digits:' . self::OTP_LENGTH,
        ]);

        $email    = strtolower(trim($request->email));
        $inputOtp = $request->otp;

        // ── Rate limit: max 5 verify attempts per 10 min per email ──
        $rlKey = 'email_otp_verify:' . $email;
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Too many failed attempts. Please request a new OTP.',
            ], 429);
        }

        $cacheKey = 'email_otp:' . $email;
        $stored   = Cache::get($cacheKey);

        if (!$stored) {
            return response()->json([
                'status'  => 'error',
                'message' => 'OTP has expired or was never issued. Please request a new one.',
            ], 422);
        }

        // ── Check OTP match ──
        if ($stored['otp'] !== $inputOtp) {
            RateLimiter::hit($rlKey, 600);
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid OTP. Please check and try again.',
            ], 422);
        }

        // ── OTP valid — clear cache & rate limiter ──
        Cache::forget($cacheKey);
        RateLimiter::clear($rlKey);

        // ── Retrieve user & log them in ──
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Account not found.',
            ], 404);
        }

        if ($user->status == Status::BANNED) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Your account has been suspended.',
            ], 403);
        }

        // Mark two-step verified (same pattern as LoginController)
        $user->tv = $user->ts == Status::VERIFIED ? Status::UNVERIFIED : Status::VERIFIED;
        $user->save();

        Auth::guard('web')->login($user, true /* remember */);

        // ── Log the login ──
        $this->logUserLogin($user);

        return response()->json([
            'status'   => 'success',
            'message'  => 'Login successful.',
            'redirect' => route('user.home'),
        ]);
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    /**
     * Send the OTP email.
     * Uses Laravel's built-in Mail facade with a simple inline Markdown.
     * Swap this for a Mailable class if you prefer.
     */
    protected function dispatchOtpMail(User $user, string $otp): void
    {
        $siteName = gs('site_name') ?? config('app.name');
        $expMins  = self::OTP_EXPIRY / 60;

        Mail::send([], [], function ($message) use ($user, $otp, $siteName, $expMins) {
            $message
                ->to($user->email, $user->fullname)
                ->subject("{$otp} is your {$siteName} login OTP")
                ->html(
                    view($this->activeTemplate . 'emails.otp', [
                        'user'     => $user,
                        'otp'      => $otp,
                        'siteName' => $siteName,
                        'expMins'  => $expMins,
                    ])->render()
                );
        });

        // If you have a queued Mailable (recommended for production):
        // Mail::to($user)->queue(new \App\Mail\EmailOtpMail($otp));
    }

    /**
     * Record the login (mirrors LoginController::authenticated)
     */
    protected function logUserLogin(User $user): void
    {
        $ip        = getRealIP();
        $exist     = UserLogin::where('user_ip', $ip)->first();
        $userLogin = new UserLogin();

        if ($exist) {
            $userLogin->longitude    = $exist->longitude;
            $userLogin->latitude     = $exist->latitude;
            $userLogin->city         = $exist->city;
            $userLogin->country_code = $exist->country_code;
            $userLogin->country      = $exist->country;
        } else {
            $info = json_decode(json_encode(getIpInfo()), true);
            $userLogin->longitude    = @implode(',', $info['long']);
            $userLogin->latitude     = @implode(',', $info['lat']);
            $userLogin->city         = @implode(',', $info['city']);
            $userLogin->country_code = @implode(',', $info['code']);
            $userLogin->country      = @implode(',', $info['country']);
        }

        $userAgent           = osBrowser();
        $userLogin->user_id  = $user->id;
        $userLogin->user_ip  = $ip;
        $userLogin->browser  = @$userAgent['browser'];
        $userLogin->os       = @$userAgent['os_platform'];
        $userLogin->save();
    }
}