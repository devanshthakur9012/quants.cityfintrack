<?php
// FILE: app/Http/Controllers/LoginController.php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuthPageCms;
use App\Constants\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\OtpMail;
use App\Mail\ResetPasswordMail;
use Spatie\Permission\Models\Role;

class LoginController extends Controller
{
    // ─────────────────────────────────────────────
    //  LOGIN — Show page
    // ─────────────────────────────────────────────
    public function showLogin()
    {
        $pageTitle = 'Login';
        $cms       = AuthPageCms::getData();

        return view($this->activeTemplate . 'login', [
            'pageTitle'       => $pageTitle,
            'features'        => $cms->features_list,
            'brokers'         => $cms->brokers_list,
            'promoVideo'      => $cms->promo_video_url ?? 'https://www.youtube.com/embed/MxpeY6j-_XE?si=7BILhTJxdUhdBP5O&autoplay=1&mute=1&rel=0&modestbranding=1&controls=1',
            'loginHeading'    => $cms->login_heading    ?? 'Welcome Back',
            'loginSubheading' => $cms->login_subheading ?? 'Sign in to your CityQuants account',
        ]);
    }

    // ─────────────────────────────────────────────
    //  LOGIN — Send OTP
    //  Auto-creates account if email is new.
    //  No registration page needed.
    // ─────────────────────────────────────────────
    public function sendLoginOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // ── Auto-create account if new email ──────────────────────────────
        if (!$user) {
            $userCode = 'CQ' . mt_rand(10000, 99999);
            $user = User::create([
                'firstname'        => 'CityQuant',
                'lastname'         => 'User',
                'email'            => $request->email,
                'mobile'           => null,
                'country_code'     => 'IN',
                'user_code'        => $userCode,
                'username'         => $userCode,
                'password'         => Hash::make(Str::random(24)), // random — never used
                'status'           => Status::USER_ACTIVE,
                'ev'               => Status::VERIFIED,  // auto-verified via OTP
                'sv'               => Status::VERIFIED,
                'ver_code_send_at' => now(),
            ]);
            $this->assignUserRole($user);
        }

        // ── Existing user checks ──────────────────────────────────────────
        if ($user->status == Status::USER_BAN) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact support.',
            ]);
        }

        // Ensure existing users also have the role
        $this->assignUserRole($user);

        // Generate and send OTP
        $otp = $this->generateOtp($user);
        Mail::to($user->email)->send(new OtpMail($user, $otp, 'Your CityQuants Login OTP'));

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email.',
        ]);
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
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP. Please try again.',
            ]);
        }

        if ($user->status == Status::USER_BAN) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended.',
            ]);
        }

        $this->clearOtp($user);

        // Mark email as verified (in case it wasn't)
        if (!$user->ev) {
            $user->ev = Status::VERIFIED;
            $user->save();
        }

        Auth::login($user, false); // no remember-me — expires on browser close
        config(['session.lifetime' => 1440]); // 24h max
        session()->regenerate();
        session(['login_at' => now()->toDateTimeString()]);

        return response()->json([
            'success'  => true,
            'redirect' => route('user.dashboard'),
        ]);
    }

    // ─────────────────────────────────────────────
    //  PASSWORD LOGIN (kept for admin/employees
    //  who may need it — remove from UI if unused)
    // ─────────────────────────────────────────────
    public function loginWithPassword(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }
        if ($user->status == Status::USER_BAN) {
            return back()->withErrors(['email' => 'Your account has been suspended.']);
        }
        if (!$user->hasRole('user')) {
            return back()->withErrors([
                'email' => 'Access denied. This portal is for registered members only.',
            ])->withInput();
        }

        Auth::login($user, false);
        config(['session.lifetime' => 1440]);
        session()->regenerate();
        session(['login_at' => now()->toDateTimeString()]);

        return redirect()->route('user.dashboard');
    }

    // ─────────────────────────────────────────────
    //  FORGOT / RESET PASSWORD
    // ─────────────────────────────────────────────
    public function showForgotPassword()
    {
        $pageTitle = 'Forgot Password';
        $cms       = AuthPageCms::getData();
        return view($this->activeTemplate . 'forgot-password', [
            'pageTitle'  => $pageTitle,
            'features'   => $cms->features_list,
            'brokers'    => $cms->brokers_list,
            'promoVideo' => $cms->promo_video_url ?? 'https://www.youtube.com/embed/MxpeY6j-_XE?si=7BILhTJxdUhdBP5O&autoplay=1&mute=1&rel=0',
        ]);
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if ($user && $user->ev) {
            $user->ver_code_send_at = now();
            $user->save();
            $encryptedEmail = encrypt($user->email);
            Mail::to($user->email)->send(new ResetPasswordMail($user, $encryptedEmail));
        }

        return response()->json([
            'success' => true,
            'message' => 'If that email exists, a reset link has been sent.',
        ]);
    }

    public function showResetPassword($token)
    {
        try { $email = decrypt($token); }
        catch (\Exception $e) {
            return redirect()->route('user.login')->with('error', 'Invalid reset link.');
        }

        $user = User::where('email', $email)->first();
        if (!$user || Carbon::parse($user->ver_code_send_at)->addHours(1)->isPast()) {
            return redirect()->route('user.login')->with('error', 'Reset link has expired or is invalid.');
        }

        $pageTitle = 'Reset Password';
        return view($this->activeTemplate . 'reset-password', compact('pageTitle', 'token'));
    }

    public function resetPassword(Request $request)
    {
        $request->validate(['token' => 'required', 'password' => 'required|min:8|confirmed']);

        try { $email = decrypt($request->token); }
        catch (\Exception $e) {
            return back()->withErrors(['token' => 'Invalid or expired link.']);
        }

        $user = User::where('email', $email)->first();
        if (!$user || Carbon::parse($user->ver_code_send_at)->addHours(1)->isPast()) {
            return back()->withErrors(['token' => 'Reset link is invalid or has expired.']);
        }

        $user->password         = Hash::make($request->password);
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
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->route('user.login');
    }

    // ─────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────
    protected function assignUserRole(User $user): void
    {
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        if (!$user->hasRole('user')) {
            $user->assignRole($role);
        }
    }

    protected function generateOtp(User $user): string
    {
        $otp                    = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $user->ver_code         = $otp;
        $user->ver_code_send_at = now();
        $user->save();
        return $otp;
    }

    protected function isOtpValid(User $user, string $otp): bool
    {
        $fresh = $user->fresh();
        if (!$fresh || !$fresh->ver_code || !$fresh->ver_code_send_at) return false;
        if (Carbon::parse($fresh->ver_code_send_at)->addMinutes(10)->isPast()) return false;
        return $fresh->ver_code === $otp;
    }

    protected function clearOtp(User $user): void
    {
        $user->ver_code         = null;
        $user->ver_code_send_at = null;
        $user->save();
    }
}