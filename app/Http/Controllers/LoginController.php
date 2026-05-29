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
use App\Mail\EmailVerificationMail;
use App\Mail\ResetPasswordMail;

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
        $request->validate(['email' => 'required|email', 'otp' => 'required|digits:4']);
        $user = User::where('email', $request->email)->first();
        if (!$user || !$this->isOtpValid($user, $request->otp)) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP.']);
        }
        $this->clearOtp($user);
        Auth::login($user);
        return response()->json(['success' => true, 'redirect' => route('user.dashboard')]);
    }

    // ─────────────────────────────────────────────
    //  LOGIN — Password login
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
        if (!$user->ev) {
            return back()->withErrors(['email' => 'Please verify your email address first.']);
        }
        Auth::login($user, $request->remember);
        return redirect()->route('user.dashboard');
    }

    // ─────────────────────────────────────────────
    //  REGISTER — Show page
    // ─────────────────────────────────────────────
    public function showRegister()
    {
        $pageTitle = 'Create Account';
        $cms       = AuthPageCms::getData();

        return view($this->activeTemplate . 'register', [
            'pageTitle'          => $pageTitle,
            'features'           => $cms->features_list,
            'brokers'            => $cms->brokers_list,
            'promoVideo'         => $cms->promo_video_url ?? 'https://www.youtube.com/embed/MxpeY6j-_XE?si=7BILhTJxdUhdBP5O&autoplay=1&mute=1&rel=0&modestbranding=1&controls=1',
            'registerHeading'    => $cms->register_heading    ?? 'Create Account',
            'registerSubheading' => $cms->register_subheading ?? 'Join thousands of option traders',
        ]);
    }

    // ─────────────────────────────────────────────
    //  REGISTER — Store
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
            'firstname'         => $request->firstname,
            'lastname'          => $request->lastname,
            'email'             => $request->email,
            'mobile'            => $request->mobile ?? null,
            'country_code'      => 'IN',
            'user_code'         => $userCode,
            'username'          => $userCode,
            'password'          => Hash::make(Str::random(16)),
            'status'            => Status::USER_ACTIVE,
            'ev'                => Status::UNVERIFIED,
            'sv'                => Status::VERIFIED,
            'ver_code'          => $token,
            'ver_code_send_at'  => now(),
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
            return redirect()->route('user.login')->with('error', 'Invalid or expired verification link.');
        }
        if (Carbon::parse($user->ver_code_send_at)->addHours(24)->isPast()) {
            return redirect()->route('user.login')->with('error', 'Verification link has expired.');
        }
        $pageTitle = 'Set Your Password';
        return view($this->activeTemplate . 'set-password', compact('pageTitle', 'token', 'user'));
    }

    public function setPassword(Request $request)
    {
        $request->validate(['token' => 'required', 'password' => 'required|min:8|confirmed']);
        $user = User::where('ver_code', $request->token)->first();
        if (!$user) {
            return back()->withErrors(['token' => 'Invalid or expired link.']);
        }
        $user->password          = Hash::make($request->password);
        $user->ev                = Status::VERIFIED;
        $user->ver_code          = null;
        $user->ver_code_send_at  = null;
        $user->save();
        Auth::login($user);
        return redirect()->route('user.dashboard')->with('success', 'Welcome to CityQuants!');
    }

    // ─────────────────────────────────────────────
    //  FORGOT PASSWORD
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
            $token                  = Str::random(64);
            $user->ver_code         = $token;
            $user->ver_code_send_at = now();
            $user->save();
            Mail::to($user->email)->send(new ResetPasswordMail($user, $token));
        }
        return response()->json(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);
    }

    public function showResetPassword($token)
    {
        $user = User::where('ver_code', $token)->first();
        if (!$user || Carbon::parse($user->ver_code_send_at)->addHours(1)->isPast()) {
            return redirect()->route('user.login')->with('error', 'Reset link has expired or is invalid.');
        }
        $pageTitle = 'Reset Password';
        return view($this->activeTemplate . 'reset-password', compact('pageTitle', 'token'));
    }

    public function resetPassword(Request $request)
    {
        $request->validate(['token' => 'required', 'password' => 'required|min:8|confirmed']);
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