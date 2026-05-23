@extends('admin.layouts.app')
@section('panel')

{{-- ── STAT WIDGETS ── --}}
<div class="row mb-3">
    @foreach([
        ['label'=>'All Media',  'key'=>'all',    'color'=>'primary',   'icon'=>'la-photo-video'],
        ['label'=>'Images',     'key'=>'images', 'color'=>'success',   'icon'=>'la-image'],
        ['label'=>'Videos',     'key'=>'videos', 'color'=>'warning',   'icon'=>'la-video'],
    ] as $c)
    <div class="col-xl-4 col-sm-6 mb-3">
        <div class="widget-two style--two box--shadow2 b-radius--10 bg--{{ $c['color'] }}">
            <div class="widget-two__icon b-radius--10"><i class="las {{ $c['icon'] }}"></i></div>
            <div class="widget-two__content">
                <h2 class="text-white">{{ $counts[$c['key']] }}</h2>
                <p class="text-white">{{ $c['label'] }}</p>
            </div>
            <a href="{{ route('admin.media.index', $c['key'] !== 'all' ? ['type' => rtrim($c['key'],'s')] : []) }}"
               class="widget-two__btn">View</a>
        </div>
    </div>
    @endforeach
</div>

<div class="row">
    {{-- ── UPLOAD PANEL ── --}}
    <div class="col-lg-4">
        <div class="card b-radius--10 sticky-top" style="top:80px;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Upload Media</h5>
                <a href="{{ route('admin.media.categories') }}" class="btn btn--secondary btn--sm">
                    <i class="las la-tags"></i> Manage Categories
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.media.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="media_category_id" class="form-select @error('media_category_id') is-invalid @enderror" required>
                            <option value="">— Select Category —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('media_category_id') == $cat->id)>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('media_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}" placeholder="Title shown on hover" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                  rows="2" placeholder="Short description (optional)">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Files <span class="text-danger">*</span></label>
                        <div class="upload-drop-zone" id="dropZone">
                            <input type="file" name="files[]" id="fileInput" multiple
                                   accept="image/*,video/*" class="d-none">
                            <div class="upload-drop-inner" onclick="document.getElementById('fileInput').click()">
                                <i class="las la-cloud-upload-alt" style="font-size:36px;color:#aaa;"></i>
                                <p class="mb-0 mt-1" style="font-size:13px;color:#888;">
                                    Click or drag files here<br>
                                    <small>Images: any size &nbsp;|&nbsp; Videos: max 10 MB</small>
                                </p>
                            </div>
                        </div>
                        <div id="filePreviewList" class="mt-2 d-flex flex-wrap gap-2"></div>
                        @error('files')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @foreach($errors->get('files.*') as $errs)
                            @foreach($errs as $err)
                                <div class="text-danger small">{{ $err }}</div>
                            @endforeach
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn--primary w-100" id="uploadBtn">
                        <i class="las la-upload"></i> Upload
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── ITEMS TABLE ── --}}
    <div class="col-lg-8">
        <div class="card b-radius--10">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">Media Library</h5>
            </div>

            {{-- Filters --}}
            <div class="card-body border-bottom py-2">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label class="form-label small mb-1">Category</label>
                        <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small mb-1">Type</label>
                        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="image" @selected(request('type')=='image')>Images</option>
                            <option value="video" @selected(request('type')=='video')>Videos</option>
                        </select>
                    </div>
                    <div>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Search title..." value="{{ request('search') }}">
                    </div>
                    <button type="submit" class="btn btn--primary btn--sm">Filter</button>
                    <a href="{{ route('admin.media.index') }}" class="btn btn--secondary btn--sm">Reset</a>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Preview</th>
                                <th>Title / File</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $i => $item)
                            <tr>
                                <td>{{ $items->firstItem() + $i }}</td>
                                <td>
                                    @if($item->is_image)
                                        <img src="{{ $item->file_url }}" alt="{{ $item->title }}"
                                             style="width:72px;height:48px;object-fit:cover;border-radius:6px;cursor:pointer;"
                                             onclick="window.open('{{ $item->file_url }}','_blank')">
                                    @else
                                        <div style="width:72px;height:48px;background:#1a1a2e;border-radius:6px;
                                                    display:flex;align-items:center;justify-content:center;cursor:pointer;"
                                             onclick="window.open('{{ $item->file_url }}','_blank')">
                                            <i class="las la-play-circle" style="font-size:24px;color:#F5A623;"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <strong style="font-size:13px;">{{ Str::limit($item->title, 40) }}</strong>
                                    <br><small class="text-muted">{{ Str::limit($item->file_original_name, 35) }}</small>
                                    @if($item->description)
                                        <br><small class="text-muted fst-italic">{{ Str::limit($item->description, 50) }}</small>
                                    @endif
                                </td>
                                <td><span class="badge badge--primary">{{ $item->category->name }}</span></td>
                                <td>
                                    @if($item->is_image)
                                        <span class="badge badge--success">Image</span>
                                    @else
                                        <span class="badge badge--warning">Video</span>
                                    @endif
                                </td>
                                <td><small>{{ $item->file_size_human }}</small></td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input toggle-active"
                                               type="checkbox"
                                               data-url="{{ route('admin.media.items.toggle', $item) }}"
                                               {{ $item->is_active ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td>
                                    <div class="button--group">
                                        <button class="btn btn-sm btn--primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editItemModal{{ $item->id }}"
                                                title="Edit">
                                            <i class="las la-pen"></i>
                                        </button>
                                        <button class="btn btn-sm btn--danger confirmationBtn"
                                                data-action="{{ route('admin.media.items.destroy', $item) }}"
                                                data-question="Delete this media item?">
                                            <i class="las la-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            {{-- Edit Modal --}}
                            <div class="modal fade" id="editItemModal{{ $item->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Media</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="{{ route('admin.media.items.update', $item) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Title</label>
                                                    <input type="text" name="title" class="form-control" value="{{ $item->title }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea name="description" class="form-control" rows="3">{{ $item->description }}</textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Sort Order</label>
                                                    <input type="number" name="sort_order" class="form-control"
                                                           value="{{ $item->sort_order }}" min="0">
                                                    <small class="text-muted">Lower = shows first within category</small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn--primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    No media uploaded yet. Use the upload panel on the left.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($items->hasPages())
            <div class="card-footer">{{ $items->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
</div>

<style>
.upload-drop-zone {
    border: 2px dashed #dee2e6; border-radius: 10px;
    padding: 20px; text-align: center; cursor: pointer;
    transition: border-color .2s, background .2s;
}
.upload-drop-zone.dragover { border-color: #F5A623; background: #fffbf0; }
.file-preview-item {
    position: relative; width: 70px; height: 70px;
    border-radius: 8px; overflow: hidden;
    border: 1px solid #dee2e6; background: #f8f9fa;
    display: flex; align-items: center; justify-content: center;
}
.file-preview-item img { width: 100%; height: 100%; object-fit: cover; }
.file-preview-item .remove-preview {
    position: absolute; top: 2px; right: 2px;
    background: rgba(220,53,69,.85); color: #fff; border: none;
    border-radius: 50%; width: 18px; height: 18px;
    font-size: 10px; line-height: 18px; text-align: center;
    cursor: pointer; padding: 0;
}
</style>

<script>
// ── Drag & Drop / Preview ─────────────────────────────────────────────────
const fileInput  = document.getElementById('fileInput');
const dropZone   = document.getElementById('dropZone');
const previewBox = document.getElementById('filePreviewList');
const MAX_VIDEO  = 10 * 1024 * 1024; // 10 MB

function addFiles(files) {
    Array.from(files).forEach(file => {
        const isVideo = file.type.startsWith('video/');
        if (isVideo && file.size > MAX_VIDEO) {
            alert(file.name + ' exceeds 10 MB video limit and was skipped.');
            return;
        }
        const wrap = document.createElement('div');
        wrap.className = 'file-preview-item';

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            wrap.appendChild(img);
        } else {
            wrap.innerHTML = '<i class="las la-video" style="font-size:28px;color:#F5A623;"></i>';
        }

        const btn = document.createElement('button');
        btn.className = 'remove-preview';
        btn.innerHTML = '&times;';
        btn.type = 'button';
        btn.onclick = () => wrap.remove();
        wrap.appendChild(btn);
        previewBox.appendChild(wrap);
    });
}

fileInput.addEventListener('change', () => addFiles(fileInput.files));

dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault(); dropZone.classList.remove('dragover');
    addFiles(e.dataTransfer.files);
    // Manually set files on input (DataTransfer trick)
    const dt = new DataTransfer();
    Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
});

// ── Toggle Active (AJAX) ──────────────────────────────────────────────────
document.querySelectorAll('.toggle-active').forEach(toggle => {
    toggle.addEventListener('change', function () {
        fetch(this.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) this.checked = !this.checked;
        })
        .catch(() => { this.checked = !this.checked; });
    });
});
</script>
@endsection