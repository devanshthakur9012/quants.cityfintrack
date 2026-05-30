<?php
// FILE: app/Services/VideoTokenService.php
namespace App\Services;

use App\Models\CourseLesson;
use App\Models\CourseEnrollment;
use Illuminate\Support\Facades\Cache;

class VideoTokenService
{
    // Token valid 2 hours — enough for any lesson watch session
    const TTL = 7200;

    /**
     * Issue a signed stream token for an enrolled user.
     * Token is IP-bound and stored server-side only — never in a cookie or URL
     * visible to the user before they request a stream.
     */
    public static function issue(int $userId, int $lessonId, string $ip): string
    {
        $token = hash_hmac(
            'sha256',
            $userId . '|' . $lessonId . '|' . $ip . '|' . time() . '|' . random_int(0, 999999),
            config('app.key')
        );

        Cache::put('vt:' . $token, [
            'user_id'   => $userId,
            'lesson_id' => $lessonId,
            'ip'        => $ip,
            'exp'       => time() + self::TTL,
            'used'      => false,
        ], self::TTL);

        return $token;
    }

    /**
     * Verify a token. Returns payload or null.
     * Does NOT consume the token — streaming uses byte-range so
     * the same token serves multiple range requests for one session.
     */
    public static function verify(string $token, string $ip): ?array
    {
        $data = Cache::get('vt:' . $token);

        if (!$data)                        return null;
        if ($data['exp'] < time())         { Cache::forget('vt:' . $token); return null; }
        if ($data['ip'] !== $ip)           return null; // IP changed = deny

        return $data;
    }

    /**
     * Revoke a token (on logout or when user un-enrolls).
     */
    public static function revoke(string $token): void
    {
        Cache::forget('vt:' . $token);
    }

    /**
     * Check DB enrollment — called on every stream request as second guard.
     */
    public static function isEnrolled(int $userId, int $lessonId): bool
    {
        $lesson = CourseLesson::find($lessonId);
        if (!$lesson) return false;

        return CourseEnrollment::where('user_id', $userId)
            ->where('course_id', $lesson->course_id)
            ->where('status', 1)
            ->exists();
    }
}