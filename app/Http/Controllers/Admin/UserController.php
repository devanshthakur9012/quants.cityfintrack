<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\EmployeeProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Constants\Status;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    // ─────────────────────────────────────────────
    //  LIST VIEWS
    // ─────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'All Users';
        $users = $this->getUsers();
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function activeUsers()
    {
        $pageTitle = 'Active Users';
        $users = $this->getUsers('active');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function bannedUsers()
    {
        $pageTitle = 'Banned Users';
        $users = $this->getUsers('banned');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function emailVerifiedUsers()
    {
        $pageTitle = 'Email Verified Users';
        $users = $this->getUsers('emailVerified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function emailUnverifiedUsers()
    {
        $pageTitle = 'Email Unverified Users';
        $users = $this->getUsers('emailUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function mobileVerifiedUsers()
    {
        $pageTitle = 'Mobile Verified Users';
        $users = $this->getUsers('mobileVerified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    public function mobileUnverifiedUsers()
    {
        $pageTitle = 'Mobile Unverified Users';
        $users = $this->getUsers('mobileUnverified');
        return view('admin.users.list', compact('pageTitle', 'users'));
    }

    // ─────────────────────────────────────────────
    //  CREATE USER
    // ─────────────────────────────────────────────

    public function create()
    {
        $pageTitle = 'Add User';
        $roles     = Role::where('name', '!=', 'admin')->get();
        $countries = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        return view('admin.users.create', compact('pageTitle', 'roles', 'countries'));
    }

    public function store(Request $request)
    {
        $rules = [
            'firstname' => 'required|string|max:100',
            'lastname'  => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email',
            'mobile'    => 'required|string|unique:users,mobile',
            'password'  => 'required|min:6|confirmed',
            'roles'     => 'required|array|min:1',
            'roles.*'   => 'exists:roles,id',
        ];

        // Employee-specific validation if employee role is selected
        $selectedRoleNames = Role::whereIn('id', $request->roles ?? [])->pluck('name');
        if ($selectedRoleNames->contains('employee')) {
            $rules['employee_code']  = 'required|string|unique:employee_profiles,employee_code';
            $rules['department']     = 'nullable|string|max:100';
            $rules['designation']    = 'nullable|string|max:100';
            $rules['date_of_joining'] = 'nullable|date';
        }

        $request->validate($rules);

        $userCode = 'USR' . mt_rand(10000, 99999);

        $user = User::create([
            'firstname'    => $request->firstname,
            'lastname'     => $request->lastname,
            'email'        => $request->email,
            'mobile'       => $request->mobile,
            'country_code' => $request->country ?? 'IN',
            'user_code'    => $userCode,
            'username'     => $userCode,
            'password'     => Hash::make($request->password),
            'status'       => Status::USER_ACTIVE,
            'ev'           => Status::VERIFIED,
            'sv'           => Status::VERIFIED,
        ]);

        if ($request->hasFile('profile_pic')) {
            $user->profile_pic = fileUploader($request->profile_pic, getFilePath('userProfile'), getFileSize('userProfile'));
            $user->save();
        }

        // Assign roles (multiple allowed)
        $roles = Role::whereIn('id', $request->roles)->get();
        $user->syncRoles($roles);

        // Create employee profile if employee role assigned
        if ($selectedRoleNames->contains('employee')) {
            EmployeeProfile::create([
                'user_id'         => $user->id,
                'employee_code'   => $request->employee_code,
                'department'      => $request->department,
                'designation'     => $request->designation,
                'date_of_joining' => $request->date_of_joining,
            ]);
        }

        $notify[] = ['success', 'User created successfully.'];
        return redirect()->route('admin.users.index')->withNotify($notify);
    }

    // ─────────────────────────────────────────────
    //  DETAIL / EDIT USER
    // ─────────────────────────────────────────────

    public function detail($id)
    {
        $user      = User::with('employeeProfile')->findOrFail($id);
        $pageTitle = 'User Detail – ' . $user->username;
        $roles     = Role::where('name', '!=', 'admin')->get();
        $countries = json_decode(file_get_contents(resource_path('views/partials/country.json')));

        return view('admin.users.detail', compact('pageTitle', 'user', 'roles', 'countries'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $countryData  = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryArray = (array) $countryData;
        $countries    = implode(',', array_keys($countryArray));

        $rules = [
            'firstname' => 'required|string|max:100',
            'lastname'  => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email,' . $user->id,
            'mobile'    => 'required|string|unique:users,mobile,' . $user->id,
            'country'   => 'required|in:' . $countries,
            'roles'     => 'required|array|min:1',
            'roles.*'   => 'exists:roles,id',
        ];

        // Employee profile validation
        $selectedRoleNames = Role::whereIn('id', $request->roles ?? [])->pluck('name');
        if ($selectedRoleNames->contains('employee')) {
            $rules['employee_code']   = 'required|string|unique:employee_profiles,employee_code,' . optional($user->employeeProfile)->id;
            $rules['department']      = 'nullable|string|max:100';
            $rules['designation']     = 'nullable|string|max:100';
            $rules['date_of_joining'] = 'nullable|date';
        }

        $request->validate($rules);

        $countryCode = $request->country;
        $dialCode    = $countryData->$countryCode->dial_code;

        if ($request->hasFile('profile_pic')) {
            $user->profile_pic = fileUploader(
                $request->profile_pic,
                getFilePath('userProfile'),
                getFileSize('userProfile'),
                $user->profile_pic   // old file gets deleted automatically
            );
        }

        $user->update([
            'firstname'    => $request->firstname,
            'lastname'     => $request->lastname,
            'profile_pic'  => $user->profile_pic,
            'email'        => $request->email,
            'mobile'       => $dialCode . $request->mobile,
            'country_code' => $countryCode,
            'telegram_username' => $request->telegram_username,
            'address'      => [
                'address' => $request->address,
                'city'    => $request->city,
                'state'   => $request->state,
                'zip'     => $request->zip,
                'country' => $countryData->$countryCode->country,
            ],
            'ev' => $request->ev ? Status::VERIFIED : Status::UNVERIFIED,
            'sv' => $request->sv ? Status::VERIFIED : Status::UNVERIFIED,
            'ts' => $request->ts ? Status::ENABLE   : Status::DISABLE,
        ]);

        // Sync roles
        $roles = Role::whereIn('id', $request->roles)->get();
        $user->syncRoles($roles);

        // Create/update/delete employee profile
        if ($selectedRoleNames->contains('employee')) {
            EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_code'   => $request->employee_code,
                    'department'      => $request->department,
                    'designation'     => $request->designation,
                    'date_of_joining' => $request->date_of_joining,
                ]
            );
        } else {
            // Remove employee profile if employee role is no longer assigned
            optional($user->employeeProfile)->delete();
        }

        $notify[] = ['success', 'User updated successfully.'];
        return back()->withNotify($notify);
    }

    // ─────────────────────────────────────────────
    //  USER STATUS (BAN / UNBAN)
    // ─────────────────────────────────────────────

    public function status(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->status == Status::USER_ACTIVE) {
            $request->validate(['reason' => 'required|string|max:255']);
            $user->status     = Status::USER_BAN;
            $user->ban_reason = $request->reason;
            $notify[]         = ['success', 'User banned successfully.'];
        } else {
            $user->status     = Status::USER_ACTIVE;
            $user->ban_reason = null;
            $notify[]         = ['success', 'User unbanned successfully.'];
        }

        $user->save();
        return back()->withNotify($notify);
    }

    // ─────────────────────────────────────────────
    //  LOGIN AS USER
    // ─────────────────────────────────────────────

    public function loginAsUser($id)
    {
        Auth::loginUsingId($id);
        return to_route('user.home');
    }

    // ─────────────────────────────────────────────
    //  NOTIFICATIONS
    // ─────────────────────────────────────────────

    public function showNotificationForm($id)
    {
        $user      = User::findOrFail($id);
        $pageTitle = 'Send Notification to ' . $user->username;
        return view('admin.users.notification_single', compact('pageTitle', 'user'));
    }

    public function sendNotification(Request $request, $id)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $user = User::findOrFail($id);
        notify($user, 'DEFAULT', [
            'subject' => $request->subject,
            'message' => $request->message,
        ]);

        $notify[] = ['success', 'Notification sent successfully.'];
        return back()->withNotify($notify);
    }

    public function notificationLog($id)
    {
        $user      = User::findOrFail($id);
        $pageTitle = 'Notifications Sent to ' . $user->username;
        $logs      = NotificationLog::where('user_id', $id)->with('user')->orderByDesc('id')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle', 'logs', 'user'));
    }

    // ─────────────────────────────────────────────
    //  AJAX USER LIST
    // ─────────────────────────────────────────────

    public function list()
    {
        $query = User::active()->searchable(['username', 'email']);
        $users = $query->orderByDesc('id')->paginate(getPaginate());

        return response()->json([
            'success' => true,
            'users'   => $users,
            'more'    => $users->hasMorePages(),
        ]);
    }

    // ─────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────

    protected function getUsers(string $scope = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $scope ? User::$scope() : User::query();

        return $query
            ->searchable(['username', 'email'])
            ->with('roles')
            ->orderByDesc('id')
            ->paginate(getPaginate());
    }

    public function employees()
    {
        $pageTitle = 'All Employees';
        $employees = User::role('employee')
            ->with(['roles', 'employeeProfile'])
            ->searchable(['username', 'email'])
            ->orderByDesc('id')
            ->paginate(getPaginate());

        return view('admin.users.employees', compact('pageTitle', 'employees'));
    }

}