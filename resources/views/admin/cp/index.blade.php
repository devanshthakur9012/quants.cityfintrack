{{-- FILE: resources/views/admin/cp/index.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-12 mb-4">
        <div class="card b-radius--10"
             style="background:linear-gradient(135deg,#0f1b2d,#1a3050);
                    border:1px solid rgba(245,166,35,.2);">
            <div class="card-body d-flex align-items-center gap-3 py-4">
                <i class="las la-chart-line" style="font-size:36px;color:#F5A623;"></i>
                <div>
                    <h4 style="color:#fff;margin:0;font-size:20px;">CP Analysis System</h4>
                    <p style="color:rgba(255,255,255,.5);margin:0;font-size:13px;">
                        Analyses · Subscriptions · Payments · Data Config
                    </p>
                </div>
            </div>
        </div>
    </div>

    @foreach([
        ['route'=>'admin.cp.analyses.index',     'icon'=>'la-brain',          'title'=>'Analysis Tools',     'desc'=>'Create modules, set free/pro/pro_plus tier'],
        ['route'=>'admin.cp.plans.index',        'icon'=>'la-crown',          'title'=>'Subscription Plans', 'desc'=>'Manage Free / Pro / Pro Plus plans'],
        ['route'=>'admin.cp.subscriptions.index','icon'=>'la-users',          'title'=>'User Subscriptions', 'desc'=>'View & manage all subscriber records'],
        ['route'=>'admin.cp.payments.index',     'icon'=>'la-rupee-sign',     'title'=>'Payments',           'desc'=>'All subscription payment records'],
        ['route'=>'admin.cp.gateway',            'icon'=>'la-key',            'title'=>'Payment Gateway',    'desc'=>'Razorpay keys for CP subscriptions'],
        ['route'=>'admin.zerodha-broker.index',  'icon'=>'la-server',         'title'=>'Zerodha Brokers',    'desc'=>'Broker API accounts'],
        ['route'=>'admin.symbol-list.index',     'icon'=>'la-list-ul',        'title'=>'Symbol List',        'desc'=>'50 stocks used in analysis'],
        ['route'=>'admin.analysis-config.index', 'icon'=>'la-cogs',           'title'=>'Analysis Config',    'desc'=>'Broker + symbols (15min fixed)'],
    ] as $item)
    <div class="col-xl-3 col-md-4 col-sm-6 mb-4">
        <a href="{{ route($item['route']) }}" style="text-decoration:none;">
            <div class="card b-radius--10 h-100"
                 style="border:1px solid #e5e9f2; transition:all .25s;"
                 onmouseover="this.style.borderColor='#F5A623';
                              this.style.boxShadow='0 4px 20px rgba(245,166,35,.15)'"
                 onmouseout="this.style.borderColor='#e5e9f2';
                             this.style.boxShadow='none'">
                <div class="card-body text-center py-4">
                    <div style="width:56px;height:56px;border-radius:14px;
                                background:rgba(245,166,35,.1);
                                border:1px solid rgba(245,166,35,.25);
                                display:flex;align-items:center;justify-content:center;
                                margin:0 auto 16px;font-size:24px;color:#F5A623;">
                        <i class="las {{ $item['icon'] }}"></i>
                    </div>
                    <h6 style="font-weight:700;color:#1a1a2e;margin-bottom:5px;">
                        {{ $item['title'] }}
                    </h6>
                    <p style="font-size:12px;color:#888;margin:0;">{{ $item['desc'] }}</p>
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>
@endsection