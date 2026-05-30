<?php
// FILE: app/Http/Controllers/SecureVideoController.php
namespace App\Http\Controllers;

use App\Models\CourseLesson;
use App\Services\VideoTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SecureVideoController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // STEP 1 — Lesson player page
    // ─────────────────────────────────────────────────────────────────────────
    public function player(Request $request, CourseLesson $lesson)
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return redirect()->route('user.login')
                ->with('loginRedirect', route('video.player', $lesson));
        }

        if (!VideoTokenService::isEnrolled($user->id, $lesson->id)) {
            abort(403, 'You are not enrolled in this course.');
        }

        $course = $lesson->course()->with([
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
    // STEP 2 — Issue a stream token (POST, enrolled users only)
    // Returns a signed token — never the real file path
    // ─────────────────────────────────────────────────────────────────────────
    public function issueToken(Request $request, CourseLesson $lesson)
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (!VideoTokenService::isEnrolled($user->id, $lesson->id)) {
            return response()->json(['error' => 'Not enrolled in this course.'], 403);
        }

        // Only upload-type lessons need a token
        if ($lesson->video_type !== 'upload') {
            return response()->json(['error' => 'This lesson does not use an uploaded video.'], 422);
        }

        if (!$lesson->video_path) {
            return response()->json([
                'error' => 'Video not attached to this lesson yet. Please contact support.',
            ], 404);
        }

        // Check the file actually exists on the secure disk
        if (!Storage::disk('course_videos')->exists($lesson->video_path)) {
            Log::error("SecureVideo: file missing for lesson {$lesson->id} — path: {$lesson->video_path}");
            return response()->json([
                'error' => 'Video file not found on server. Please contact support.',
            ], 404);
        }

        $token = VideoTokenService::issue($user->id, $lesson->id, $request->ip());

        return response()->json([
            'token'      => $token,
            'stream_url' => route('video.stream', ['lesson' => $lesson->id, 'token' => $token]),
            'duration'   => $lesson->duration_seconds,
            'title'      => $lesson->title,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STEP 3 — Stream video bytes (chunked, token-verified, IP-bound)
    // ─────────────────────────────────────────────────────────────────────────
    public function stream(Request $request, CourseLesson $lesson)
    {
        $token = $request->query('token');

        // Guard 1: token valid + IP matches
        $data = VideoTokenService::verify($token ?? '', $request->ip());
        if (!$data) {
            return response('Stream token invalid or expired.', 403);
        }

        // Guard 2: token is for THIS lesson
        if ((int)$data['lesson_id'] !== $lesson->id) {
            return response('Token lesson mismatch.', 403);
        }

        // Guard 3: re-check DB enrollment on every request
        if (!VideoTokenService::isEnrolled($data['user_id'], $lesson->id)) {
            VideoTokenService::revoke($token);
            return response('Access revoked.', 403);
        }

        // Guard 4: referrer must be our own domain
        $referer = $request->header('Referer', '');
        if ($referer) {
            $ourHost     = parse_url(config('app.url'), PHP_URL_HOST);
            $refererHost = parse_url($referer, PHP_URL_HOST);
            if ($refererHost && $refererHost !== $ourHost) {
                Log::warning("Video hotlink from [{$referer}] for lesson {$lesson->id}");
                return response('Hotlinking not allowed.', 403);
            }
        }

        // Resolve file path from the course_videos disk
        $filePath = Storage::disk('course_videos')->path($lesson->video_path);
        if (!file_exists($filePath)) {
            Log::error("SecureVideo stream: file not found [{$filePath}] lesson {$lesson->id}");
            return response('Video file not found.', 404);
        }

        $fileSize = filesize($filePath);
        $mimeType = $this->getMimeType($filePath);

        // Security headers
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

        // Byte-range response (normal browser video streaming)
        if ($request->hasHeader('Range')) {
            return $this->serveRange($request, $filePath, $fileSize, $baseHeaders);
        }

        // Full file
        return response()->stream(function () use ($filePath) {
            $handle = fopen($filePath, 'rb');
            while (!feof($handle)) {
                echo fread($handle, 1024 * 256);
                ob_flush();
                flush();
            }
            fclose($handle);
        }, 200, array_merge($baseHeaders, ['Content-Length' => $fileSize]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: serve byte range
    // ─────────────────────────────────────────────────────────────────────────
    private function serveRange(Request $request, string $filePath, int $fileSize, array $headers)
    {
        [$unit, $range] = explode('=', $request->header('Range'), 2);
        [$start, $end]  = array_pad(explode('-', $range, 2), 2, null);

        $start = max(0, (int)$start);
        $end   = ($end !== '' && $end !== null)
            ? min((int)$end, $fileSize - 1)
            : $fileSize - 1;
        $end    = max($start, min($end, $fileSize - 1));
        $length = $end - $start + 1;

        return response()->stream(function () use ($filePath, $start, $length) {
            $handle    = fopen($filePath, 'rb');
            fseek($handle, $start);
            $remaining = $length;
            while (!feof($handle) && $remaining > 0) {
                $chunk      = fread($handle, min(1024 * 256, $remaining));
                echo $chunk;
                $remaining -= strlen($chunk);
                ob_flush();
                flush();
            }
            fclose($handle);
        }, 206, array_merge($headers, [
            'Content-Length' => $length,
            'Content-Range'  => "bytes {$start}-{$end}/{$fileSize}",
        ]));
    }

    private function getMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match($ext) {
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
            'mov'  => 'video/quicktime',
            'mkv'  => 'video/x-matroska',
            'avi'  => 'video/x-msvideo',
            default => 'video/mp4',
        };
    }
}