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

    public function issueToken(Request $request, CourseLesson $lesson)
    {
        $user = Auth::guard('web')->user();
        if (!$user) return response()->json(['error' => 'Unauthenticated.'], 401);
        if (!VideoTokenService::isEnrolled($user->id, $lesson->id))
            return response()->json(['error' => 'Not enrolled.'], 403);
        if ($lesson->video_type !== 'upload')
            return response()->json(['error' => 'Not an uploaded video lesson.'], 422);
        if (!$lesson->video_path)
            return response()->json(['error' => 'Video not attached yet. Please contact support.'], 404);
        if (!Storage::disk('course_videos')->exists($lesson->video_path)) {
            Log::error("SecureVideo: missing file lesson {$lesson->id} path: {$lesson->video_path}");
            return response()->json(['error' => 'Video file not found on server.'], 404);
        }
        $token = VideoTokenService::issue($user->id, $lesson->id, $request->ip());
        return response()->json([
            'token'      => $token,
            'stream_url' => route('video.stream', ['lesson' => $lesson->id, 'token' => $token]),
            'duration'   => $lesson->duration_seconds,
            'title'      => $lesson->title,
        ]);
    }

    public function stream(Request $request, CourseLesson $lesson)
    {
        $token = $request->query('token');

        // TEMP DEBUG — remove after fix confirmed
        Log::info('STREAM_DEBUG', [
            'lesson_id'   => $lesson->id,
            'token'       => substr($token ?? '', 0, 20) . '...',
            'ip_request'  => $request->ip(),
            'has_token'   => !empty($token),
        ]);

        // Guard 1: token valid + IP
        $data = VideoTokenService::verify($token ?? '', $request->ip());

        Log::info('STREAM_DEBUG_G1', [
            'verify_result' => $data ? 'PASS' : 'FAIL',
            'token_data'    => $data,
            'request_ip'    => $request->ip(),
        ]);

        if (!$data) return response('Guard1: token invalid or expired.', 403);

        // Guard 2: token is for THIS lesson
        Log::info('STREAM_DEBUG_G2', [
            'token_lesson_id'   => (int)$data['lesson_id'],
            'route_lesson_id'   => $lesson->id,
            'match'             => (int)$data['lesson_id'] === $lesson->id ? 'PASS' : 'FAIL',
        ]);
        if ((int)$data['lesson_id'] !== $lesson->id) return response('Guard2: token mismatch.', 403);

        // Guard 3: DB enrollment
        $enrolled = VideoTokenService::isEnrolled($data['user_id'], $lesson->id);
        Log::info('STREAM_DEBUG_G3', [
            'user_id'  => $data['user_id'],
            'enrolled' => $enrolled ? 'PASS' : 'FAIL',
        ]);
        if (!$enrolled) {
            VideoTokenService::revoke($token);
            return response('Guard3: access revoked.', 403);
        }

        // Guard 4: Referer check REMOVED intentionally.
        // The token is already IP-bound — someone from another site/IP
        // cannot use this token even if they somehow obtain the URL.
        // Adding a referer check on top causes false 403s because:
        // - <video> byte-range requests often omit the Referer header
        // - Some proxies/CDNs strip or modify the Referer header
        // Security is fully handled by Guards 1-3 above.

        // Resolve file path via storage disk
        $filePath = Storage::disk('course_videos')->path($lesson->video_path);
        Log::info('STREAM_DEBUG_FILE', [
            'video_path' => $lesson->video_path,
            'full_path'  => $filePath,
            'exists'     => file_exists($filePath) ? 'YES' : 'NO',
        ]);
        if (!file_exists($filePath)) {
            Log::error("Stream: file not found [{$filePath}] lesson {$lesson->id}");
            return response('Guard4: video not found.', 404);
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
            $handle = fopen($filePath, 'rb');
            while (!feof($handle)) { echo fread($handle, 1024 * 256); ob_flush(); flush(); }
            fclose($handle);
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
            $handle    = fopen($filePath, 'rb');
            fseek($handle, $start);
            $remaining = $length;
            while (!feof($handle) && $remaining > 0) {
                $chunk = fread($handle, min(1024 * 256, $remaining));
                echo $chunk;
                $remaining -= strlen($chunk);
                ob_flush(); flush();
            }
            fclose($handle);
        }, 206, array_merge($headers, [
            'Content-Length' => $length,
            'Content-Range'  => "bytes {$start}-{$end}/{$fileSize}",
        ]));
    }

    private function getMimeType(string $path): string
    {
        return match(strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
            'mov'  => 'video/quicktime',
            'mkv'  => 'video/x-matroska',
            'avi'  => 'video/x-msvideo',
            default => 'video/mp4',
        };
    }
}