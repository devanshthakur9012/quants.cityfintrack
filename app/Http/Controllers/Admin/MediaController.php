<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaCategory;
use App\Models\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    // CATEGORIES
    // ══════════════════════════════════════════════════════════════════════════

    public function categories(Request $request)
    {
        $pageTitle  = 'Media Categories';
        $categories = MediaCategory::withCount('mediaItems')
            ->when($request->search, fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(getPaginate());

        return view('admin.media.categories', compact('pageTitle', 'categories'));
    }

    public function categoryStore(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100|unique:media_categories,name',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        MediaCategory::create([
            'name'        => $request->name,
            'slug'        => MediaCategory::generateSlug($request->name),
            'description' => $request->description,
            'sort_order'  => $request->sort_order ?? 0,
            'is_active'   => true,
        ]);

        $notify[] = ['success', 'Category created successfully'];
        return back()->withNotify($notify);
    }

    public function categoryUpdate(Request $request, MediaCategory $category)
    {
        $request->validate([
            'name'        => 'required|string|max:100|unique:media_categories,name,' . $category->id,
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $category->update([
            'name'        => $request->name,
            'slug'        => MediaCategory::generateSlug($request->name, $category->id),
            'description' => $request->description,
            'sort_order'  => $request->sort_order ?? 0,
        ]);

        $notify[] = ['success', 'Category updated successfully'];
        return back()->withNotify($notify);
    }

    public function categoryToggle(MediaCategory $category)
    {
        $category->update(['is_active' => !$category->is_active]);
        $notify[] = ['success', 'Category status updated'];
        return back()->withNotify($notify);
    }

    public function categoryDestroy(MediaCategory $category)
    {
        // Delete all media files under this category
        foreach ($category->mediaItems as $item) {
            $this->deleteFile($item->file_name);
        }
        $category->delete();

        $notify[] = ['success', 'Category and all its media deleted'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MEDIA ITEMS
    // ══════════════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $pageTitle  = 'Media Items';
        $categories = MediaCategory::where('is_active', true)->orderBy('sort_order')->get();

        $items = MediaItem::with('category')
            ->when($request->category_id, fn($q) => $q->where('media_category_id', $request->category_id))
            ->when($request->type,        fn($q) => $q->where('file_type', $request->type))
            ->when($request->search,      fn($q) => $q->where('title', 'like', '%' . $request->search . '%'))
            ->orderByDesc('created_at')
            ->paginate(getPaginate());

        $counts = [
            'all'    => MediaItem::count(),
            'images' => MediaItem::where('file_type', 'image')->count(),
            'videos' => MediaItem::where('file_type', 'video')->count(),
        ];

        return view('admin.media.index', compact('pageTitle', 'categories', 'items', 'counts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'media_category_id' => 'required|exists:media_categories,id',
            'title'             => 'required|string|max:200',
            'description'       => 'nullable|string|max:500',
            'files'             => 'required|array|min:1',
            'files.*'           => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    $mime = $value->getMimeType();
                    $isImage = str_starts_with($mime, 'image/');
                    $isVideo = str_starts_with($mime, 'video/');

                    if (!$isImage && !$isVideo) {
                        $fail('Only image and video files are allowed.');
                        return;
                    }
                    if ($isVideo && $value->getSize() > 10 * 1024 * 1024) {
                        $fail('Video files must be under 10 MB.');
                    }
                },
            ],
        ]);

        $uploadPath = public_path('assets/images/media');
        if (!is_dir($uploadPath)) mkdir($uploadPath, 0755, true);

        foreach ($request->file('files') as $file) {
            $mime     = $file->getMimeType();
            $fileType = str_starts_with($mime, 'video/') ? 'video' : 'image';
            $ext      = $file->getClientOriginalExtension();
            $fileName = 'media_' . time() . '_' . Str::random(8) . '.' . $ext;

            // ✅ Capture size and original name BEFORE move()
            $fileSize         = $file->getSize();
            $fileOriginalName = $file->getClientOriginalName();

            $file->move($uploadPath, $fileName);

            MediaItem::create([
                'media_category_id'  => $request->media_category_id,
                'title'              => $request->title,
                'description'        => $request->description,
                'file_name'          => $fileName,
                'file_original_name' => $fileOriginalName,
                'file_type'          => $fileType,
                'mime_type'          => $mime,
                'file_size'          => $fileSize,
                'sort_order'         => 0,
                'is_active'          => true,
            ]);
        }

        $notify[] = ['success', 'Media uploaded successfully'];
        return back()->withNotify($notify);
    }

    public function update(Request $request, MediaItem $mediaItem)
    {
        $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $mediaItem->update([
            'title'       => $request->title,
            'description' => $request->description,
            'sort_order'  => $request->sort_order ?? $mediaItem->sort_order,
        ]);

        $notify[] = ['success', 'Media updated successfully'];
        return back()->withNotify($notify);
    }

    public function destroy(MediaItem $mediaItem)
    {
        $this->deleteFile($mediaItem->file_name);
        $mediaItem->delete();

        $notify[] = ['success', 'Media deleted'];
        return back()->withNotify($notify);
    }

    public function toggleActive(MediaItem $mediaItem)
    {
        $mediaItem->update(['is_active' => !$mediaItem->is_active]);
        return response()->json(['success' => true, 'active' => $mediaItem->fresh()->is_active]);
    }

    // ── Private ───────────────────────────────────────────────────────────────
    private function deleteFile(string $fileName): void
    {
        $path = public_path('assets/images/media/' . $fileName);
        if (file_exists($path)) unlink($path);
    }
}