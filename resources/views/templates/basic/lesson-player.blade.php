{{-- FILE: resources/views/themes/{activeTemplate}/lesson-player.blade.php --}}
@extends($activeTemplate.'layouts.frontend')

@section('content')
<meta http-equiv="Content-Security-Policy"
      content="media-src 'self'; object-src 'none';">
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&family=Exo+2:wght@400;500;600&display=swap" rel="stylesheet">

<style>
*{box-sizing:border-box;}
body{background:#0a0f1a;margin:0;}

/* ── LAYOUT ── */
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
.lp-video-area{position:relative;width:100%;background:#000;aspect-ratio:16/9;max-height:70vh;}

/* Security: overlay stops right-click on video */
.lp-shield{
    position:absolute;inset:0;z-index:5;
    /* pointer-events none = clicks pass through to video controls */
    pointer-events:none;
    background:transparent;
}
/* When paused: enable shield so right-click hits the div not the video */
.lp-video-area.paused .lp-shield{pointer-events:all;}

.lp-video-area video{
    width:100%;height:100%;display:block;background:#000;
    /* browser-level: hides download button in controls */
    -webkit-media-controls-overflow-button:none;
}

/* ── STATE OVERLAY (loading / error) ── */
.lp-state{position:absolute;inset:0;z-index:10;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;}
.lp-state.gone{display:none;}
.lp-spinner{width:46px;height:46px;border:4px solid rgba(255,255,255,.08);border-top-color:#f5a623;border-radius:50%;animation:lspin .8s linear infinite;}
@keyframes lspin{to{transform:rotate(360deg);}}
.lp-state-txt{font-size:13px;color:rgba(255,255,255,.5);}
.lp-state-icon{font-size:48px;opacity:.25;}

/* ── CONTROLS STRIP ── */
.lp-controls{background:#0f1b2d;border-top:1px solid rgba(255,255,255,.06);padding:12px 20px;display:flex;align-items:center;gap:14px;}
.lp-prog-wrap{flex:1;}
.lp-prog-lbl{font-size:11px;color:rgba(255,255,255,.3);margin-bottom:5px;}
.lp-prog-track{height:3px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden;}
.lp-prog-fill{height:100%;background:#f5a623;border-radius:2px;transition:width .4s linear;width:0%;}

/* ── LESSON META ── */
.lp-meta{padding:18px 24px;border-bottom:1px solid rgba(255,255,255,.05);}
.lp-meta h2{font-size:20px;color:#fff;margin:0 0 8px;}
.lp-meta-row{display:flex;flex-wrap:wrap;gap:14px;font-size:12px;color:rgba(255,255,255,.4);}
.lp-meta-row span{display:flex;align-items:center;gap:5px;}
.lp-meta-row i{color:#f5a623;}
.lp-desc{padding:14px 24px 24px;font-size:13px;color:rgba(255,255,255,.45);line-height:1.8;}

/* ── YOUTUBE EMBED ── */
.lp-yt{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;}
.lp-yt iframe{position:absolute;inset:0;width:100%;height:100%;border:none;}

/* ── SIDEBAR ── */
.lp-sidebar{width:320px;flex-shrink:0;background:#0d1626;border-left:1px solid rgba(255,255,255,.05);overflow-y:auto;display:flex;flex-direction:column;}
.lp-sb-head{padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.05);font-family:'Rajdhani',sans-serif;font-size:14px;font-weight:700;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:8px;}
.lp-sb-head i{color:#f5a623;}

/* Section */
.lp-sec{border-bottom:1px solid rgba(255,255,255,.03);}
.lp-sec-hd{display:flex;align-items:center;gap:8px;padding:10px 16px;cursor:pointer;background:rgba(255,255,255,.02);user-select:none;}
.lp-sec-hd:hover{background:rgba(255,255,255,.04);}
.lp-sec-arr{font-size:9px;color:rgba(255,255,255,.25);transition:transform .2s;flex-shrink:0;}
.lp-sec-hd.open .lp-sec-arr{transform:rotate(90deg);}
.lp-sec-title{font-size:12px;font-weight:600;color:rgba(255,255,255,.6);flex:1;line-height:1.3;}
.lp-sec-cnt{font-size:10px;color:rgba(255,255,255,.2);white-space:nowrap;}
.lp-sec-body{display:none;}
.lp-sec-body.open{display:block;}

/* Lesson row */
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

/* ── DEVTOOLS WARNING OVERLAY ── */
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

{{-- DevTools warning overlay --}}
<div class="lp-dt-warn" id="lpDtWarn">
    <i class="fas fa-shield-alt"></i>
    <h3>Developer Tools Detected</h3>
    <p>Please close Developer Tools to continue watching. Video playback is paused for security.</p>
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
            <div class="lp-video-area paused" id="lpArea">

                @if($lesson->video_type === 'upload')

                {{-- Loading state --}}
                <div class="lp-state" id="lpState">
                    <div class="lp-spinner"></div>
                    <div class="lp-state-txt">Preparing secure stream…</div>
                </div>

                {{-- Shield div — blocks right-click when paused --}}
                <div class="lp-shield" id="lpShield"
                     oncontextmenu="return false"></div>

                <video id="lpVideo"
                       playsinline
                       controlsList="nodownload noremoteplayback nofullscreen"
                       disablePictureInPicture
                       oncontextmenu="return false"
                       style="width:100%;height:100%;object-fit:contain;">
                </video>

                @elseif($lesson->video_type === 'youtube')

                <div class="lp-yt" style="width:100%;height:100%;padding-bottom:0;position:absolute;inset:0;">
                    <iframe
                        src="https://www.youtube.com/embed/{{ $lesson->youtube_embed_id }}?rel=0&modestbranding=1"
                        allow="accelerometer; autoplay; encrypted-media; gyroscope"
                        allowfullscreen
                        style="position:absolute;inset:0;width:100%;height:100%;border:none;">
                    </iframe>
                </div>

                @else
                <div class="lp-state">
                    <i class="fas fa-video-slash lp-state-icon"></i>
                    <div class="lp-state-txt">No video for this lesson.</div>
                </div>
                @endif

            </div>

            {{-- CONTROLS --}}
            <div class="lp-controls">
                @if($prevLesson)
                <a href="{{ route('video.player', $prevLesson) }}" class="lp-nav-btn">
                    <i class="fas fa-arrow-left"></i> Previous
                </a>
                @endif
                <div class="lp-prog-wrap">
                    <div class="lp-prog-lbl" id="lpLbl">Loading…</div>
                    <div class="lp-prog-track">
                        <div class="lp-prog-fill" id="lpFill"></div>
                    </div>
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
                    <a href="{{ route('video.player', $l) }}"
                       class="lp-lrow {{ $l->id === $lesson->id ? 'active' : '' }}">
                        <div class="lp-lrow-ico {{ $l->id === $lesson->id ? 'cur' : ($l->video_type === 'youtube' ? 'yt' : 'up') }}">
                            <i class="{{ $l->id === $lesson->id ? 'fas fa-play' : ($l->video_type === 'youtube' ? 'fab fa-youtube' : 'fas fa-play') }}"></i>
                        </div>
                        <div class="lp-lrow-info">
                            <div class="lp-lrow-title">{{ $l->title }}</div>
                            @if($l->duration_seconds)
                            <div class="lp-lrow-dur">{{ $l->formatted_duration }}</div>
                            @endif
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

// ── 1. Disable right-click everywhere on this page ───────────────────────
document.addEventListener('contextmenu', function(e){ e.preventDefault(); return false; });

// ── 2. Block keyboard shortcuts used to save / inspect ───────────────────
document.addEventListener('keydown', function(e){
    var k = e.key ? e.key.toLowerCase() : '';
    // Ctrl+S, Ctrl+U, Ctrl+P (print/save), Ctrl+Shift+I/J/C (devtools)
    if(e.ctrlKey && ['s','u','p'].includes(k)){ e.preventDefault(); return false; }
    if(e.ctrlKey && e.shiftKey && ['i','j','c'].includes(k)){ e.preventDefault(); return false; }
    if(k === 'f12'){ e.preventDefault(); return false; }
});

// ── 3. DevTools size heuristic — pause video if open ─────────────────────
var dtOpen   = false;
var dtWarn   = document.getElementById('lpDtWarn');
var lpVideo  = document.getElementById('lpVideo');

function checkDevTools(){
    var wDiff = window.outerWidth  - window.innerWidth;
    var hDiff = window.outerHeight - window.innerHeight;
    var open  = (wDiff > 150 || hDiff > 150);
    if(open && !dtOpen){
        dtOpen = true;
        if(lpVideo && !lpVideo.paused) lpVideo.pause();
        if(dtWarn) dtWarn.classList.add('show');
    } else if(!open && dtOpen){
        dtOpen = false;
        if(dtWarn) dtWarn.classList.remove('show');
    }
}
setInterval(checkDevTools, 800);

// ── 4. Debugger trap — makes devtools console unusable ───────────────────
// When someone opens devtools console, this runs continuously
// making the console freeze. Doesn't affect normal users at all.
(function devTrap(){
    try{
        (function(){ var d = new Date(); debugger; if(new Date()-d > 100){ dtOpen = true; if(lpVideo && !lpVideo.paused) lpVideo.pause(); } })();
    } catch(e){}
    setTimeout(devTrap, 3000);
})();

// ── 5. Sidebar toggle ────────────────────────────────────────────────────
window.lpSec = function(hd){
    hd.classList.toggle('open');
    var body = hd.nextElementSibling;
    if(body) body.classList.toggle('open');
};

@if($lesson->video_type === 'upload')
// ── 6. Secure video player ────────────────────────────────────────────────
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

// STEP 1: Get a signed stream token from server
function initPlayer(){
    showState('<div class="lp-spinner"></div><div class="lp-state-txt">Securing stream…</div>');

    fetch('{{ route("video.token", $lesson) }}', {
        method : 'POST',
        headers: {
            'Content-Type' : 'application/json',
            'X-CSRF-TOKEN' : '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
    .then(function(r){
        if(r.status === 401){ window.location.href = '{{ route("user.login") }}'; return null; }
        if(!r.ok) return r.json().then(function(d){ throw new Error(d.error || 'Failed'); });
        return r.json();
    })
    .then(function(data){
        if(!data) return;
        // STEP 2: Feed the signed stream URL to the video element
        // The URL contains our token — no real file path ever visible
        loadVideo(data.stream_url, data.duration);
    })
    .catch(function(err){
        showState(
            '<i class="fas fa-exclamation-triangle lp-state-icon" style="color:#e53935;"></i>' +
            '<div class="lp-state-txt">' + (err.message || 'Could not load video.') + '</div>' +
            '<button onclick="initPlayer()" style="margin-top:10px;padding:8px 20px;background:#f5a623;border:none;border-radius:6px;font-weight:700;cursor:pointer;font-family:\'Exo 2\',sans-serif;">Retry</button>'
        );
    });
}

// STEP 2: Attach stream URL to <video> element
// Browser will automatically use byte-range requests (206 responses)
// Each range request hits our controller which re-verifies token + IP
function loadVideo(streamUrl, duration){
    var video = document.getElementById('lpVideo');
    if(!video) return;

    // Set source — browser sends Range: bytes=X-Y requests automatically
    video.src = streamUrl;
    video.load();

    video.addEventListener('canplay', function onReady(){
        hideState();
        video.removeEventListener('canplay', onReady);
        video.play().catch(function(){
            // Autoplay blocked — show play button
            showState(
                '<div onclick="document.getElementById(\'lpVideo\').play();hideState();" '+
                'style="cursor:pointer;width:68px;height:68px;border-radius:50%;background:#f5a623;display:flex;align-items:center;justify-content:center;">' +
                '<i class="fas fa-play" style="font-size:24px;color:#0f1b2d;margin-left:3px;"></i></div>' +
                '<div class="lp-state-txt" style="margin-top:10px;">Click to play</div>'
            );
        });
    }, {once: true});

    video.addEventListener('error', function(){
        showState(
            '<i class="fas fa-exclamation-triangle lp-state-icon" style="color:#e53935;"></i>'+
            '<div class="lp-state-txt">Stream error. <button onclick="initPlayer()" '+
            'style="background:none;border:none;color:#f5a623;cursor:pointer;font-weight:700;">Retry</button></div>'
        );
    });

    // ── Play/pause shield toggle ─────────────────────────────────────────
    video.addEventListener('play',  function(){ lpArea.classList.remove('paused'); });
    video.addEventListener('pause', function(){ lpArea.classList.add('paused'); });

    // ── Progress bar ─────────────────────────────────────────────────────
    video.addEventListener('timeupdate', function(){
        if(!video.duration) return;
        var pct = (video.currentTime / video.duration) * 100;
        if(lpFill) lpFill.style.width = pct.toFixed(1) + '%';
        if(lpLbl)  lpLbl.textContent  = fmt(video.currentTime) + ' / ' + fmt(video.duration);
    });

    // ── Auto-advance ─────────────────────────────────────────────────────
    video.addEventListener('ended', function(){
        @if($nextLesson)
        if(lpLbl) lpLbl.textContent = 'Loading next lesson…';
        setTimeout(function(){ window.location.href = '{{ route("video.player", $nextLesson) }}'; }, 1500);
        @endif
    });

    // ── Token refresh: if 403 during playback, re-issue token ────────────
    // This handles the edge case where token expires mid-long-video
    video.addEventListener('stalled', function(){
        var savedTime = video.currentTime;
        fetch('{{ route("video.token", $lesson) }}', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'X-Requested-With':'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(data && data.stream_url){
                video.src = data.stream_url;
                video.load();
                video.currentTime = savedTime;
                video.play().catch(function(){});
            }
        }).catch(function(){});
    });
}

window.initPlayer = initPlayer;
window.hideState  = function(){ if(lpState) lpState.classList.add('gone'); };

// Start player
initPlayer();
@endif

})();
</script>
@endsection