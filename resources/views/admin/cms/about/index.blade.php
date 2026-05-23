{{-- FILE: resources/views/admin/cms/about/index.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-12 mb-4">
        <div class="card b-radius--10" style="background:linear-gradient(135deg,#0f1b2d,#1a3050);border:1px solid rgba(245,166,35,.2);">
            <div class="card-body d-flex align-items-center gap-3 py-4">
                <i class="las la-info-circle" style="font-size:36px;color:#F5A623;"></i>
                <div>
                    <h4 style="color:#fff;margin:0;font-size:20px;">About Page CMS</h4>
                    <p style="color:rgba(255,255,255,.5);margin:0;font-size:13px;">Manage all sections of the about page.</p>
                </div>
            </div>
        </div>
    </div>
    @foreach([
        ['route'=>'cms.about.hero',           'icon'=>'la-star',           'title'=>'Hero Banner',       'desc'=>'Tagline, subtitle, stat boxes'],
        ['route'=>'cms.about.who_we_are',     'icon'=>'la-users',          'title'=>'Who We Are',        'desc'=>'Heading, body text, pillars'],
        ['route'=>'cms.about.mission',        'icon'=>'la-bullseye',       'title'=>'Mission & Vision',  'desc'=>'Heading, body, value items'],
        ['route'=>'cms.about.founders',       'icon'=>'la-user-tie',       'title'=>'Founding Members',  'desc'=>'Photos, names, bios'],
        ['route'=>'cms.about.workspace',      'icon'=>'la-building',       'title'=>'Workspace & Offices','desc'=>'Photo slides, city cards'],
        ['route'=>'cms.about.founder_vision', 'icon'=>'la-quote-left',     'title'=>'Founder Vision',    'desc'=>'CEO card and paragraphs'],
    ] as $item)
    <div class="col-xl-3 col-md-4 col-sm-6 mb-4">
        <a href="{{ route('admin.'.$item['route']) }}" style="text-decoration:none;">
            <div class="card b-radius--10 h-100" style="border:1px solid #e5e9f2;transition:all .25s;"
                 onmouseover="this.style.borderColor='#F5A623';this.style.boxShadow='0 4px 20px rgba(245,166,35,.15)'"
                 onmouseout="this.style.borderColor='#e5e9f2';this.style.boxShadow='none'">
                <div class="card-body text-center py-4">
                    <div style="width:56px;height:56px;border-radius:14px;background:rgba(245,166,35,.1);border:1px solid rgba(245,166,35,.25);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:24px;color:#F5A623;">
                        <i class="las {{ $item['icon'] }}"></i>
                    </div>
                    <h6 style="font-weight:700;color:#1a1a2e;margin-bottom:5px;">{{ $item['title'] }}</h6>
                    <p style="font-size:12px;color:#888;margin:0;">{{ $item['desc'] }}</p>
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>
@endsection

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- FILE: resources/views/admin/cms/about/hero.blade.php              --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
