<?php
// FILE: app/Http/Controllers/UserDashboardController.php

namespace App\Http\Controllers;

use App\Models\CourseEnrollment;
use App\Models\CourseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserDashboardController extends Controller
{
    public $activeTemplate;

    public function __construct()
    {
        $this->activeTemplate = activeTemplate();
        $this->middleware('auth:web');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DASHBOARD
    // ─────────────────────────────────────────────────────────────────────────
    public function index()
    {
        $user      = Auth::guard('web')->user();
        $pageTitle = 'My Dashboard';

        $enrollments = CourseEnrollment::with([
                'course' => fn($q) => $q->with(['category', 'lessons']),
                'order',
            ])
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->orderByDesc('enrolled_at')
            ->get();

        $totalEnrolled   = $enrollments->count();
        $paidCourses     = $enrollments->where('access_type', 'paid')->count();
        $freeCourses     = $enrollments->where('access_type', 'free')->count();
        $totalSpent      = CourseOrder::where('user_id', $user->id)->where('status', 'paid')->sum('amount');
        $recentOrders    = CourseOrder::with('course')->where('user_id', $user->id)->orderByDesc('created_at')->limit(10)->get();
        $activeCourses   = $enrollments->filter(fn($e) => $e->isActive() && optional($e->course)->status !== 'recorded');
        $recordedCourses = $enrollments->filter(fn($e) => optional($e->course)->status === 'recorded');

        return view($this->activeTemplate . 'dashboard', compact(
            'pageTitle', 'user',
            'enrollments', 'activeCourses', 'recordedCourses',
            'totalEnrolled', 'paidCourses', 'freeCourses',
            'totalSpent', 'recentOrders'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROFILE — view + update
    // ─────────────────────────────────────────────────────────────────────────
    public function profile()
    {
        $user      = Auth::guard('web')->user();
        $pageTitle = 'Profile Settings';

        return view($this->activeTemplate . 'profile', compact('pageTitle', 'user'));
    }

    public function profileUpdate(Request $request)
    {
        $user = Auth::guard('web')->user();

        $request->validate([
            'firstname'   => 'required|string|max:100',
            'lastname'    => 'required|string|max:100',
            'mobile'      => 'required|string|max:20|unique:users,mobile,' . $user->id,
            'profile_pic' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('profile_pic')) {
            $user->profile_pic = fileUploader(
                $request->file('profile_pic'),
                getFilePath('userProfile'),
                getFileSize('userProfile'),
                $user->profile_pic
            );
        }

        $user->update([
            'firstname'         => $request->firstname,
            'lastname'          => $request->lastname,
            'mobile'            => $request->mobile,
            'telegram_username' => $request->telegram_username,
            'profile_pic'       => $user->profile_pic,
            'address'           => (object)[
                'address' => $request->address,
                'city'    => $request->city,
                'state'   => $request->state,
                'zip'     => $request->zip,
            ],
        ]);

        $notify[] = ['success', 'Profile updated successfully.'];
        return back()->withNotify($notify);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CHANGE PASSWORD — view + update
    // ─────────────────────────────────────────────────────────────────────────
    public function changePassword()
    {
        $user      = Auth::guard('web')->user();
        $pageTitle = 'Change Password';

        return view($this->activeTemplate . 'change-password', compact('pageTitle', 'user'));
    }

    public function changePasswordUpdate(Request $request)
    {
        $user = Auth::guard('web')->user();

        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            $notify[] = ['error', 'Current password is incorrect.'];
            return back()->withNotify($notify);
        }

        $user->update(['password' => Hash::make($request->password)]);

        $notify[] = ['success', 'Password changed successfully.'];
        return back()->withNotify($notify);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('user.login');
    }
}