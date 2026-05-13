<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Constants\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\OtpMail;
use App\Mail\EmailVerificationMail;
use App\Mail\ResetPasswordMail;

class LoginController extends Controller
{
    // ─────────────────────────────────────────────
    //  LOGIN — Show page
    // ─────────────────────────────────────────────

    public function showLogin()
    {
        $pageTitle  = 'Login';
        $features   = $this->features();
        $brokers    = $this->brokers();
        $promoVideo = 'https://www.youtube.com/embed/MxpeY6j-_XE?si=7BILhTJxdUhdBP5O&autoplay=1&mute=1&rel=0&modestbranding=1&controls=1';

        return view($this->activeTemplate . 'login', compact('pageTitle', 'features', 'brokers', 'promoVideo'));
    }

    // ─────────────────────────────────────────────
    //  LOGIN — Send OTP to email
    // ─────────────────────────────────────────────

    public function sendLoginOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No account found with this email.']);
        }

        if ($user->status == Status::USER_BAN) {
            return response()->json(['success' => false, 'message' => 'Your account has been suspended.']);
        }

        if (!$user->ev) {
            return response()->json(['success' => false, 'message' => 'Please verify your email first.']);
        }

        $otp = $this->generateOtp($user);
        Mail::to($user->email)->send(new OtpMail($user, $otp, 'Login OTP'));

        return response()->json(['success' => true, 'message' => 'OTP sent to your email.']);
    }

    // ─────────────────────────────────────────────
    //  LOGIN — Verify OTP
    // ─────────────────────────────────────────────

    public function verifyLoginOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:4',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$this->isOtpValid($user, $request->otp)) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP.']);
        }

        $this->clearOtp($user);
        Auth::login($user);

        return response()->json(['success' => true, 'redirect' => route('user.home')]);
    }

    // ─────────────────────────────────────────────
    //  LOGIN — Password login
    // ─────────────────────────────────────────────

    public function loginWithPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }

        if ($user->status == Status::USER_BAN) {
            return back()->withErrors(['email' => 'Your account has been suspended.']);
        }

        if (!$user->ev) {
            return back()->withErrors(['email' => 'Please verify your email address first.']);
        }

        Auth::login($user, $request->remember);
        return redirect()->route('user.home');
    }

    // ─────────────────────────────────────────────
    //  REGISTER — Show page
    // ─────────────────────────────────────────────

    public function showRegister()
    {
        $pageTitle  = 'Create Account';
        $features   = $this->features();
        $brokers    = $this->brokers();
        $promoVideo = 'https://www.youtube.com/embed/MxpeY6j-_XE?si=7BILhTJxdUhdBP5O&autoplay=1&mute=1&rel=0&modestbranding=1&controls=1';

        return view($this->activeTemplate . 'register', compact('pageTitle', 'features', 'brokers', 'promoVideo'));
    }

    // ─────────────────────────────────────────────
    //  REGISTER — Store + send verification email
    // ─────────────────────────────────────────────

    public function register(Request $request)
    {
        $request->validate([
            'firstname' => 'required|string|max:100',
            'lastname'  => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email',
            'mobile'    => 'nullable|string|max:20|unique:users,mobile',
        ]);

        $userCode = 'CQ' . mt_rand(10000, 99999);
        $token    = Str::random(64);

        $user = User::create([
            'firstname'    => $request->firstname,
            'lastname'     => $request->lastname,
            'email'        => $request->email,
            'mobile'       => $request->mobile ?? null,
            'country_code' => 'IN',
            'user_code'    => $userCode,
            'username'     => $userCode,
            'password'     => Hash::make(Str::random(16)), // temp password; user sets via email link
            'status'       => Status::USER_ACTIVE,
            'ev'           => Status::UNVERIFIED,
            'sv'           => Status::VERIFIED,
            'ver_code'     => $token,
            'ver_code_send_at' => now(),
        ]);

        Mail::to($user->email)->send(new EmailVerificationMail($user, $token));

        return response()->json([
            'success' => true,
            'message' => 'Account created! Please check your email to verify and set your password.',
        ]);
    }

    // ─────────────────────────────────────────────
    //  REGISTER — Verify email + set password
    // ─────────────────────────────────────────────

    public function verifyEmail(Request $request, $token)
    {
        $user = User::where('ver_code', $token)->first();

        if (!$user) {
            abort(404, 'Invalid or expired verification link.');
        }

        // Token expires in 24 hours
        if (Carbon::parse($user->ver_code_send_at)->addHours(24)->isPast()) {
            return redirect()->route('user.login')->with('error', 'Verification link has expired. Please register again.');
        }

        $pageTitle = 'Set Your Password';
        return view($this->activeTemplate . 'set-password', compact('pageTitle', 'token', 'user'));
    }

    public function setPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::where('ver_code', $request->token)->first();

        if (!$user) {
            return back()->withErrors(['token' => 'Invalid or expired link.']);
        }

        $user->password       = Hash::make($request->password);
        $user->ev             = Status::VERIFIED;
        $user->ver_code       = null;
        $user->ver_code_send_at = null;
        $user->save();

        Auth::login($user);
        return redirect()->route('user.home')->with('success', 'Welcome to CityQuants!');
    }

    // ─────────────────────────────────────────────
    //  FORGOT PASSWORD — Show page
    // ─────────────────────────────────────────────

    public function showForgotPassword()
    {
        $pageTitle  = 'Forgot Password';
        $features   = $this->features();
        $brokers    = $this->brokers();
        $promoVideo = 'https://www.youtube.com/embed/MxpeY6j-_XE?si=7BILhTJxdUhdBP5O&autoplay=1&mute=1&rel=0&modestbranding=1&controls=1';

        return view($this->activeTemplate . 'forgot-password', compact('pageTitle', 'features', 'brokers', 'promoVideo'));
    }

    // ─────────────────────────────────────────────
    //  FORGOT PASSWORD — Send reset link
    // ─────────────────────────────────────────────

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Always respond success to avoid email enumeration
        if ($user && $user->ev) {
            $token = Str::random(64);
            $user->ver_code         = $token;
            $user->ver_code_send_at = now();
            $user->save();

            Mail::to($user->email)->send(new ResetPasswordMail($user, $token));
        }

        return response()->json([
            'success' => true,
            'message' => 'If that email exists, a reset link has been sent.',
        ]);
    }

    // ─────────────────────────────────────────────
    //  FORGOT PASSWORD — Show reset form
    // ─────────────────────────────────────────────

    public function showResetPassword($token)
    {
        $user = User::where('ver_code', $token)->first();

        if (!$user || Carbon::parse($user->ver_code_send_at)->addHours(1)->isPast()) {
            return redirect()->route('user.login')->with('error', 'Reset link has expired or is invalid.');
        }

        $pageTitle = 'Reset Password';
        return view($this->activeTemplate . 'reset-password', compact('pageTitle', 'token'));
    }

    // ─────────────────────────────────────────────
    //  FORGOT PASSWORD — Save new password
    // ─────────────────────────────────────────────

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::where('ver_code', $request->token)->first();

        if (!$user || Carbon::parse($user->ver_code_send_at)->addHours(1)->isPast()) {
            return back()->withErrors(['token' => 'Reset link is invalid or has expired.']);
        }

        $user->password         = Hash::make($request->password);
        $user->ver_code         = null;
        $user->ver_code_send_at = null;
        $user->save();

        return redirect()->route('user.login')->with('success', 'Password reset successfully. Please login.');
    }

    // ─────────────────────────────────────────────
    //  LOGOUT
    // ─────────────────────────────────────────────

    public function logout()
    {
        Auth::logout();
        return redirect()->route('user.login');
    }

    // ─────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────

    protected function generateOtp(User $user): string
    {
        $otp = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        // Store plaintext — OTPs are short-lived 4-digit codes.
        // Hashing into a 60-char bcrypt string silently truncates in MySQL
        // when ver_code column is varchar(40), making Hash::check() always fail.
        $user->ver_code         = $otp;
        $user->ver_code_send_at = now();
        $user->save();

        return $otp;
    }

    protected function isOtpValid(User $user, string $otp): bool
    {
        // Re-fetch fresh from DB — ensures we read the saved value, not
        // a stale in-memory copy (ver_code is in $hidden so won't show
        // in toArray() but is readable directly on the model instance).
        $fresh = $user->fresh();

        if (!$fresh || !$fresh->ver_code || !$fresh->ver_code_send_at) {
            return false;
        }

        if (Carbon::parse($fresh->ver_code_send_at)->addMinutes(10)->isPast()) {
            return false;
        }

        return $fresh->ver_code === $otp;
    }

    protected function clearOtp(User $user): void
    {
        $user->ver_code         = null;
        $user->ver_code_send_at = null;
        $user->save();
    }

    protected function features(): array
    {
        return [
            '25 Free Real Time Tools',
            '59 Premium Real Time Tools',
            '2 Option Algorithm',
        ];
    }

    protected function brokers(): array
    {
        return [
            ['name' => 'Zerodha',       'letter' => 'Z', 'bg' => '#e53935'],
            ['name' => 'Upstox',        'letter' => 'U', 'bg' => '#7b1fa2'],
            ['name' => 'Dhan',          'letter' => 'D', 'bg' => '#00897b'],
            ['name' => '5Paisa',        'letter' => '5', 'bg' => '#455a64'],
            ['name' => 'Motilal Oswal', 'letter' => 'M', 'bg' => '#f57f17'],
            ['name' => 'Fyers',         'letter' => 'F', 'bg' => '#1565c0'],
            ['name' => 'Choice',        'letter' => 'C', 'bg' => '#6a1b9a'],
            ['name' => 'Aliceblue',     'letter' => 'A', 'bg' => '#00838f'],
            ['name' => 'Sharekhan',     'letter' => 'S', 'bg' => '#bf360c'],
            ['name' => 'Angel',         'letter' => 'A', 'bg' => '#2e7d32'],
            ['name' => 'Groww',         'letter' => 'G', 'bg' => '#00695c'],
            ['name' => 'ICICI',         'letter' => 'I', 'bg' => '#b71c1c'],
            ['name' => 'HDFC Sky',      'letter' => 'H', 'bg' => '#1a237e'],
            ['name' => 'Kotak',         'letter' => 'K', 'bg' => '#e65100'],
        ];
    }
}