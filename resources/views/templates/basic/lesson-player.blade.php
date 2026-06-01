{{-- FILE: resources/views/themes/{activeTemplate}/lesson-player.blade.php --}}
@extends($activeTemplate.'layouts.frontend')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&family=Exo+2:wght@400;500;600&display=swap" rel="stylesheet">

@php
    $ytEmbedId = null;
    if ($lesson->video_type === 'youtube' && $lesson->video_url) {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_\-]{11})/', $lesson->video_url, $m)) {
            $ytEmbedId = $m[1];
        }
    }
    $isUpload  = $lesson->video_type === 'upload' && $lesson->video_path;
    $isYoutube = $lesson->video_type === 'youtube' && $ytEmbedId;
@endphp

<style>
*{box-sizing:border-box;}
body{background:#0a0f1a;margin:0;}
.lp{font-family:'Exo 2',sans-serif;background:#0a0f1a;min-height:100vh;color:#e0e6f0;display:flex;flex-direction:column;}
.lp h1,.lp h2,.lp h3,.lp h4{font-family:'Rajdhani',sans-serif;}
.lp-bar{background:#0f1b2d;border-bottom:1px solid rgba(255,255,255,.08);padding:0 20px;height:52px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:200;}
.lp-bar-back{color:rgba(255,255,255,.55);font-size:13px;display:flex;align-items:center;gap:6px;text-decoration:none;transition:color .2s;white-space:nowrap;}
.lp-bar-back:hover{color:#7DFF00;}
.lp-bar-title{font-family:'Rajdhani',sans-serif;font-size:15px;font-weight:700;color:#fff;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.lp-bar-sub{font-size:11px;color:rgba(255,255,255,.35);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;}
.lp-nav-btn{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.7);padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;text-decoration:none;transition:all .2s;font-family:'Exo 2',sans-serif;white-space:nowrap;}
.lp-nav-btn:hover{background:rgba(245,166,35,.15);border-color:#7DFF00;color:#7DFF00;}
.lp-nav-btn.next{background:#7DFF00;color:#0f1b2d;border-color:#7DFF00;}
.lp-nav-btn.next:hover{background:#d4890e;}
.lp-body{display:flex;flex:1;height:calc(100vh - 52px);overflow:hidden;}
.lp-main{flex:1;display:flex;flex-direction:column;overflow-y:auto;min-width:0;}
.lp-video-area{position:relative;width:100%;background:#000;aspect-ratio:16/9;max-height:70vh;flex-shrink:0;}
.lp-shield{position:absolute;inset:0;z-index:5;pointer-events:none;background:transparent;}
.lp-video-area.paused .lp-shield{pointer-events:all;}
.lp-video-area video{width:100%;height:100%;display:block;background:#000;object-fit:contain;}
.lp-yt-wrap{position:absolute;inset:0;width:100%;height:100%;}
.lp-yt-wrap iframe{width:100%;height:100%;border:none;display:block;}
.lp-state{position:absolute;inset:0;z-index:10;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;}
.lp-state.gone{display:none;}
.lp-spinner{width:46px;height:46px;border:4px solid rgba(255,255,255,.08);border-top-color:#7DFF00;border-radius:50%;animation:lspin .8s linear infinite;}
@keyframes lspin{to{transform:rotate(360deg);}}
.lp-state-txt{font-size:13px;color:rgba(255,255,255,.5);text-align:center;padding:0 20px;}
.lp-state-icon{font-size:48px;opacity:.25;}
.lp-type-badge{position:absolute;top:10px;left:10px;z-index:20;font-size:10px;font-weight:700;padding:3px 10px;border-radius:4px;letter-spacing:.05em;text-transform:uppercase;display:flex;align-items:center;gap:4px;}
.lp-type-badge.yt{background:rgba(229,57,53,.85);color:#fff;}
.lp-type-badge.upload{background:rgba(26,86,219,.85);color:#fff;}

/* CUSTOM PLAYER CONTROLS */
.lp-player-controls{position:absolute;bottom:0;left:0;right:0;z-index:15;background:linear-gradient(transparent,rgba(0,0,0,.88));padding:36px 16px 14px;opacity:0;transition:opacity .3s;pointer-events:none;}
.lp-video-area:hover .lp-player-controls,.lp-video-area.paused .lp-player-controls{opacity:1;pointer-events:all;}
.lp-seek-wrap{position:relative;height:16px;display:flex;align-items:center;cursor:pointer;margin-bottom:8px;}
.lp-seek-track{position:absolute;inset:6px 0;background:rgba(255,255,255,.2);border-radius:3px;pointer-events:none;}
.lp-seek-buf{position:absolute;top:6px;bottom:6px;left:0;background:rgba(255,255,255,.3);border-radius:3px;pointer-events:none;}
.lp-seek-fill{position:absolute;top:6px;bottom:6px;left:0;background:#7DFF00;border-radius:3px;pointer-events:none;transition:width .1s;}
.lp-seek-thumb{position:absolute;top:50%;width:14px;height:14px;background:#7DFF00;border-radius:50%;transform:translate(-50%,-50%);pointer-events:none;box-shadow:0 0 4px rgba(0,0,0,.5);}
.lp-ctrl-row{display:flex;align-items:center;gap:8px;}
.lp-ctrl-btn{background:none;border:none;color:#fff;font-size:18px;cursor:pointer;padding:5px 7px;border-radius:5px;transition:all .15s;display:flex;align-items:center;justify-content:center;line-height:1;flex-shrink:0;}
.lp-ctrl-btn:hover{color:#7DFF00;background:rgba(255,255,255,.1);}
.lp-skip-btn{display:flex;flex-direction:column;align-items:center;gap:1px;}
.lp-skip-btn i{font-size:14px;}
.lp-skip-lbl{font-size:8px;font-weight:700;font-family:'Exo 2',sans-serif;color:rgba(255,255,255,.8);}
.lp-vol-row{display:flex;align-items:center;gap:5px;}
.lp-vol-slide{width:65px;height:3px;-webkit-appearance:none;appearance:none;background:rgba(255,255,255,.3);border-radius:2px;cursor:pointer;outline:none;}
.lp-vol-slide::-webkit-slider-thumb{-webkit-appearance:none;width:11px;height:11px;border-radius:50%;background:#7DFF00;cursor:pointer;}
.lp-time-txt{font-size:12px;color:rgba(255,255,255,.85);font-family:'Exo 2',sans-serif;white-space:nowrap;}
.lp-spacer{flex:1;}
.lp-speed{font-size:11px;font-weight:700;font-family:'Exo 2',sans-serif;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);color:#fff;padding:3px 8px;border-radius:4px;cursor:pointer;transition:all .15s;}
.lp-speed:hover{background:rgba(245,166,35,.25);border-color:#7DFF00;color:#7DFF00;}
.lp-skip-flash{position:absolute;top:50%;transform:translateY(-50%);z-index:16;font-size:14px;font-weight:700;font-family:'Rajdhani',sans-serif;color:#fff;background:rgba(0,0,0,.55);padding:8px 14px;border-radius:8px;pointer-events:none;opacity:0;transition:opacity .25s;}
.lp-skip-flash.left{left:12%;}
.lp-skip-flash.right{right:12%;}
.lp-skip-flash.show{opacity:1;}

/* LESSON NAV STRIP */
.lp-controls{background:#0f1b2d;border-top:1px solid rgba(255,255,255,.06);padding:10px 20px;display:flex;align-items:center;gap:14px;flex-shrink:0;}
.lp-prog-lbl{font-size:11px;color:rgba(255,255,255,.3);flex:1;}

/* META */
.lp-meta{padding:18px 24px;border-bottom:1px solid rgba(255,255,255,.05);}
.lp-meta h2{font-size:20px;color:#fff;margin:0 0 8px;}
.lp-meta-row{display:flex;flex-wrap:wrap;gap:14px;font-size:12px;color:rgba(255,255,255,.4);}
.lp-meta-row span{display:flex;align-items:center;gap:5px;}
.lp-meta-row i{color:#7DFF00;}
.lp-desc{padding:14px 24px 24px;font-size:13px;color:rgba(255,255,255,.45);line-height:1.8;}

/* SIDEBAR */
.lp-sidebar{width:320px;flex-shrink:0;background:#0d1626;border-left:1px solid rgba(255,255,255,.05);overflow-y:auto;display:flex;flex-direction:column;}
.lp-sb-head{padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.05);font-family:'Rajdhani',sans-serif;font-size:14px;font-weight:700;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:8px;}
.lp-sb-head i{color:#7DFF00;}
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
.lp-lrow.active{background:rgba(245,166,35,.07);border-left:3px solid #7DFF00;padding-left:23px;}
.lp-lrow.active .lp-lrow-title{color:#7DFF00;}
.lp-lrow-ico{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:8px;flex-shrink:0;}
.lp-lrow-ico.yt{background:rgba(229,57,53,.2);color:#e53935;}
.lp-lrow-ico.up{background:rgba(26,86,219,.15);color:#5c9aff;}
.lp-lrow-ico.cur{background:#7DFF00;color:#0f1b2d;}
.lp-lrow-info{flex:1;min-width:0;}
.lp-lrow-title{font-size:12px;color:rgba(255,255,255,.65);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3;}
.lp-lrow-dur{font-size:10px;color:rgba(255,255,255,.25);margin-top:1px;display:flex;align-items:center;gap:4px;}
.lp-dt-warn{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.95);align-items:center;justify-content:center;flex-direction:column;gap:16px;text-align:center;padding:30px;}
.lp-dt-warn.show{display:flex;}
.lp-dt-warn i{font-size:56px;color:#7DFF00;opacity:.8;}
.lp-dt-warn h3{font-family:'Rajdhani',sans-serif;font-size:24px;color:#fff;margin:0;}
.lp-dt-warn p{font-size:14px;color:rgba(255,255,255,.5);margin:0;max-width:360px;}
@media(max-width:768px){
    .lp-body{flex-direction:column;height:auto;overflow:visible;}
    .lp-main{overflow:visible;}
    .lp-sidebar{width:100%;max-height:50vh;border-left:none;border-top:1px solid rgba(255,255,255,.05);}
    .lp-video-area{max-height:56vw;}
    .lp-bar-sub,.lp-bar-back span{display:none;}
    .lp-vol-row{display:none;}
}
</style>

<div class="lp-dt-warn" id="lpDtWarn">
    <i class="fas fa-shield-alt"></i>
    <h3>Developer Tools Detected</h3>
    <p>Please close Developer Tools to continue watching.</p>
</div>

<div class="lp">
    <div class="lp-bar">
        <a href="{{ route('courses.detail', $course->slug) }}" class="lp-bar-back">
            <i class="fas fa-arrow-left"></i><span>Back to Course</span>
        </a>
        <div style="flex:1;min-width:0;">
            <div class="lp-bar-title">{{ $lesson->title }}</div>
            <div class="lp-bar-sub">{{ $course->title }}</div>
        </div>
        @if($prevLesson)
        <a href="{{ route('video.player', ['lesson' => encrypt($prevLesson->id)]) }}" class="lp-nav-btn">
            <i class="fas fa-step-backward"></i> Prev
        </a>
        @endif
        @if($nextLesson)
        <a href="{{ route('video.player', ['lesson' => encrypt($nextLesson->id)]) }}" class="lp-nav-btn next">
            Next <i class="fas fa-step-forward"></i>
        </a>
        @endif
    </div>

    <div class="lp-body">
        <div class="lp-main">
            <div class="lp-video-area paused" id="lpArea">

                @if($isUpload)
                <div class="lp-type-badge upload"><i class="fas fa-video"></i> Uploaded Video</div>
                <div class="lp-state" id="lpState">
                    <div class="lp-spinner"></div>
                    <div class="lp-state-txt">Preparing secure stream…</div>
                </div>
                <div class="lp-shield" id="lpShield" oncontextmenu="return false"></div>
                <video id="lpVideo" playsinline
                       controlsList="nodownload noremoteplayback"
                       disablePictureInPicture
                       oncontextmenu="return false"
                       style="width:100%;height:100%;object-fit:contain;">
                </video>
                <div class="lp-skip-flash left"  id="sfL">⏪ 10s</div>
                <div class="lp-skip-flash right" id="sfR">10s ⏩</div>

                <div class="lp-player-controls" id="lpPlayerCtrl">
                    {{-- Seek bar --}}
                    <div class="lp-seek-wrap" id="lpSeekWrap">
                        <div class="lp-seek-track"></div>
                        <div class="lp-seek-buf"   id="lpSeekBuf"  style="width:0"></div>
                        <div class="lp-seek-fill"  id="lpSeekFill" style="width:0"></div>
                        <div class="lp-seek-thumb" id="lpSeekThumb" style="left:0"></div>
                    </div>
                    {{-- Controls --}}
                    <div class="lp-ctrl-row">
                        <button class="lp-ctrl-btn" id="lpPlayBtn" onclick="lpTogglePlay()">
                            <i class="fas fa-play" id="lpPlayIco"></i>
                        </button>
                        <button class="lp-ctrl-btn lp-skip-btn" onclick="lpSkip(-10)" title="Back 10s (←)">
                            <i class="fas fa-undo"></i><span class="lp-skip-lbl">10s</span>
                        </button>
                        <button class="lp-ctrl-btn lp-skip-btn" onclick="lpSkip(10)" title="Fwd 10s (→)">
                            <i class="fas fa-redo"></i><span class="lp-skip-lbl">10s</span>
                        </button>
                        <div class="lp-vol-row">
                            <button class="lp-ctrl-btn" id="lpMuteBtn" onclick="lpToggleMute()">
                                <i class="fas fa-volume-up" id="lpVolIco"></i>
                            </button>
                            <input type="range" class="lp-vol-slide" id="lpVolSlide"
                                   min="0" max="1" step="0.05" value="1"
                                   oninput="lpSetVol(this.value)">
                        </div>
                        <span class="lp-time-txt" id="lpTimeTxt">0:00 / 0:00</span>
                        <div class="lp-spacer"></div>
                        <button class="lp-speed" id="lpSpeedBtn" onclick="lpCycleSpeed()">1×</button>
                        <button class="lp-ctrl-btn" onclick="lpToggleFs()" title="Fullscreen (F)">
                            <i class="fas fa-expand" id="lpFsIco"></i>
                        </button>
                    </div>
                </div>

                @elseif($isYoutube)
                <div class="lp-type-badge yt"><i class="fab fa-youtube"></i> YouTube</div>
                <div class="lp-yt-wrap">
                    <iframe
                        src="https://www.youtube-nocookie.com/embed/{{ $ytEmbedId }}?rel=0&modestbranding=1&autoplay=0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen title="{{ $lesson->title }}">
                    </iframe>
                </div>

                @else
                <div class="lp-state">
                    <i class="fas fa-video-slash lp-state-icon"></i>
                    <div class="lp-state-txt">No video has been added to this lesson yet.</div>
                </div>
                @endif
            </div>

            <div class="lp-controls">
                @if($prevLesson)
                <a href="{{ route('video.player', ['lesson' => encrypt($prevLesson->id)]) }}" class="lp-nav-btn">
                    <i class="fas fa-arrow-left"></i> Previous
                </a>
                @endif
                <div class="lp-prog-lbl" id="lpLbl">
                    @if($isYoutube)<i class="fab fa-youtube" style="color:#e53935;margin-right:5px;"></i>Playing via YouTube
                    @elseif(!$hasVideo)No video
                    @endif
                </div>
                @if($nextLesson)
                <a href="{{ route('video.player', ['lesson' => encrypt($nextLesson->id)]) }}" class="lp-nav-btn next">
                    Next <i class="fas fa-arrow-right"></i>
                </a>
                @endif
            </div>

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
                    @if($isUpload)<span><i class="fas fa-hdd" style="color:#5c9aff;"></i> Uploaded Video</span>
                    @elseif($isYoutube)<span><i class="fab fa-youtube" style="color:#e53935;"></i> YouTube Video</span>@endif
                </div>
            </div>
            @if($lesson->description)
            <div class="lp-desc">{{ $lesson->description }}</div>
            @endif
        </div>

        <div class="lp-sidebar">
            <div class="lp-sb-head"><i class="fas fa-layer-group"></i> Course Content</div>
            @foreach($course->sections as $sec)
            @php $isCur = $sec->id === ($section->id ?? null); @endphp
            <div class="lp-sec">
                <div class="lp-sec-hd {{ $isCur?'open':'' }}" onclick="lpSec(this)">
                    <i class="fas fa-chevron-right lp-sec-arr"></i>
                    <span class="lp-sec-title">{{ $sec->title }}</span>
                    <span class="lp-sec-cnt">{{ $sec->lessons->count() }}</span>
                </div>
                <div class="lp-sec-body {{ $isCur?'open':'' }}">
                    @foreach($sec->lessons->sortBy('sort_order') as $l)
                    @php
                        $ico  = $l->id===$lesson->id?'cur':($l->video_type==='youtube'?'yt':'up');
                        $icn  = $l->id===$lesson->id?'fas fa-play':($l->video_type==='youtube'?'fab fa-youtube':'fas fa-play');
                    @endphp
                    <a href="{{ route('video.player', ['lesson' => encrypt($l->id)]) }}"
                       class="lp-lrow {{ $l->id===$lesson->id?'active':'' }}">
                        <div class="lp-lrow-ico {{ $ico }}"><i class="{{ $icn }}"></i></div>
                        <div class="lp-lrow-info">
                            <div class="lp-lrow-title">{{ $l->title }}</div>
                            <div class="lp-lrow-dur">
                                @if($l->duration_seconds)<span>{{ $l->formatted_duration }}</span>@endif
                                @if($l->video_type==='youtube')
                                <span style="background:rgba(229,57,53,.15);color:#e53935;font-size:9px;font-weight:700;padding:1px 5px;border-radius:3px;">YT</span>
                                @elseif($l->video_type==='upload'&&$l->video_path)
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
@if($isUpload)
document.addEventListener('contextmenu',function(e){e.preventDefault();return false;});
@endif
document.addEventListener('keydown',function(e){
    var k=e.key?e.key.toLowerCase():'';
    if(e.ctrlKey&&['s','u','p'].includes(k)){e.preventDefault();return false;}
    if(e.ctrlKey&&e.shiftKey&&['i','j','c'].includes(k)){e.preventDefault();return false;}
    if(k==='f12'){e.preventDefault();return false;}
});
@if($isUpload)
var dtOpen=false,dtWarn=document.getElementById('lpDtWarn');
function chkDt(){
    var o=window.outerWidth-window.innerWidth>150||window.outerHeight-window.innerHeight>150;
    if(o&&!dtOpen){dtOpen=true;if(vid&&!vid.paused)vid.pause();dtWarn&&dtWarn.classList.add('show');}
    else if(!o&&dtOpen){dtOpen=false;dtWarn&&dtWarn.classList.remove('show');}
}
setInterval(chkDt,800);
@endif
window.lpSec=function(hd){hd.classList.toggle('open');var b=hd.nextElementSibling;if(b)b.classList.toggle('open');};

@if($isUpload)
var vid      = document.getElementById('lpVideo');
var lpArea   = document.getElementById('lpArea');
var lpState  = document.getElementById('lpState');
var lpLbl    = document.getElementById('lpLbl');
var seekWrap = document.getElementById('lpSeekWrap');
var seekFill = document.getElementById('lpSeekFill');
var seekBuf  = document.getElementById('lpSeekBuf');
var seekThumb= document.getElementById('lpSeekThumb');
var playIco  = document.getElementById('lpPlayIco');
var volIco   = document.getElementById('lpVolIco');
var volSlide = document.getElementById('lpVolSlide');
var timeEl   = document.getElementById('lpTimeTxt');
var speedBtn = document.getElementById('lpSpeedBtn');
var fsIco    = document.getElementById('lpFsIco');
var sfL      = document.getElementById('sfL');
var sfR      = document.getElementById('sfR');
var speeds=[0.5,0.75,1,1.25,1.5,1.75,2],spIdx=2;

function showState(h){if(lpState){lpState.innerHTML=h;lpState.classList.remove('gone');}}
function hideState(){if(lpState)lpState.classList.add('gone');}
window.hideState=hideState;

function initPlayer(){
    showState('<div class="lp-spinner"></div><div class="lp-state-txt">Preparing secure stream…</div>');
    fetch('{{ route("video.token",["lesson"=>encrypt($lesson->id)]) }}',{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','X-Requested-With':'XMLHttpRequest'},
        credentials:'same-origin'
    })
    .then(function(r){
        if(r.status===401){window.location.href='{{ route("user.login") }}';return null;}
        if(!r.ok)return r.json().then(function(d){throw new Error(d.error||'Failed');});
        return r.json();
    })
    .then(function(d){if(d)loadVideo(d.stream_url);})
    .catch(function(e){
        showState('<i class="fas fa-exclamation-triangle" style="font-size:36px;color:#e53935;opacity:.7;display:block;margin-bottom:12px;"></i>'+
            '<div class="lp-state-txt">'+(e.message||'Could not load video.')+'</div>'+
            '<button onclick="initPlayer()" style="margin-top:14px;padding:9px 22px;background:#7DFF00;border:none;border-radius:7px;font-weight:700;cursor:pointer;font-family:\'Exo 2\',sans-serif;">Retry</button>');
    });
}
window.initPlayer=initPlayer;

function loadVideo(url){
    vid.src=url; vid.load();
    vid.addEventListener('canplay',function onR(){
        hideState(); vid.removeEventListener('canplay',onR);
        vid.play().catch(function(){
            showState('<div onclick="vid.play();hideState();" style="cursor:pointer;width:68px;height:68px;border-radius:50%;background:#7DFF00;display:flex;align-items:center;justify-content:center;">'+
                '<i class="fas fa-play" style="font-size:24px;color:#0f1b2d;margin-left:3px;"></i></div>'+
                '<div class="lp-state-txt" style="margin-top:10px;">Click to play</div>');
        });
    },{once:true});
    vid.addEventListener('error',function(){
        showState('<i class="fas fa-exclamation-triangle" style="font-size:36px;color:#e53935;opacity:.7;display:block;margin-bottom:12px;"></i>'+
            '<div class="lp-state-txt">Stream error. <button onclick="initPlayer()" style="background:none;border:none;color:#7DFF00;cursor:pointer;font-weight:700;">Retry</button></div>');
    });
}

window.lpTogglePlay=function(){vid.paused?vid.play():vid.pause();};
vid.addEventListener('play', function(){lpArea.classList.remove('paused');playIco.className='fas fa-pause';});
vid.addEventListener('pause',function(){lpArea.classList.add('paused');  playIco.className='fas fa-play';});

window.lpSkip=function(s){
    vid.currentTime=Math.max(0,Math.min(vid.duration||0,vid.currentTime+s));
    var f=s<0?sfL:sfR; f.classList.add('show');
    clearTimeout(f._t); f._t=setTimeout(function(){f.classList.remove('show');},700);
};

// Seek bar
function seekTo(clientX){
    var r=seekWrap.getBoundingClientRect();
    var p=Math.max(0,Math.min(1,(clientX-r.left)/r.width));
    vid.currentTime=p*(vid.duration||0);
}
var seeking=false;
seekWrap.addEventListener('mousedown',function(e){
    seeking=true; seekTo(e.clientX);
    function mv(e2){if(seeking)seekTo(e2.clientX);}
    function up(){seeking=false;document.removeEventListener('mousemove',mv);document.removeEventListener('mouseup',up);}
    document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up);
});
seekWrap.addEventListener('touchstart',function(e){seekTo(e.touches[0].clientX);},{passive:true});
seekWrap.addEventListener('touchmove', function(e){seekTo(e.touches[0].clientX);},{passive:true});

vid.addEventListener('timeupdate',function(){
    if(!vid.duration)return;
    var p=(vid.currentTime/vid.duration)*100;
    seekFill.style.width=p.toFixed(2)+'%';
    seekThumb.style.left=p.toFixed(2)+'%';
    var t=fmt(vid.currentTime)+' / '+fmt(vid.duration);
    if(timeEl)timeEl.textContent=t;
    if(lpLbl)lpLbl.textContent=t;
});
vid.addEventListener('progress',function(){
    if(!vid.duration||!vid.buffered.length)return;
    seekBuf.style.width=((vid.buffered.end(vid.buffered.length-1)/vid.duration)*100)+'%';
});

window.lpSetVol=function(v){vid.volume=parseFloat(v);vid.muted=parseFloat(v)===0;updVol();};
window.lpToggleMute=function(){vid.muted=!vid.muted;if(!vid.muted&&vid.volume===0)vid.volume=0.5;volSlide.value=vid.muted?0:vid.volume;updVol();};
function updVol(){if(vid.muted||vid.volume===0)volIco.className='fas fa-volume-mute';else if(vid.volume<0.5)volIco.className='fas fa-volume-down';else volIco.className='fas fa-volume-up';}

window.lpCycleSpeed=function(){spIdx=(spIdx+1)%speeds.length;vid.playbackRate=speeds[spIdx];speedBtn.textContent=speeds[spIdx]+'×';};

window.lpToggleFs=function(){
    if(!document.fullscreenElement){lpArea.requestFullscreen&&lpArea.requestFullscreen();fsIco.className='fas fa-compress';}
    else{document.exitFullscreen&&document.exitFullscreen();fsIco.className='fas fa-expand';}
};
document.addEventListener('fullscreenchange',function(){if(!document.fullscreenElement)fsIco.className='fas fa-expand';});

// Keyboard shortcuts
document.addEventListener('keydown',function(e){
    var tag=document.activeElement.tagName;
    if(tag==='INPUT'||tag==='TEXTAREA')return;
    var k=e.key;
    if(k===' '||k==='k'){e.preventDefault();lpTogglePlay();}
    else if(k==='ArrowRight'){e.preventDefault();lpSkip(10);}
    else if(k==='ArrowLeft') {e.preventDefault();lpSkip(-10);}
    else if(k==='ArrowUp')   {e.preventDefault();vid.volume=Math.min(1,vid.volume+0.1);volSlide.value=vid.volume;updVol();}
    else if(k==='ArrowDown') {e.preventDefault();vid.volume=Math.max(0,vid.volume-0.1);volSlide.value=vid.volume;updVol();}
    else if(k==='m'||k==='M'){lpToggleMute();}
    else if(k==='f'||k==='F'){lpToggleFs();}
});

// Double-click left/right to skip
lpArea.addEventListener('dblclick',function(e){
    var r=lpArea.getBoundingClientRect();
    lpSkip(e.clientX<r.left+r.width/2?-10:10);
});

vid.addEventListener('ended',function(){
    @if($nextLesson)
    if(lpLbl)lpLbl.textContent='Loading next lesson…';
    setTimeout(function(){window.location.href='{{ route("video.player",["lesson"=>encrypt($nextLesson->id)]) }}';},1500);
    @endif
});

function fmt(s){var m=Math.floor(s/60),sc=Math.floor(s%60);return m+':'+(sc<10?'0':'')+sc;}

initPlayer();
@endif
})();
</script>
@endsection