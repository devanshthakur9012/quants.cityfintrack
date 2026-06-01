{{-- FILE: resources/views/admin/cms/home/index.blade.php --}}
@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-12 mb-4">
        <div class="card b-radius--10" style="background:linear-gradient(135deg,#0f1b2d,#1a3050);border:1px solid rgba(245,166,35,.2);">
            <div class="card-body d-flex align-items-center gap-3 py-4">
                <i class="las la-home" style="font-size:36px;color:#7DFF00;"></i>
                <div>
                    <h4 style="color:#fff;margin:0;font-size:20px;">Home Page CMS</h4>
                    <p style="color:rgba(255,255,255,.5);margin:0;font-size:13px;">Manage all sections of the home page from here.</p>
                </div>
            </div>
        </div>
    </div>

    @foreach([
        ['route'=>'cms.home.hero',         'icon'=>'la-film',         'title'=>'Hero Section',       'desc'=>'Video background, heading text'],
        ['route'=>'cms.home.platform',     'icon'=>'la-layer-group',  'title'=>'Platform Banner',    'desc'=>'Main title and subtitle text'],
        ['route'=>'cms.home.cert_slides',  'icon'=>'la-images',       'title'=>'Cert Slides',        'desc'=>'Upload certification slider images'],
        ['route'=>'cms.home.about_section','icon'=>'la-info-circle',  'title'=>'About Section',      'desc'=>'Video and 4 stats boxes'],
        ['route'=>'cms.home.features',     'icon'=>'la-toolbox',      'title'=>'Feature Tools',      'desc'=>'Utility tabs and tool content'],
        ['route'=>'cms.home.learning',     'icon'=>'la-graduation-cap','title'=>'Learning Tabs',     'desc'=>'Webinars, demo video tabs'],
        ['route'=>'cms.home.testimonials', 'icon'=>'la-comments',     'title'=>'Testimonials',       'desc'=>'User reviews and ratings'],
    ] as $item)
    <div class="col-xl-3 col-md-4 col-sm-6 mb-4">
        <a href="{{ route('admin.'.$item['route']) }}" style="text-decoration:none;">
            <div class="card b-radius--10 h-100" style="border:1px solid #e5e9f2;transition:all .25s;"
                 onmouseover="this.style.borderColor='#7DFF00';this.style.boxShadow='0 4px 20px rgba(245,166,35,.15)'"
                 onmouseout="this.style.borderColor='#e5e9f2';this.style.boxShadow='none'">
                <div class="card-body text-center py-4">
                    <div style="width:56px;height:56px;border-radius:14px;background:rgba(245,166,35,.1);border:1px solid rgba(245,166,35,.25);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:24px;color:#7DFF00;">
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
