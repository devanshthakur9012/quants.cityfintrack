<?php
// FILE: app/Http/Controllers/SecureVideoController.php
namespace App\Http\Controllers;

use App\Models\CourseLesson;
use App\Models\CourseEnrollment;
use App\Services\VideoTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SecureVideoController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // STEP 1 — Issue a stream token
    // Called via POST when the player page loads.
    // Returns a short token — never the real file path.
    // ─────────────────────────────────────────────────────────────────────────
    public function issueToken(Request $request, CourseLesson $lesson)
    {
        $user = Auth::guard('web')->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Must be enrolled
        if (!VideoTokenService::isEnrolled($user->id, $lesson->id)) {
            return response()->json(['error' => 'Not enrolled in this course.'], 403);
        }

        if ($lesson->video_type !== 'upload' || !$lesson->video_path) {
            return response()->json(['error' => 'This lesson has no streamable video.'], 422);
        }

        // Check file actually exists on disk
        if (!Storage::disk('course_videos')->exists($lesson->video_path)) {
            Log::error("Video file missing for lesson {$lesson->id}: {$lesson->video_path}");
            return response()->json(['error' => 'Video file not found. Please contact support.'], 404);
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
    // STEP 2 — Stream the video
    // Serves the video file in byte-range chunks.
    // Every request verifies the token + IP + enrollment.
    // ─────────────────────────────────────────────────────────────────────────
    public function stream(Request $request, CourseLesson $lesson)
    {
        $token = $request->query('token');

        // ── Guard 1: Token valid + IP matches ───────────────────────────────
        $data = VideoTokenService::verify($token ?? '', $request->ip());
        if (!$data) {
            return response('Stream token invalid or expired.', 403);
        }

        // ── Guard 2: Token is for THIS lesson ────────────────────────────────
        if ((int)$data['lesson_id'] !== $lesson->id) {
            return response('Token lesson mismatch.', 403);
        }

        // ── Guard 3: Re-check DB enrollment on every request ─────────────────
        // Prevents someone who was un-enrolled mid-session from continuing
        if (!VideoTokenService::isEnrolled($data['user_id'], $lesson->id)) {
            VideoTokenService::revoke($token);
            return response('Access revoked.', 403);
        }

        // ── Guard 4: Referrer must be our domain ──────────────────────────────
        $referer = $request->header('Referer', '');
        $ourHost = parse_url(config('app.url'), PHP_URL_HOST);
        if ($referer && parse_url($referer, PHP_URL_HOST) !== $ourHost) {
            Log::warning("Video hotlink attempt from: {$referer} for lesson {$lesson->id}");
            return response('Hotlinking not allowed.', 403);
        }

        // ── Get file ──────────────────────────────────────────────────────────
        $filePath = Storage::disk('course_videos')->path($lesson->video_path);
        if (!file_exists($filePath)) {
            return response('Video not found.', 404);
        }

        $fileSize = filesize($filePath);
        $mimeType = $this->getMimeType($filePath);

        // ── Security headers ──────────────────────────────────────────────────
        // These tell the browser: do NOT cache this to disk, do NOT allow
        // right-click save, do NOT allow picture-in-picture, etc.
        $baseHeaders = [
            'Content-Type'              => $mimeType,
            'Accept-Ranges'             => 'bytes',
            'Content-Disposition'       => 'inline; filename="video.mp4"',
            // No caching anywhere — every byte must come through our auth layer
            'Cache-Control'             => 'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma'                    => 'no-cache',
            'Expires'                   => '0',
            // Prevent CDN/proxy caching
            'Surrogate-Control'         => 'no-store',
            // Prevent MIME sniffing attacks
            'X-Content-Type-Options'    => 'nosniff',
            // Prevent embedding in other sites (hotlink via <video> on another domain)
            'X-Frame-Options'           => 'SAMEORIGIN',
            // Block search engine archiving
            'X-Robots-Tag'              => 'noindex, noarchive, nosnippet',
            // Content Security Policy — only our origin can play this
            'Content-Security-Policy'   => "default-src 'none'; media-src 'self'",
        ];

        // ── Byte-range streaming ──────────────────────────────────────────────
        // Browser requests specific byte ranges (e.g. "bytes=0-65535").
        // This means:
        // 1. The full file is never downloaded in one request
        // 2. Seeking works properly
        // 3. Each range request is re-verified (token + IP + enrollment)
        //
        // Typical browser chunk size: ~512KB–2MB per request
        // A 500MB video = 250–1000 separate verified requests
        if ($request->hasHeader('Range')) {
            return $this->serveRange($request, $filePath, $fileSize, $baseHeaders);
        }

        // ── Full file (first request from some browsers) ──────────────────────
        return response()->stream(function () use ($filePath) {
            $handle = fopen($filePath, 'rb');
            while (!feof($handle)) {
                echo fread($handle, 1024 * 256); // 256KB chunks
                ob_flush();
                flush();
            }
            fclose($handle);
        }, 200, array_merge($baseHeaders, [
            'Content-Length' => $fileSize,
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lesson player page
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

        $course     = $lesson->course()->with(['sections' => fn($q) => $q->orderBy('sort_order'),
                                               'sections.lessons' => fn($q) => $q->orderBy('sort_order')])->first();
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
    // Private: serve byte range
    // ─────────────────────────────────────────────────────────────────────────
    private function serveRange(Request $request, string $filePath, int $fileSize, array $headers): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        [$unit, $range] = explode('=', $request->header('Range'), 2);
        [$start, $end]  = array_pad(explode('-', $range, 2), 2, null);

        $start = (int) $start;
        $end   = ($end !== '' && $end !== null) ? min((int) $end, $fileSize - 1) : $fileSize - 1;

        // Clamp to file bounds
        $start  = max(0, min($start, $fileSize - 1));
        $end    = max($start, min($end, $fileSize - 1));
        $length = $end - $start + 1;

        return response()->stream(function () use ($filePath, $start, $length) {
            $handle    = fopen($filePath, 'rb');
            fseek($handle, $start);
            $remaining = $length;
            while (!feof($handle) && $remaining > 0) {
                $chunk     = fread($handle, min(1024 * 256, $remaining)); // 256KB per chunk
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
            default => 'video/mp4',
        };
    }
}