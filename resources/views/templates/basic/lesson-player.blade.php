{{-- FILE: resources/views/themes/{activeTemplate}/lesson-player.blade.php --}}
@extends($activeTemplate.'layouts.frontend')

@section('content')
      content="media-src 'self' https://www.youtube.com https://www.youtube-nocookie.com; object-src 'none';">
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&family=Exo+2:wght@400;500;600&display=swap" rel="stylesheet">

@php
    // ── Parse YouTube embed ID from URL ──────────────────────────────────────
    // Handles: youtube.com/watch?v=XXX, youtu.be/XXX, youtube.com/embed/XXX
    $ytEmbedId = null;
    if ($lesson->video_type === 'youtube' && $lesson->video_url) {
        $url = $lesson->video_url;
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_\-]{11})/', $url, $m)) {
            $ytEmbedId = $m[1];
        }
    }
    $isUpload  = $lesson->video_type === 'upload' && $lesson->video_path;
    $isYoutube = $lesson->video_type === 'youtube' && $ytEmbedId;
    $hasVideo  = $isUpload || $isYoutube;
@endphp

<style>
*{box-sizing:border-box;}
body{background:#0a0f1a;margin:0;}
.lp{font-family:'Exo 2',sans-serif;background:#0a0f1a;min-height:100vh;color:#e0e6f0;display:flex;flex-direction:column;}
.lp h1,.lp h2,.lp h3,.lp h4{font-family:'Rajdhani',sans-serif;}

/* ── TOP BAR ── */
.lp-bar{background:#0f1b2d;border-bottom:1px solid rgba(255,255,255,.08);padding:0 20px;height:52px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:200;}
.lp-bar-back{color:rgba(255,255,255,.55);font-size:13px;display:flex;align-items:center;gap:6px;text-decoration:none;transition:color .2s;white-space:nowrap;}
.lp-bar-back:hover{color:#f5a623;}
.lp-bar-title{font-family:'Rajdhani',sans-serif;font-size:15px;font-weight:700;color:#fff;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.lp-bar-sub{font-size:11px;color:rgba(255,255,255,.35);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;}
.lp-nav-btn{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.7);padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;text-decoration:none;transition:all .2s;font-family:'Exo 2',sans-serif;white-space:nowrap;}
.lp-nav-btn:hover{background:rgba(245,166,35,.15);border-color:#f5a623;color:#f5a623;}
.lp-nav-btn.next{background:#f5a623;color:#0f1b2d;border-color:#f5a623;}
.lp-nav-btn.next:hover{background:#d4890e;}

/* ── BODY ── */
.lp-body{display:flex;flex:1;height:calc(100vh - 52px);overflow:hidden;}

/* ── VIDEO COLUMN ── */
.lp-main{flex:1;display:flex;flex-direction:column;overflow-y:auto;min-width:0;}

/* ── VIDEO AREA ── */
.lp-video-area{position:relative;width:100%;background:#000;aspect-ratio:16/9;max-height:70vh;flex-shrink:0;}

/* Upload video: shield blocks right-click save when paused */
.lp-shield{position:absolute;inset:0;z-index:5;pointer-events:none;background:transparent;}
.lp-video-area.paused .lp-shield{pointer-events:all;}

.lp-video-area video{width:100%;height:100%;display:block;background:#000;object-fit:contain;}

/* YouTube iframe */
.lp-yt-wrap{position:absolute;inset:0;width:100%;height:100%;}
.lp-yt-wrap iframe{width:100%;height:100%;border:none;display:block;}

/* ── STATE OVERLAY ── */
.lp-state{position:absolute;inset:0;z-index:10;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;}
.lp-state.gone{display:none;}
.lp-spinner{width:46px;height:46px;border:4px solid rgba(255,255,255,.08);border-top-color:#f5a623;border-radius:50%;animation:lspin .8s linear infinite;}
@keyframes lspin{to{transform:rotate(360deg);}}
.lp-state-txt{font-size:13px;color:rgba(255,255,255,.5);text-align:center;padding:0 20px;}
.lp-state-icon{font-size:48px;opacity:.25;}

/* ── VIDEO TYPE BADGE ── */
.lp-type-badge{position:absolute;top:10px;left:10px;z-index:20;font-size:10px;font-weight:700;padding:3px 10px;border-radius:4px;letter-spacing:.05em;text-transform:uppercase;display:flex;align-items:center;gap:4px;}
.lp-type-badge.yt{background:rgba(229,57,53,.85);color:#fff;}
.lp-type-badge.upload{background:rgba(26,86,219,.85);color:#fff;}

/* ── CONTROLS STRIP ── */
.lp-controls{background:#0f1b2d;border-top:1px solid rgba(255,255,255,.06);padding:12px 20px;display:flex;align-items:center;gap:14px;flex-shrink:0;}
.lp-prog-wrap{flex:1;}
.lp-prog-lbl{font-size:11px;color:rgba(255,255,255,.3);margin-bottom:5px;}
.lp-prog-track{height:3px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden;}
.lp-prog-fill{height:100%;background:#f5a623;border-radius:2px;transition:width .4s linear;width:0%;}

/* ── META ── */
.lp-meta{padding:18px 24px;border-bottom:1px solid rgba(255,255,255,.05);}
.lp-meta h2{font-size:20px;color:#fff;margin:0 0 8px;}
.lp-meta-row{display:flex;flex-wrap:wrap;gap:14px;font-size:12px;color:rgba(255,255,255,.4);}
.lp-meta-row span{display:flex;align-items:center;gap:5px;}
.lp-meta-row i{color:#f5a623;}
.lp-desc{padding:14px 24px 24px;font-size:13px;color:rgba(255,255,255,.45);line-height:1.8;}

/* ── SIDEBAR ── */
.lp-sidebar{width:320px;flex-shrink:0;background:#0d1626;border-left:1px solid rgba(255,255,255,.05);overflow-y:auto;display:flex;flex-direction:column;}
.lp-sb-head{padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.05);font-family:'Rajdhani',sans-serif;font-size:14px;font-weight:700;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:8px;}
.lp-sb-head i{color:#f5a623;}
.lp-sec{border-bottom:1px solid rgba(255,255,255,.03);}
.lp-sec-hd{display:flex;align-items:center;gap:8px;padding:10px 16px;cursor:pointer;background:rgba(255,255,255,.02);user-select:none;}
.lp-sec-hd:hover{background:rgba(255,255,255,.04);}
.lp-sec-arr{font-size:9px;color:rgba(255,255,255,.25);transition:transform .2s;flex-shrink:0;}
.lp-sec-hd.open .lp-sec-arr{transform:rotate(90deg);}
.lp-sec-title{font-size:12px;font-weight:600;color:rgba(255,255,255,.6);flex:1;line-height:1.3;}
.lp-sec-cnt{font-size:10px;color:rgba(255,255,255,.2);white-space:nowrap;}
.lp-sec-body{display:none;}
.lp-sec-body.open{display:block;}
.lp-lrow{display:flex;align-items:center;gap:10px;padding:9px 16px 9px 26px;text-decoration:none;color:inherit;transition:background .15s;}
.lp-lrow:hover{background:rgba(255,255,255,.03);}
.lp-lrow.active{background:rgba(245,166,35,.07);border-left:3px solid #f5a623;padding-left:23px;}
.lp-lrow.active .lp-lrow-title{color:#f5a623;}
.lp-lrow-ico{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:8px;flex-shrink:0;}
.lp-lrow-ico.yt{background:rgba(229,57,53,.2);color:#e53935;}
.lp-lrow-ico.up{background:rgba(26,86,219,.15);color:#5c9aff;}
.lp-lrow-ico.cur{background:#f5a623;color:#0f1b2d;}
.lp-lrow-info{flex:1;min-width:0;}
.lp-lrow-title{font-size:12px;color:rgba(255,255,255,.65);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3;}
.lp-lrow-dur{font-size:10px;color:rgba(255,255,255,.25);margin-top:1px;}

/* ── DEVTOOLS WARNING ── */
.lp-dt-warn{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.95);align-items:center;justify-content:center;flex-direction:column;gap:16px;text-align:center;padding:30px;}
.lp-dt-warn.show{display:flex;}
.lp-dt-warn i{font-size:56px;color:#f5a623;opacity:.8;}
.lp-dt-warn h3{font-family:'Rajdhani',sans-serif;font-size:24px;color:#fff;margin:0;}
.lp-dt-warn p{font-size:14px;color:rgba(255,255,255,.5);margin:0;max-width:360px;}

/* ── RESPONSIVE ── */
@media(max-width:768px){
    .lp-body{flex-direction:column;height:auto;overflow:visible;}
    .lp-main{overflow:visible;}
    .lp-sidebar{width:100%;max-height:50vh;border-left:none;border-top:1px solid rgba(255,255,255,.05);}
    .lp-video-area{max-height:56vw;}
    .lp-bar-sub,.lp-bar-back span{display:none;}
}
</style>

{{-- DevTools warning --}}
<div class="lp-dt-warn" id="lpDtWarn">
    <i class="fas fa-shield-alt"></i>
    <h3>Developer Tools Detected</h3>
    <p>Please close Developer Tools to continue watching.</p>
</div>

<div class="lp">

    {{-- TOP BAR --}}
    <div class="lp-bar">
        <a href="{{ route('courses.detail', $course->slug) }}" class="lp-bar-back">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Course</span>
        </a>
        <div style="flex:1;min-width:0;">
            <div class="lp-bar-title">{{ $lesson->title }}</div>
            <div class="lp-bar-sub">{{ $course->title }}</div>
        </div>
        @if($prevLesson)
        <a href="{{ route('video.player', $prevLesson) }}" class="lp-nav-btn">
            <i class="fas fa-step-backward"></i> Prev
        </a>
        @endif
        @if($nextLesson)
        <a href="{{ route('video.player', $nextLesson) }}" class="lp-nav-btn next">
            Next <i class="fas fa-step-forward"></i>
        </a>
        @endif
    </div>

    <div class="lp-body">

        {{-- VIDEO COLUMN --}}
        <div class="lp-main">

            {{-- VIDEO AREA --}}
            <div class="lp-video-area {{ $isUpload ? 'paused' : '' }}" id="lpArea">

                @if($isUpload)
                {{-- ══ UPLOADED VIDEO — secure token-based stream ══ --}}

                {{-- Badge --}}
                <div class="lp-type-badge upload">
                    <i class="fas fa-video"></i> Uploaded Video
                </div>

                {{-- Loading state --}}
                <div class="lp-state" id="lpState">
                    <div class="lp-spinner"></div>
                    <div class="lp-state-txt">Preparing secure stream…</div>
                </div>

                {{-- Shield blocks right-click on pause --}}
                <div class="lp-shield" id="lpShield" oncontextmenu="return false"></div>

                <video id="lpVideo"
                       playsinline
                       controlsList="nodownload noremoteplayback"
                       disablePictureInPicture
                       oncontextmenu="return false"
                       style="width:100%;height:100%;object-fit:contain;">
                </video>

                @elseif($isYoutube)
                {{-- ══ YOUTUBE VIDEO — embedded iframe ══ --}}

                {{-- Badge --}}
                <div class="lp-type-badge yt">
                    <i class="fab fa-youtube"></i> YouTube
                </div>

                <div class="lp-yt-wrap">
                    <iframe
                        src="https://www.youtube-nocookie.com/embed/{{ $ytEmbedId }}?rel=0&modestbranding=1&autoplay=0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        title="{{ $lesson->title }}">
                    </iframe>
                </div>

                @else
                {{-- ══ NO VIDEO ══ --}}
                <div class="lp-state">
                    <i class="fas fa-video-slash lp-state-icon"></i>
                    <div class="lp-state-txt">No video has been added to this lesson yet.</div>
                </div>
                @endif

            </div>

            {{-- CONTROLS (only meaningful for upload type) --}}
            <div class="lp-controls">
                @if($prevLesson)
                <a href="{{ route('video.player', $prevLesson) }}" class="lp-nav-btn">
                    <i class="fas fa-arrow-left"></i> Previous
                </a>
                @endif
                <div class="lp-prog-wrap">
                    @if($isUpload)
                    <div class="lp-prog-lbl" id="lpLbl">Loading…</div>
                    <div class="lp-prog-track">
                        <div class="lp-prog-fill" id="lpFill"></div>
                    </div>
                    @elseif($isYoutube)
                    <div class="lp-prog-lbl" style="color:rgba(255,255,255,.3);">
                        <i class="fab fa-youtube" style="color:#e53935;margin-right:5px;"></i>
                        Playing via YouTube
                    </div>
                    @else
                    <div class="lp-prog-lbl" style="color:rgba(255,255,255,.3);">No video</div>
                    @endif
                </div>
                @if($nextLesson)
                <a href="{{ route('video.player', $nextLesson) }}" class="lp-nav-btn next">
                    Next <i class="fas fa-arrow-right"></i>
                </a>
                @endif
            </div>

            {{-- META --}}
            <div class="lp-meta">
                <h2>{{ $lesson->title }}</h2>
                <div class="lp-meta-row">
                    @if($lesson->duration_seconds)
                    <span><i class="fas fa-clock"></i> {{ $lesson->formatted_duration }}</span>
                    @endif
                    @if($section)
                    <span><i class="fas fa-layer-group"></i> {{ $section->title }}</span>
                    @endif
                    <span><i class="fas fa-book-open"></i> {{ $course->title }}</span>
                    {{-- Video type indicator --}}
                    @if($isUpload)
                    <span><i class="fas fa-hdd" style="color:#5c9aff;"></i> Uploaded Video</span>
                    @elseif($isYoutube)
                    <span><i class="fab fa-youtube" style="color:#e53935;"></i> YouTube Video</span>
                    @endif
                </div>
            </div>

            @if($lesson->description)
            <div class="lp-desc">{{ $lesson->description }}</div>
            @endif

        </div>

        {{-- SIDEBAR --}}
        <div class="lp-sidebar">
            <div class="lp-sb-head">
                <i class="fas fa-layer-group"></i> Course Content
            </div>
            @foreach($course->sections as $sec)
            @php $isCurrent = $sec->id === ($section->id ?? null); @endphp
            <div class="lp-sec">
                <div class="lp-sec-hd {{ $isCurrent ? 'open' : '' }}" onclick="lpSec(this)">
                    <i class="fas fa-chevron-right lp-sec-arr"></i>
                    <span class="lp-sec-title">{{ $sec->title }}</span>
                    <span class="lp-sec-cnt">{{ $sec->lessons->count() }}</span>
                </div>
                <div class="lp-sec-body {{ $isCurrent ? 'open' : '' }}">
                    @foreach($sec->lessons->sortBy('sort_order') as $l)
                    @php
                        // Determine icon class per lesson type
                        $lIco = $l->id === $lesson->id ? 'cur' : ($l->video_type === 'youtube' ? 'yt' : 'up');
                        $lIcon = $l->id === $lesson->id ? 'fas fa-play' : ($l->video_type === 'youtube' ? 'fab fa-youtube' : 'fas fa-play');
                    @endphp
                    <a href="{{ route('video.player', $l) }}"
                       class="lp-lrow {{ $l->id === $lesson->id ? 'active' : '' }}">
                        <div class="lp-lrow-ico {{ $lIco }}">
                            <i class="{{ $lIcon }}"></i>
                        </div>
                        <div class="lp-lrow-info">
                            <div class="lp-lrow-title">{{ $l->title }}</div>
                            <div class="lp-lrow-dur" style="display:flex;align-items:center;gap:6px;">
                                @if($l->duration_seconds)
                                <span>{{ $l->formatted_duration }}</span>
                                @endif
                                {{-- Small type indicator --}}
                                @if($l->video_type === 'youtube')
                                <span style="background:rgba(229,57,53,.15);color:#e53935;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;">YT</span>
                                @elseif($l->video_type === 'upload' && $l->video_path)
                                <span style="background:rgba(26,86,219,.15);color:#5c9aff;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;">MP4</span>
                                @endif
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

    </div>
</div>

<script>
(function(){
'use strict';

// ── Disable right-click (upload videos only — YT iframe handles its own) ─
@if($isUpload)
document.addEventListener('contextmenu', function(e){ e.preventDefault(); return false; });
@endif

// ── Block inspect shortcuts ──────────────────────────────────────────────
document.addEventListener('keydown', function(e){
    var k = e.key ? e.key.toLowerCase() : '';
    if(e.ctrlKey && ['s','u','p'].includes(k)){ e.preventDefault(); return false; }
    if(e.ctrlKey && e.shiftKey && ['i','j','c'].includes(k)){ e.preventDefault(); return false; }
    if(k === 'f12'){ e.preventDefault(); return false; }
});

// ── DevTools detection (upload only — no point pausing YT embed) ─────────
@if($isUpload)
var dtOpen  = false;
var dtWarn  = document.getElementById('lpDtWarn');
var lpVideo = document.getElementById('lpVideo');

function checkDt(){
    var open = (window.outerWidth - window.innerWidth > 150 || window.outerHeight - window.innerHeight > 150);
    if(open && !dtOpen){
        dtOpen = true;
        if(lpVideo && !lpVideo.paused) lpVideo.pause();
        if(dtWarn) dtWarn.classList.add('show');
    } else if(!open && dtOpen){
        dtOpen = false;
        if(dtWarn) dtWarn.classList.remove('show');
    }
}
setInterval(checkDt, 800);

// Debugger trap
(function trap(){
    try{ (function(){ var d=new Date(); debugger; if(new Date()-d>100){ dtOpen=true; if(lpVideo&&!lpVideo.paused)lpVideo.pause(); } })(); }catch(e){}
    setTimeout(trap, 3000);
})();
@endif

// ── Sidebar section toggle ────────────────────────────────────────────────
window.lpSec = function(hd){
    hd.classList.toggle('open');
    var b = hd.nextElementSibling;
    if(b) b.classList.toggle('open');
};

// ═══════════════════════════════════════════════════════════════
// UPLOADED VIDEO PLAYER (secure token stream)
// Only runs when video_type = 'upload'
// ═══════════════════════════════════════════════════════════════
@if($isUpload)
var lpArea  = document.getElementById('lpArea');
var lpState = document.getElementById('lpState');
var lpFill  = document.getElementById('lpFill');
var lpLbl   = document.getElementById('lpLbl');

function showState(html){
    if(lpState){ lpState.innerHTML = html; lpState.classList.remove('gone'); }
}
function hideState(){
    if(lpState) lpState.classList.add('gone');
}
window.hideState = hideState;

function initPlayer(){
    showState('<div class="lp-spinner"></div><div class="lp-state-txt">Securing stream…</div>');

    fetch('{{ route("video.token", $lesson) }}', {
        method:'POST',
        headers:{
            'Content-Type':'application/json',
            'X-CSRF-TOKEN':'{{ csrf_token() }}',
            'X-Requested-With':'XMLHttpRequest',
        },
        credentials:'same-origin',
    })
    .then(function(r){
        if(r.status===401){ window.location.href='{{ route("user.login") }}'; return null; }
        if(!r.ok) return r.json().then(function(d){ throw new Error(d.error||'Failed to load video'); });
        return r.json();
    })
    .then(function(data){
        if(!data) return;
        loadVideo(data.stream_url);
    })
    .catch(function(err){
        showState(
            '<i class="fas fa-exclamation-triangle" style="font-size:38px;color:#e53935;opacity:.7;display:block;margin-bottom:12px;"></i>'+
            '<div class="lp-state-txt">'+(err.message||'Could not load video.')+'</div>'+
            '<button onclick="initPlayer()" style="margin-top:14px;padding:9px 22px;background:#f5a623;border:none;border-radius:7px;font-weight:700;cursor:pointer;font-family:\'Exo 2\',sans-serif;font-size:13px;">Retry</button>'
        );
    });
}
window.initPlayer = initPlayer;

function loadVideo(streamUrl){
    var video = document.getElementById('lpVideo');
    if(!video) return;

    video.src = streamUrl;
    video.load();

    video.addEventListener('canplay', function onReady(){
        hideState();
        video.removeEventListener('canplay', onReady);
        video.play().catch(function(){
            // Autoplay blocked
            showState(
                '<div onclick="document.getElementById(\'lpVideo\').play();hideState();" '+
                'style="cursor:pointer;width:68px;height:68px;border-radius:50%;background:#f5a623;display:flex;align-items:center;justify-content:center;">'+
                '<i class="fas fa-play" style="font-size:24px;color:#0f1b2d;margin-left:3px;"></i></div>'+
                '<div class="lp-state-txt" style="margin-top:10px;">Click to play</div>'
            );
        });
    }, {once:true});

    video.addEventListener('error', function(){
        showState(
            '<i class="fas fa-exclamation-triangle" style="font-size:38px;color:#e53935;opacity:.7;display:block;margin-bottom:12px;"></i>'+
            '<div class="lp-state-txt">Stream error. <button onclick="initPlayer()" style="background:none;border:none;color:#f5a623;cursor:pointer;font-weight:700;font-size:13px;">Retry</button></div>'
        );
    });

    video.addEventListener('play',  function(){ lpArea.classList.remove('paused'); });
    video.addEventListener('pause', function(){ lpArea.classList.add('paused'); });

    video.addEventListener('timeupdate', function(){
        if(!video.duration) return;
        var pct = (video.currentTime/video.duration)*100;
        if(lpFill) lpFill.style.width = pct.toFixed(1)+'%';
        if(lpLbl)  lpLbl.textContent  = fmt(video.currentTime)+' / '+fmt(video.duration);
    });

    video.addEventListener('ended', function(){
        @if($nextLesson)
        if(lpLbl) lpLbl.textContent = 'Loading next lesson…';
        setTimeout(function(){ window.location.href='{{ route("video.player", $nextLesson) }}'; }, 1500);
        @endif
    });
}

function fmt(s){
    var m=Math.floor(s/60), sec=Math.floor(s%60);
    return m+':'+(sec<10?'0':'')+sec;
}

// Start
initPlayer();
@endif

// ═══════════════════════════════════════════════════════════════
// YOUTUBE VIDEO — nothing special needed, iframe handles it
// Just auto-advance on next lesson if possible
// ═══════════════════════════════════════════════════════════════
@if($isYoutube)
// YouTube iframe API for auto-advance (optional, non-critical)
// The iframe will play normally without any JS
console.log('YouTube lesson loaded: {{ $ytEmbedId }}');
@endif

})();
</script>
@endsection