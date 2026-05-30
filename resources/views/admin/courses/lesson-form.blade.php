{{-- FILE: resources/views/admin/courses/lesson-form.blade.php --}}
@extends('admin.layouts.app')

@push('style')
<style>
.video-drop-zone{border:2px dashed #dee2e6;border-radius:10px;padding:36px 20px;text-align:center;cursor:pointer;transition:all .25s;background:#fafafa;}
.video-drop-zone.dragover{border-color:#1a56db;background:#eff6ff;}
.video-drop-zone .drop-icon{font-size:44px;color:#bbb;display:block;margin-bottom:10px;}
.video-drop-zone .drop-text{font-size:14px;color:#555;}
.video-drop-zone .drop-sub{font-size:12px;color:#aaa;margin-top:4px;}
#videoFileInput{display:none;}
.upload-progress-wrap{display:none;margin-top:14px;}
.upload-progress-bar-outer{height:8px;border-radius:4px;background:#e8e8e8;overflow:hidden;margin-bottom:5px;}
.upload-progress-bar-inner{height:100%;background:linear-gradient(90deg,#1a56db,#0ea5e9);border-radius:4px;transition:width .3s;width:0%;}
.upload-stats{display:flex;justify-content:space-between;font-size:11px;color:#777;}
.upload-status-msg{font-size:13px;font-weight:500;margin-bottom:3px;}
.overview-video-card{border:1px dashed #c3d3f5;border-radius:10px;background:#fafbff;padding:16px;}
.overview-label-badge{display:inline-flex;align-items:center;gap:6px;background:#fff3e0;color:#b45309;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;}
.existing-video-box{border:1px solid #e0e0e0;border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:12px;background:#f8f9fa;margin-bottom:10px;}
.existing-video-icon{font-size:34px;color:#1a56db;}
.existing-video-info{flex:1;}
.existing-video-info strong{display:block;font-size:14px;}
.existing-video-info small{color:#888;}
.duration-human{font-size:12px;color:#6b7280;margin-top:3px;font-weight:500;}
</style>
@endpush

@section('panel')
@php
    $editing = isset($lesson);
    $section = $section ?? ($lesson->section ?? null);
@endphp

{{-- CSRF token for JS --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- These JS globals are read by the upload script --}}
<script>
    window.csrfToken      = '{{ csrf_token() }}';
    window.chunkUploadUrl = '{{ route('admin.courses.video.chunk') }}';
</script>

<form action="{{ $editing
    ? route('admin.courses.lessons.update', [$course, $lesson])
    : route('admin.courses.lessons.store',  [$course, $section]) }}"
    method="POST" enctype="multipart/form-data" id="lessonForm">
    @csrf
    @if($editing) @method('PUT') @endif

    {{-- Hidden: current lesson ID so JS can always send it with upload chunks --}}
    <input type="hidden" id="currentLessonId" value="{{ $editing ? $lesson->id : '' }}">

    <div class="row">
        {{-- LEFT ─────────────────────────────────────────────── --}}
        <div class="col-xl-8">

            {{-- BASIC INFO --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-book me-1"></i> Lesson Details</h5>
                </div>
                <div class="card-body">

                    @if($editing)
                    <div class="mb-3">
                        <label class="form-label">Section</label>
                        <select name="course_section_id" class="form-select">
                            @foreach($sections as $sec)
                                <option value="{{ $sec->id }}"
                                    @selected($sec->id == ($lesson->course_section_id ?? ($section->id ?? null)))>
                                    {{ $sec->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @else
                    <input type="hidden" name="course_section_id" value="{{ $section->id }}">
                    @endif

                    <div class="mb-3">
                        <label class="form-label required">Lesson Title</label>
                        <input type="text" name="title"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $lesson->title ?? '') }}"
                               required placeholder="e.g. Understanding Open Interest">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description / Notes</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Brief notes about this lesson">{{ old('description', $lesson->description ?? '') }}</textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Duration (seconds)</label>
                            <input type="number" name="duration_seconds" class="form-control" min="0"
                                   value="{{ old('duration_seconds', $lesson->duration_seconds ?? '') }}"
                                   placeholder="e.g. 3600 = 1 hr" id="durationInput">
                            <div class="duration-human" id="durationLabel"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" min="0"
                                   value="{{ old('sort_order', $lesson->sort_order ?? 0) }}">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" name="status" value="1"
                                       id="lessonStatus" @checked(old('status', $lesson->status ?? 1))>
                                <label class="form-check-label" for="lessonStatus">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- MAIN LESSON VIDEO --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="las la-film me-1"></i> Lesson Video</h5>
                </div>
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label">Video Source</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="video_type"
                                       value="youtube" id="srcYoutube"
                                       @checked(old('video_type', $lesson->video_type ?? 'youtube') == 'youtube')>
                                <label class="form-check-label" for="srcYoutube">
                                    <i class="lab la-youtube text-danger me-1"></i> YouTube URL
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="video_type"
                                       value="upload" id="srcUpload"
                                       @checked(old('video_type', $lesson->video_type ?? '') == 'upload')>
                                <label class="form-check-label" for="srcUpload">
                                    <i class="las la-upload text--primary me-1"></i> Upload Video
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- YouTube --}}
                    <div id="youtubeSection">
                        <input type="text" name="video_url" class="form-control" id="youtubeUrl"
                               value="{{ old('video_url', $lesson->video_url ?? '') }}"
                               placeholder="https://www.youtube.com/watch?v=...">
                        @if($editing && $lesson->video_type === 'youtube' && $lesson->video_url)
                        @php
                            preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_\-]{11})/', $lesson->video_url, $ytm);
                            $ytPreviewId = $ytm[1] ?? null;
                        @endphp
                        @if($ytPreviewId)
                        <div class="mt-2" style="position:relative;padding-bottom:40%;height:0;overflow:hidden;border-radius:8px;">
                            <iframe src="https://www.youtube.com/embed/{{ $ytPreviewId }}"
                                    style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
                                    allowfullscreen loading="lazy"></iframe>
                        </div>
                        @endif
                        @endif
                    </div>

                    {{-- Upload --}}
                    <div id="uploadSection" style="display:none;">

                        @if($editing && $lesson->video_type === 'upload' && $lesson->video_path)
                        <div class="existing-video-box" id="existingVideoBox">
                            <i class="las la-video existing-video-icon"></i>
                            <div class="existing-video-info">
                                <strong>Uploaded Video</strong>
                                <small>Stored securely — not publicly accessible.
                                    <a href="{{ route('admin.courses.lesson.stream', $lesson) }}"
                                       target="_blank">Preview in new tab</a>
                                </small>
                            </div>
                            <button type="button" class="btn btn--danger btn--sm" id="removeVideoBtn"
                                    data-lesson="{{ $lesson->id }}">
                                <i class="las la-trash"></i> Remove
                            </button>
                        </div>
                        @endif

                        <div class="video-drop-zone" id="videoDropZone">
                            <input type="file" id="videoFileInput"
                                   accept="video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska,.mp4,.webm,.mov,.avi,.mkv">
                            <i class="las la-cloud-upload-alt drop-icon"></i>
                            <div class="drop-text">Drag & drop video here, or
                                <span class="text--primary fw-600">browse</span>
                            </div>
                            <div class="drop-sub">
                                MP4, WEBM, MOV, AVI, MKV — max 2 GB<br>
                                Stored securely (not publicly accessible)
                            </div>
                        </div>

                        <div class="upload-progress-wrap" id="uploadProgressWrap">
                            <div class="upload-status-msg" id="uploadStatusMsg">Uploading…</div>
                            <div class="upload-progress-bar-outer">
                                <div class="upload-progress-bar-inner" id="uploadProgressBar"></div>
                            </div>
                            <div class="upload-stats">
                                <span id="uploadProgressPct">0%</span>
                                <span id="uploadSpeedInfo"></span>
                                <span id="uploadSizeInfo"></span>
                            </div>
                        </div>

                        {{-- Hidden: stores the final_path returned after upload completes --}}
                        <input type="hidden" name="video_path_uploaded" id="videoPathUploaded"
                               value="{{ old('video_path_uploaded', ($lesson->video_path ?? '')) }}">
                    </div>

                </div>
            </div>

            {{-- LESSON OVERVIEW VIDEO --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <h5 class="card-title mb-0"><i class="las la-eye me-1"></i> Lesson Overview Video</h5>
                    <span style="font-size:11px;color:#6b7280;font-weight:400;">— optional teaser shown before purchase</span>
                </div>
                <div class="card-body">
                    <div class="overview-video-card">
                        <div class="overview-label-badge"><i class="las la-star"></i> Free Teaser</div>
                        <p class="text-muted mb-3" style="font-size:13px;">
                            A short YouTube video non-enrolled students can watch to preview this lesson.
                        </p>
                        <div class="mb-3">
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="preview_video_type"
                                           value="none" id="ovNone"
                                           @checked(old('preview_video_type', $lesson->preview_video_type ?? 'none') == 'none')>
                                    <label class="form-check-label" for="ovNone">None</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="preview_video_type"
                                           value="youtube" id="ovYoutube"
                                           @checked(old('preview_video_type', $lesson->preview_video_type ?? '') == 'youtube')>
                                    <label class="form-check-label" for="ovYoutube">
                                        <i class="lab la-youtube text-danger me-1"></i> YouTube URL
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div id="overviewYTSection"
                             style="{{ old('preview_video_type', $lesson->preview_video_type ?? 'none') === 'youtube' ? '' : 'display:none;' }}">
                            <input type="text" name="preview_video_url" class="form-control"
                                   value="{{ old('preview_video_url', $lesson->preview_video_url ?? '') }}"
                                   placeholder="https://www.youtube.com/watch?v=...">
                            <small class="text-muted">Use an unlisted or public YouTube link.</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ATTACHMENT --}}
            <div class="card b-radius--10 mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="las la-paperclip me-1"></i> Attachment
                        <small class="text-muted fw-normal">(optional)</small>
                    </h5>
                </div>
                <div class="card-body">
                    @if($editing && $lesson->attachment)
                    <div class="alert alert--info d-flex align-items-center gap-2 mb-2" style="font-size:13px;">
                        <i class="las la-file-pdf"></i>
                        Existing attachment present. Upload a new file to replace it.
                    </div>
                    @endif
                    <input type="file" name="attachment" class="form-control" accept=".pdf,.docx,.zip">
                    <small class="text-muted">PDF, DOCX, ZIP — max 20 MB</small>
                </div>
            </div>

        </div>

        {{-- RIGHT ────────────────────────────────────────────── --}}
        <div class="col-xl-4">
            <div class="card b-radius--10 mb-3">
                <div class="card-header"><h5 class="card-title mb-0">Publish</h5></div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.courses.curriculum', $course) }}"
                       class="btn btn--secondary btn--sm w-50">Cancel</a>
                    <button type="submit" class="btn btn--primary btn--sm w-50" id="saveLessonBtn">
                        <i class="las la-save"></i> {{ $editing ? 'Update' : 'Add' }} Lesson
                    </button>
                </div>
            </div>

            <div class="card b-radius--10 mb-3">
                <div class="card-header"><h6 class="card-title mb-0">Location</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <span class="text-muted" style="font-size:12px;">Course</span>
                            <strong style="max-width:160px;text-align:right;font-size:12px;word-break:break-word;">
                                {{ Str::limit($course->title, 35) }}
                            </strong>
                        </li>
                        @if($section)
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <span class="text-muted" style="font-size:12px;">Section</span>
                            <strong style="max-width:160px;text-align:right;font-size:12px;word-break:break-word;">
                                {{ Str::limit($section->title, 35) }}
                            </strong>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="card b-radius--10" style="border:1px solid #e8f0fe;background:#fafbff;">
                <div class="card-body" style="font-size:12px;color:#4a5568;line-height:1.6;">
                    <strong style="display:block;margin-bottom:6px;color:#1a56db;">
                        <i class="las la-lightbulb me-1"></i> Tips
                    </strong>
                    <ul class="mb-0 ps-3">
                        <li>Duration is in <strong>seconds</strong> — auto-converts to h/m/s.</li>
                        <li><strong>Overview Video</strong> = free teaser for non-enrolled users.</li>
                        <li><strong>Lesson Video</strong> = full content for enrolled users only.</li>
                        <li>Uploaded videos are stored securely outside public folder.</li>
                        <li>Supported formats: <strong>MP4, WEBM, MOV, AVI, MKV</strong></li>
                        <li>Maximum file size: <strong>2 GB</strong></li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</form>
@endsection

@push('script')
<script>
// VIDEO SOURCE TOGGLE
function toggleVideoSource() {
    var isUpload = document.getElementById('srcUpload').checked;
    document.getElementById('youtubeSection').style.display = isUpload ? 'none' : '';
    document.getElementById('uploadSection').style.display  = isUpload ? '' : 'none';
    document.getElementById('youtubeUrl').required = !isUpload;
}
document.getElementById('srcYoutube').addEventListener('change', toggleVideoSource);
document.getElementById('srcUpload').addEventListener('change', toggleVideoSource);
toggleVideoSource();

// OVERVIEW TOGGLE
function toggleOverviewSource() {
    document.getElementById('overviewYTSection').style.display =
        document.getElementById('ovYoutube').checked ? '' : 'none';
}
document.getElementById('ovNone').addEventListener('change', toggleOverviewSource);
document.getElementById('ovYoutube').addEventListener('change', toggleOverviewSource);

// DURATION LABEL
(function(){
    var inp = document.getElementById('durationInput');
    var lbl = document.getElementById('durationLabel');
    function update(){
        var s = parseInt(inp.value)||0; if(!s){lbl.textContent='';return;}
        var h=Math.floor(s/3600),m=Math.floor((s%3600)/60),sec=s%60,parts=[];
        if(h)parts.push(h+'h'); if(m)parts.push(m+'m'); if(sec||!parts.length)parts.push(sec+'s');
        lbl.textContent='≈ '+parts.join(' ');
    }
    inp.addEventListener('input',update); update();
})();

// CHUNKED UPLOAD
var CHUNK_SIZE=2*1024*1024, uploadId=null, uploadActive=false, startTime=null, bytesUploaded=0;
var dropZone=document.getElementById('videoDropZone');
var fileInput=document.getElementById('videoFileInput');
var progressWrap=document.getElementById('uploadProgressWrap');
var progressBar=document.getElementById('uploadProgressBar');
var progressPct=document.getElementById('uploadProgressPct');
var statusMsg=document.getElementById('uploadStatusMsg');
var speedInfo=document.getElementById('uploadSpeedInfo');
var sizeInfo=document.getElementById('uploadSizeInfo');
var videoPathInput=document.getElementById('videoPathUploaded');
var saveBtn=document.getElementById('saveLessonBtn');

dropZone.addEventListener('dragover',function(e){e.preventDefault();dropZone.classList.add('dragover');});
dropZone.addEventListener('dragleave',function(){dropZone.classList.remove('dragover');});
dropZone.addEventListener('drop',function(e){e.preventDefault();dropZone.classList.remove('dragover');if(e.dataTransfer.files[0])startUpload(e.dataTransfer.files[0]);});
dropZone.addEventListener('click',function(e){if(e.target!==fileInput)fileInput.click();});
fileInput.addEventListener('change',function(){if(this.files[0])startUpload(this.files[0]);});

function uuid(){return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(c){var r=Math.random()*16|0,v=c==='x'?r:(r&0x3|0x8);return v.toString(16);});}
function fmtBytes(b){if(b>=1073741824)return(b/1073741824).toFixed(2)+' GB';if(b>=1048576)return(b/1048576).toFixed(2)+' MB';return(b/1024).toFixed(2)+' KB';}

function startUpload(file){
    if(uploadActive){alert('Upload already in progress.');return;}
    var ext=file.name.split('.').pop().toLowerCase();
    if(!['mp4','webm','mov','avi','mkv'].includes(ext)){
        alert('Unsupported format. Please use MP4, WEBM, MOV, AVI, or MKV.');return;
    }
    uploadId=uuid(); uploadActive=true; startTime=Date.now(); bytesUploaded=0;
    var total=Math.ceil(file.size/CHUNK_SIZE);
    progressWrap.style.display='block'; saveBtn.disabled=true;
    statusMsg.textContent='Starting upload ('+total+' chunks)…';
    sizeInfo.textContent='0 / '+fmtBytes(file.size);
    uploadChunk(file,0,total);
}

function uploadChunk(file,index,total){
    var start=index*CHUNK_SIZE, end=Math.min(start+CHUNK_SIZE,file.size), blob=file.slice(start,end);
    // ── Always send lesson_id ──
    var lessonId=document.getElementById('currentLessonId').value;
    var fd=new FormData();
    fd.append('_token',window.csrfToken);
    fd.append('upload_id',uploadId);
    fd.append('chunk',blob,'chunk');
    fd.append('chunk_index',index);
    fd.append('total_chunks',total);
    fd.append('filename',file.name);
    if(lessonId) fd.append('lesson_id',lessonId);

    var xhr=new XMLHttpRequest();
    xhr.open('POST',window.chunkUploadUrl,true);
    xhr.onload=function(){
        if(xhr.status===200){
            var res; try{res=JSON.parse(xhr.responseText);}catch(e){statusMsg.textContent='❌ Server error';uploadActive=false;saveBtn.disabled=false;return;}
            if(!res.success){statusMsg.textContent='❌ '+(res.message||'Upload error');uploadActive=false;saveBtn.disabled=false;return;}
            bytesUploaded+=(end-start);
            var elapsed=Math.max(0.1,(Date.now()-startTime)/1000),speed=bytesUploaded/elapsed,eta=(file.size-bytesUploaded)/speed;
            speedInfo.textContent=fmtBytes(speed)+'/s'+(eta>1?' · ~'+Math.ceil(eta)+'s left':'');
            sizeInfo.textContent=fmtBytes(bytesUploaded)+' / '+fmtBytes(file.size);
            var pct=res.progress||Math.round(((index+1)/total)*100);
            progressBar.style.width=pct+'%'; progressPct.textContent=pct+'%';
            statusMsg.textContent='Uploading… '+(index+1)+'/'+total+' chunks';
            if(pct>=100||(index+1)>=total){
                uploadActive=false;
                progressBar.style.background='#22c55e';
                statusMsg.textContent='✅ Upload complete!';
                speedInfo.textContent=''; sizeInfo.textContent=fmtBytes(file.size);
                saveBtn.disabled=false;
                if(res.final_path){
                    videoPathInput.value=res.final_path;
                    dropZone.innerHTML='<i class="las la-check-circle" style="font-size:38px;color:#22c55e;display:block;margin-bottom:8px;"></i>'+
                        '<div class="drop-text" style="color:#16a34a;font-weight:600;">Video uploaded successfully</div>'+
                        '<div class="drop-sub">'+file.name+' · '+fmtBytes(file.size)+'</div>';
                }
            } else { uploadChunk(file,index+1,total); }
        } else {uploadActive=false;saveBtn.disabled=false;statusMsg.textContent='❌ HTTP '+xhr.status+' error. Retry.';}
    };
    xhr.onerror=function(){
        if(!xhr._r)xhr._r=0; xhr._r++;
        if(xhr._r<3){statusMsg.textContent='Network error, retrying chunk '+index+'…';setTimeout(function(){uploadChunk(file,index,total);},2000);}
        else{uploadActive=false;saveBtn.disabled=false;statusMsg.textContent='❌ Network error after 3 retries.';}
    };
    xhr.send(fd);
}

// Remove video
var removeBtn=document.getElementById('removeVideoBtn');
if(removeBtn){
    removeBtn.addEventListener('click',function(){
        if(!confirm('Remove this video?'))return;
        fetch('/admin/courses/video/lesson/'+this.dataset.lesson,{
            method:'DELETE',headers:{'X-CSRF-TOKEN':window.csrfToken}
        }).then(function(r){return r.json();}).then(function(d){
            if(d.success){var b=document.getElementById('existingVideoBox');if(b)b.remove();videoPathInput.value='';}
        });
    });
}
</script>
@endpush