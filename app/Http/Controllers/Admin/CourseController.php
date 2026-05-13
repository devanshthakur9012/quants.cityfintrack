<?php
// FILE: app/Http/Controllers/Admin/CourseController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseFaq;
use App\Models\CourseCategory;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // LIST
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $pageTitle = 'All Courses';

        $courses = Course::with('category')->withCount('lessons')
            ->when($request->search,   fn($q) => $q->where('title', 'like', '%' . $request->search . '%'))
            ->when($request->status,   fn($q) => $q->where('status', $request->status))
            ->when($request->category, fn($q) => $q->where('course_category_id', $request->category))
            ->orderByDesc('created_at')
            ->paginate(getPaginate());

        $categories = CourseCategory::active()->orderBy('name')->get();
        $statuses   = ['upcoming', 'ongoing', 'recorded', 'draft'];

        return view('admin.courses.index', compact('pageTitle', 'courses', 'categories', 'statuses'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE / STORE
    // ─────────────────────────────────────────────────────────────────────────
    public function create()
    {
        $pageTitle  = 'Add New Course';
        $categories = CourseCategory::active()->orderBy('name')->get();

        $employees = User::role('employee')
            ->with('employeeProfile')
            ->orderBy('firstname')
            ->get();

        return view('admin.courses.form', compact('pageTitle', 'categories', 'employees'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateCourse($request);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $this->uploadThumbnail($request->file('thumbnail'));
        }

        $validated['slug'] = Course::generateSlug($request->title);
        $validated         = $this->computePrice($validated);
        $validated         = $this->nullifyBatchSchedule($validated);

        $course = Course::create($validated);

        $this->syncTrainers($course, $request->input('trainer_ids', []));
        $this->saveNewFaqs($request, $course);

        $notify[] = ['success', 'Course created successfully'];
        return redirect()->route('admin.courses.index')->withNotify($notify);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDIT / UPDATE
    // ─────────────────────────────────────────────────────────────────────────
    public function edit(Course $course)
    {
        $pageTitle = 'Edit Course: ' . $course->title;

        $categories = CourseCategory::active()->orderBy('name')->get();

        $employees = User::role('employee')
            ->with('employeeProfile')
            ->orderBy('firstname')
            ->get();

        $selectedTrainers = DB::table('course_trainer_pivot')
            ->where('course_id', $course->id)
            ->pluck('user_id')
            ->toArray();

        $faqs = $course->faqs()->orderBy('sort_order')->get();

        return view('admin.courses.form', compact(
            'pageTitle', 'course', 'categories', 'employees', 'selectedTrainers', 'faqs'
        ));
    }

    public function update(Request $request, Course $course)
    {
        $validated = $this->validateCourse($request);

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail) {
                @unlink(public_path('assets/courses/thumbnails/' . $course->thumbnail));
            }
            $validated['thumbnail'] = $this->uploadThumbnail($request->file('thumbnail'));
        }

        $validated['slug'] = Course::generateSlug($request->title, $course->id);
        $validated         = $this->computePrice($validated);
        $validated         = $this->nullifyBatchSchedule($validated);

        $course->update($validated);

        $this->syncTrainers($course, $request->input('trainer_ids', []));
        $this->saveNewFaqs($request, $course);

        $notify[] = ['success', 'Course updated successfully'];
        return redirect()->route('admin.courses.index')->withNotify($notify);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy(Course $course)
    {
        foreach ($course->lessons as $lesson) {
            if ($lesson->video_type === 'upload' && $lesson->video_path) {
                Storage::disk('course_videos')->delete($lesson->video_path);
            }
            // Also clean up lesson preview videos
            if ($lesson->preview_video_type === 'upload' && $lesson->preview_video_path) {
                Storage::disk('course_videos')->delete($lesson->preview_video_path);
            }
        }
        // Clean up section preview videos
        foreach ($course->sections as $section) {
            if ($section->preview_video_type === 'upload' && $section->preview_video_path) {
                Storage::disk('course_videos')->delete($section->preview_video_path);
            }
        }
        if ($course->thumbnail) {
            @unlink(public_path('assets/courses/thumbnails/' . $course->thumbnail));
        }
        $course->delete();

        $notify[] = ['success', 'Course deleted successfully'];
        return back()->withNotify($notify);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STATUS / FEATURED
    // ─────────────────────────────────────────────────────────────────────────
    public function statusUpdate(Request $request, Course $course)
    {
        $request->validate(['status' => 'required|in:upcoming,ongoing,recorded,draft']);
        $course->update(['status' => $request->status]);
        $notify[] = ['success', 'Status updated successfully'];
        return back()->withNotify($notify);
    }

    public function featuredToggle(Course $course)
    {
        $course->update(['is_featured' => !$course->is_featured]);
        $notify[] = ['success', 'Featured status updated'];
        return back()->withNotify($notify);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CURRICULUM
    // ─────────────────────────────────────────────────────────────────────────
    public function curriculum(Course $course)
    {
        $pageTitle = 'Curriculum: ' . $course->title;
        $sections  = $course->sections()->with('lessons')->get();

        // Pre-compute course totals for sidebar
        $totalLessons  = $sections->sum(fn($s) => $s->lessons->count());
        $totalSeconds  = $sections->sum(fn($s) => $s->lessons->sum('duration_seconds'));
        $totalDuration = $this->formatDurationSeconds($totalSeconds);

        return view('admin.courses.curriculum', compact(
            'pageTitle', 'course', 'sections', 'totalLessons', 'totalDuration'
        ));
    }

    // ── SECTION CRUD ──────────────────────────────────────────────────────────

    public function sectionStore(Request $request, Course $course)
    {
        $request->validate([
            'title'               => 'required|string|max:191',
            'description'         => 'nullable|string',
            'preview_video_type'  => 'nullable|in:none,youtube,upload',
            'preview_video_url'   => 'nullable|string|max:500',
        ]);

        $maxOrder = $course->sections()->max('sort_order') ?? 0;

        $data = [
            'title'              => $request->title,
            'description'        => $request->description,
            'sort_order'         => $maxOrder + 1,
            'preview_video_type' => $request->input('preview_video_type', 'none'),
            'preview_video_url'  => $request->preview_video_url,
        ];

        $course->sections()->create($data);

        $notify[] = ['success', 'Section added successfully'];
        return back()->withNotify($notify);
    }

    public function sectionUpdate(Request $request, CourseSection $section)
    {
        $request->validate([
            'title'               => 'required|string|max:191',
            'description'         => 'nullable|string',
            'preview_video_type'  => 'nullable|in:none,youtube,upload',
            'preview_video_url'   => 'nullable|string|max:500',
        ]);

        $section->update([
            'title'              => $request->title,
            'description'        => $request->description,
            'preview_video_type' => $request->input('preview_video_type', 'none'),
            'preview_video_url'  => $request->preview_video_url,
        ]);

        return response()->json(['success' => true]);
    }

    public function sectionDestroy(CourseSection $section)
    {
        foreach ($section->lessons as $lesson) {
            if ($lesson->video_type === 'upload' && $lesson->video_path) {
                Storage::disk('course_videos')->delete($lesson->video_path);
            }
            if ($lesson->preview_video_type === 'upload' && $lesson->preview_video_path) {
                Storage::disk('course_videos')->delete($lesson->preview_video_path);
            }
        }
        if ($section->preview_video_type === 'upload' && $section->preview_video_path) {
            Storage::disk('course_videos')->delete($section->preview_video_path);
        }
        $section->delete();

        $notify[] = ['success', 'Section deleted'];
        return back()->withNotify($notify);
    }

    public function sectionReorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer']);
        foreach ($request->order as $position => $sectionId) {
            CourseSection::where('id', $sectionId)->update(['sort_order' => $position]);
        }
        return response()->json(['success' => true]);
    }

    // ── LESSON CRUD ───────────────────────────────────────────────────────────

    public function lessonCreate(Course $course, CourseSection $section)
    {
        $pageTitle = 'Add Lesson to: ' . $section->title;
        return view('admin.courses.lesson-form', compact('pageTitle', 'course', 'section'));
    }

    public function lessonStore(Request $request, Course $course, CourseSection $section)
    {
        $validated                      = $this->validateLesson($request);
        $validated['course_id']         = $course->id;
        $validated['course_section_id'] = $section->id;
        $validated['sort_order']        = $section->lessons()->max('sort_order') + 1;

        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $this->uploadAttachment($request->file('attachment'), $course->id);
        }

        CourseLesson::create($validated);

        $notify[] = ['success', 'Lesson created successfully'];
        return redirect()->route('admin.courses.curriculum', $course)->withNotify($notify);
    }

    public function lessonEdit(Course $course, CourseLesson $lesson)
    {
        $pageTitle = 'Edit Lesson: ' . $lesson->title;
        $sections  = $course->sections()->orderBy('sort_order')->get();
        return view('admin.courses.lesson-form', compact('pageTitle', 'course', 'lesson', 'sections'));
    }

    public function lessonUpdate(Request $request, Course $course, CourseLesson $lesson)
    {
        $validated = $this->validateLesson($request);

        if ($request->hasFile('attachment')) {
            if ($lesson->attachment) $this->deleteAttachment($lesson->attachment, $course->id);
            $validated['attachment'] = $this->uploadAttachment($request->file('attachment'), $course->id);
        }

        $lesson->update($validated);

        $notify[] = ['success', 'Lesson updated successfully'];
        return redirect()->route('admin.courses.curriculum', $course)->withNotify($notify);
    }

    public function lessonDestroy(Course $course, CourseLesson $lesson)
    {
        if ($lesson->video_type === 'upload' && $lesson->video_path) {
            Storage::disk('course_videos')->delete($lesson->video_path);
        }
        if ($lesson->preview_video_type === 'upload' && $lesson->preview_video_path) {
            Storage::disk('course_videos')->delete($lesson->preview_video_path);
        }
        if ($lesson->attachment) $this->deleteAttachment($lesson->attachment, $course->id);
        $lesson->delete();

        $notify[] = ['success', 'Lesson deleted'];
        return back()->withNotify($notify);
    }

    public function lessonReorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'integer']);
        foreach ($request->order as $position => $lessonId) {
            CourseLesson::where('id', $lessonId)->update(['sort_order' => $position]);
        }
        return response()->json(['success' => true]);
    }

    public function lessonStream(CourseLesson $lesson)
    {
        abort_unless(auth()->guard('admin')->check(), 403);
        if ($lesson->video_type !== 'upload' || !$lesson->video_path) abort(404);
        if (!Storage::disk('course_videos')->exists($lesson->video_path)) abort(404);

        $path     = Storage::disk('course_videos')->path($lesson->video_path);
        $size     = filesize($path);
        $mimeType = mime_content_type($path) ?: 'video/mp4';
        $headers  = [
            'Content-Type'           => $mimeType,
            'Accept-Ranges'          => 'bytes',
            'Content-Disposition'    => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (isset($_SERVER['HTTP_RANGE'])) {
            [$unit, $range] = explode('=', $_SERVER['HTTP_RANGE'], 2);
            [$start, $end]  = explode('-', $range, 2);
            $start  = (int) $start;
            $end    = $end !== '' ? (int) $end : $size - 1;
            $length = $end - $start + 1;

            return response()->stream(function () use ($path, $start, $length) {
                $handle    = fopen($path, 'rb');
                fseek($handle, $start);
                $remaining = $length;
                while (!feof($handle) && $remaining > 0) {
                    $chunk = fread($handle, min(8192, $remaining));
                    echo $chunk;
                    $remaining -= strlen($chunk);
                    ob_flush();
                    flush();
                }
                fclose($handle);
            }, 206, array_merge($headers, [
                'Content-Length' => $length,
                'Content-Range'  => "bytes {$start}-{$end}/{$size}",
            ]));
        }

        return response()->stream(
            fn() => readfile($path),
            200,
            array_merge($headers, ['Content-Length' => $size])
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FAQ CRUD
    // ─────────────────────────────────────────────────────────────────────────
    public function faqStore(Request $request, Course $course)
    {
        $request->validate(['question' => 'required|string|max:500', 'answer' => 'required|string']);
        $maxOrder = $course->faqs()->max('sort_order') ?? 0;
        $faq = $course->faqs()->create([
            'question'   => $request->question,
            'answer'     => $request->answer,
            'sort_order' => $maxOrder + 1,
        ]);
        return response()->json(['success' => true, 'message' => 'FAQ added', 'faq' => $faq]);
    }

    public function faqUpdate(Request $request, CourseFaq $faq)
    {
        $request->validate(['question' => 'required|string|max:500', 'answer' => 'required|string']);
        $faq->update(['question' => $request->question, 'answer' => $request->answer]);
        return response()->json(['success' => true, 'message' => 'FAQ updated']);
    }

    public function faqDestroy(CourseFaq $faq)
    {
        $faq->delete();
        return response()->json(['success' => true, 'message' => 'FAQ deleted']);
    }

    public function faqReorder(Request $request)
    {
        $request->validate(['order' => 'required|array']);
        foreach ($request->order as $position => $faqId) {
            CourseFaq::where('id', $faqId)->update(['sort_order' => $position]);
        }
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function validateCourse(Request $request): array
    {
        return $request->validate([
            'course_category_id'   => 'required|exists:course_categories,id',
            'title'                => 'required|string|max:191',
            'short_description'    => 'nullable|string|max:500',
            'description'          => 'nullable|string',
            'thumbnail'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'preview_video_type'   => 'required|in:youtube,upload',
            'preview_video_url'    => 'nullable|string|max:500',
            'mode'                 => 'required|in:online,offline,hybrid',
            'level'                => 'required|in:beginner,intermediate,advanced',
            'language'             => 'required|in:hindi,english,gujarati',
            'status'               => 'required|in:upcoming,ongoing,recorded,draft',
            'type'                 => 'required|in:free,paid',
            'mrp'                  => 'nullable|numeric|min:0',
            'discount_percent'     => 'nullable|numeric|min:0|max:100',
            'price'                => 'nullable|numeric|min:0',
            'discount_label'       => 'nullable|string|max:50',
            'has_certificate'      => 'nullable|boolean',
            'trainer_ids'          => 'nullable|array',
            'trainer_ids.*'        => 'exists:users,id',
            'sort_order'           => 'nullable|integer|min:0',
            'is_featured'          => 'nullable|boolean',
            'meta_title'           => 'nullable|string|max:191',
            'meta_description'     => 'nullable|string|max:500',
            'meta_keywords'        => 'nullable|string|max:300',
            'batch_name'           => 'nullable|string|max:100',
            'total_sessions'       => 'nullable|integer|min:0',
            'session_duration_hrs' => 'nullable|numeric|min:0',
            'schedule_days'        => 'nullable|string|max:100',
            'duration_label'       => 'nullable|string|max:100',
            'start_date'           => 'nullable|date',
            'end_date'             => 'nullable|date',
            'class_time'           => 'nullable|string|max:50',
        ]);
    }

    private function validateLesson(Request $request): array
    {
        return $request->validate([
            'course_section_id'    => 'required|exists:course_sections,id',
            'title'                => 'required|string|max:191',
            'description'          => 'nullable|string',
            // Main lesson video
            'video_type'           => 'required|in:youtube,upload',
            'video_url'            => 'nullable|string|max:500',
            // Lesson overview / preview video
            'preview_video_type'   => 'nullable|in:none,youtube,upload',
            'preview_video_url'    => 'nullable|string|max:500',
            // Meta
            'duration_seconds'     => 'nullable|integer|min:0',
            'attachment'           => 'nullable|file|mimes:pdf,docx,zip|max:20480',
            'sort_order'           => 'nullable|integer|min:0',
            // status kept for publish toggle
            'status'               => 'nullable|boolean',
        ]);
    }

    private function computePrice(array $data): array
    {
        $mrp     = floatval($data['mrp'] ?? 0);
        $discPct = floatval($data['discount_percent'] ?? 0);

        if ($mrp > 0 && $discPct > 0) {
            $data['price']          = round($mrp * (1 - $discPct / 100));
            $data['discount_label'] = round($discPct) . '% off';
        } elseif ($mrp > 0) {
            $data['price']          = $mrp;
            $data['discount_label'] = null;
        }

        return $data;
    }

    private function nullifyBatchSchedule(array $data): array
    {
        $data['batch_name']           = null;
        $data['total_sessions']       = 0;
        $data['session_duration_hrs'] = 0;
        $data['schedule_days']        = null;
        $data['start_date']           = null;
        $data['end_date']             = null;
        $data['class_time']           = null;
        $data['duration_label']       = null;
        return $data;
    }

    private function syncTrainers(Course $course, array $userIds): void
    {
        DB::table('course_trainer_pivot')->where('course_id', $course->id)->delete();
        foreach ($userIds as $sort => $userId) {
            DB::table('course_trainer_pivot')->insert([
                'course_id'  => $course->id,
                'user_id'    => $userId,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function saveNewFaqs(Request $request, Course $course): void
    {
        if (!$request->has('new_faqs')) return;
        $maxOrder = $course->faqs()->max('sort_order') ?? 0;
        foreach ($request->input('new_faqs', []) as $faq) {
            $question = trim($faq['question'] ?? '');
            $answer   = trim($faq['answer'] ?? '');
            if ($question && $answer) {
                $course->faqs()->create([
                    'question'   => $question,
                    'answer'     => $answer,
                    'sort_order' => ++$maxOrder,
                    'status'     => 1,
                ]);
            }
        }
    }

    private function uploadThumbnail($file): string
    {
        $dir = public_path('assets/courses/thumbnails');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $name);
        return $name;
    }

    private function uploadAttachment($file, int $courseId): string
    {
        $dir  = "attachments/course_{$courseId}";
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();
        Storage::disk('course_videos')->putFileAs($dir, $file, $name);
        return "{$dir}/{$name}";
    }

    private function deleteAttachment(string $path, int $courseId): void
    {
        Storage::disk('course_videos')->delete($path);
    }

    private function formatDurationSeconds(int $secs): string
    {
        $h = floor($secs / 3600);
        $m = floor(($secs % 3600) / 60);
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }
}