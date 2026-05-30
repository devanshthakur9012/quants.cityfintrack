<?php
// FILE: app/Http/Controllers/SecureVideoController.php
// KEY CHANGE: {lesson} route param is now an encrypted string.
// We decrypt it manually instead of using Laravel's model binding.

namespace App\Http\Controllers;

use App\Models\CourseLesson;
use App\Services\VideoTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SecureVideoController extends Controller
{
    // ── Decrypt the lesson param from URL ─────────────────────────────────────
    // Route uses {lesson} as a plain string (encrypted ID).
    // We decrypt → get lesson ID → load model.
    private function resolveLesson(string $encrypted): CourseLesson
    {
        try {
            $lessonId = decrypt($encrypted);
        } catch (\Exception $e) {
            abort(404, 'Invalid lesson link.');
        }
        return CourseLesson::findOrFail($lessonId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function player(Request $request, string $lesson)
    {
        $lesson = $this->resolveLesson($lesson);
        $user   = Auth::guard('web')->user();

        if (!$user) {
            return redirect()->route('user.login')
                ->with('loginRedirect', route('video.player', ['lesson' => encrypt($lesson->id)]));
        }
        if (!VideoTokenService::isEnrolled($user->id, $lesson->id)) {
            abort(403, 'You are not enrolled in this course.');
        }

        $course     = $lesson->course()->with([
            'sections'         => fn($q) => $q->orderBy('sort_order'),
            'sections.lessons' => fn($q) => $q->orderBy('sort_order'),
        ])->first();
        $section    = $lesson->section;
        $allLessons = $course->sections->flatMap->lessons->sortBy('sort_order')->values();
        $currentIdx = $allLessons->search(fn($l) => $l->id === $lesson->id);
        $prevLesson = $currentIdx > 0 ? $allLessons[$currentIdx - 1] : null;
        $nextLesson = $currentIdx < $allLessons->count() - 1 ? $allLessons[$currentIdx + 1] : null;
        $pageTitle  = $lesson->title . ' — ' . $course->title;

        return view(activeTemplate() . 'lesson-player', compact(
            'pageTitle', 'lesson', 'course', 'section',
            'prevLesson', 'nextLesson', 'allLessons'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function issueToken(Request $request, string $lesson)
    {
        $lesson = $this->resolveLesson($lesson);
        $user   = Auth::guard('web')->user();

        if (!$user) return response()->json(['error' => 'Unauthenticated.'], 401);
        if (!VideoTokenService::isEnrolled($user->id, $lesson->id))
            return response()->json(['error' => 'Not enrolled.'], 403);
        if ($lesson->video_type !== 'upload')
            return response()->json(['error' => 'Not an uploaded video lesson.'], 422);
        if (!$lesson->video_path)
            return response()->json(['error' => 'Video not attached yet.'], 404);
        if (!Storage::disk('course_videos')->exists($lesson->video_path)) {
            Log::error("SecureVideo: missing file lesson {$lesson->id}: {$lesson->video_path}");
            return response()->json(['error' => 'Video file not found on server.'], 404);
        }

        $token = VideoTokenService::issue($user->id, $lesson->id, $request->ip());

        return response()->json([
            'token'      => $token,
            'stream_url' => route('video.stream', ['lesson' => encrypt($lesson->id), 'token' => $token]),
            'duration'   => $lesson->duration_seconds,
            'title'      => $lesson->title,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function stream(Request $request, string $lesson)
    {
        $lesson = $this->resolveLesson($lesson);
        $token  = $request->query('token');

        $data = VideoTokenService::verify($token ?? '', $request->ip());
        if (!$data) return response('Token invalid or expired.', 403);
        if ((int)$data['lesson_id'] !== $lesson->id) return response('Token mismatch.', 403);
        if (!VideoTokenService::isEnrolled($data['user_id'], $lesson->id)) {
            VideoTokenService::revoke($token);
            return response('Access revoked.', 403);
        }

        $filePath = Storage::disk('course_videos')->path($lesson->video_path);
        if (!file_exists($filePath)) {
            Log::error("Stream: file not found [{$filePath}] lesson {$lesson->id}");
            return response('Video not found.', 404);
        }

        $fileSize = filesize($filePath);
        $mimeType = $this->getMimeType($filePath);
        $baseHeaders = [
            'Content-Type'           => $mimeType,
            'Accept-Ranges'          => 'bytes',
            'Content-Disposition'    => 'inline; filename="video.mp4"',
            'Cache-Control'          => 'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma'                 => 'no-cache',
            'Expires'                => '0',
            'Surrogate-Control'      => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'SAMEORIGIN',
            'X-Robots-Tag'           => 'noindex, noarchive',
        ];

        if ($request->hasHeader('Range')) {
            return $this->serveRange($request, $filePath, $fileSize, $baseHeaders);
        }
        return response()->stream(function () use ($filePath) {
            $h = fopen($filePath, 'rb');
            while (!feof($h)) { echo fread($h, 1024 * 256); ob_flush(); flush(); }
            fclose($h);
        }, 200, array_merge($baseHeaders, ['Content-Length' => $fileSize]));
    }

    private function serveRange(Request $request, string $filePath, int $fileSize, array $headers)
    {
        [$unit, $range] = explode('=', $request->header('Range'), 2);
        [$start, $end]  = array_pad(explode('-', $range, 2), 2, null);
        $start  = max(0, (int)$start);
        $end    = ($end !== '' && $end !== null) ? min((int)$end, $fileSize - 1) : $fileSize - 1;
        $end    = max($start, min($end, $fileSize - 1));
        $length = $end - $start + 1;
        return response()->stream(function () use ($filePath, $start, $length) {
            $h = fopen($filePath, 'rb'); fseek($h, $start);
            $rem = $length;
            while (!feof($h) && $rem > 0) {
                $c = fread($h, min(1024 * 256, $rem)); echo $c; $rem -= strlen($c); ob_flush(); flush();
            }
            fclose($h);
        }, 206, array_merge($headers, [
            'Content-Length' => $length,
            'Content-Range'  => "bytes {$start}-{$end}/{$fileSize}",
        ]));
    }

    private function getMimeType(string $path): string
    {
        return match(strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4'  => 'video/mp4',  'webm' => 'video/webm',
            'mov'  => 'video/quicktime', 'mkv' => 'video/x-matroska',
            'avi'  => 'video/x-msvideo', default => 'video/mp4',
        };
    }
}