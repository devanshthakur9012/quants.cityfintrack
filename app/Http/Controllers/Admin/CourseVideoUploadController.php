<?php
// FILE: app/Http/Controllers/Admin/CourseVideoUploadController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use App\Models\CourseVideoUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseVideoUploadController extends Controller
{
    private const ALLOWED_MIMES = [
        'video/mp4',
        'video/webm',
        'video/quicktime',   // .mov
        'video/x-msvideo',   // .avi
        'video/x-matroska',  // .mkv
        'application/octet-stream', // some browsers send this for mp4
    ];
    private const ALLOWED_EXTS = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
    private const MAX_MB        = 2048; // 2 GB

    // ─────────────────────────────────────────────────────────────────────────
    // CHUNK UPLOAD
    // ─────────────────────────────────────────────────────────────────────────
    public function uploadChunk(Request $request)
    {
        $request->validate([
            'upload_id'    => 'required|string|max:100',
            'chunk'        => 'required|file',
            'chunk_index'  => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'filename'     => 'required|string|max:255',
            'lesson_id'    => 'nullable|exists:course_lessons,id',
        ]);

        // Validate file extension (MIME type can be spoofed)
        $filename = $request->filename;
        $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTS)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file type. Allowed: MP4, WEBM, MOV, AVI, MKV.',
            ], 422);
        }

        $uploadId    = preg_replace('/[^a-zA-Z0-9\-]/', '', $request->upload_id); // sanitize
        $chunkIndex  = (int)$request->chunk_index;
        $totalChunks = (int)$request->total_chunks;
        $lessonId    = $request->lesson_id ? (int)$request->lesson_id : null;

        // Get or create tracking record
        $upload = CourseVideoUpload::firstOrCreate(
            ['upload_id' => $uploadId],
            [
                'original_name'    => $filename,
                'total_chunks'     => $totalChunks,
                'uploaded_chunks'  => 0,
                'status'           => 'pending',
                'course_lesson_id' => $lessonId,
                'mime_type'        => $request->file('chunk')->getMimeType(),
            ]
        );

        // If lesson_id was missing on first call but present now, update it
        if ($lessonId && !$upload->course_lesson_id) {
            $upload->update(['course_lesson_id' => $lessonId]);
        }

        // Store chunk in temp dir
        $chunkDir = storage_path("app/temp_chunks/{$uploadId}");
        if (!is_dir($chunkDir)) {
            mkdir($chunkDir, 0755, true);
        }
        file_put_contents(
            "{$chunkDir}/chunk_{$chunkIndex}",
            file_get_contents($request->file('chunk')->getRealPath())
        );

        $upload->increment('uploaded_chunks');
        $freshUpload = $upload->fresh();

        // All chunks received → assemble
        if ($freshUpload->uploaded_chunks >= $totalChunks) {
            return $this->assembleChunks($freshUpload, $chunkDir, $filename, $lessonId);
        }

        return response()->json([
            'success'  => true,
            'progress' => round(($freshUpload->uploaded_chunks / $totalChunks) * 100),
            'message'  => "Chunk {$chunkIndex} uploaded ({$freshUpload->uploaded_chunks}/{$totalChunks})",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASSEMBLE all chunks into final file
    // ─────────────────────────────────────────────────────────────────────────
    private function assembleChunks(CourseVideoUpload $upload, string $chunkDir, string $filename, ?int $lessonId)
    {
        $upload->update(['status' => 'processing']);

        $ext        = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'mp4';
        $secureName = Str::uuid() . '.' . $ext;
        $finalDir   = 'videos';
        $finalPath  = "{$finalDir}/{$secureName}";

        // Assemble into a temp file
        $tmpFile = storage_path("app/temp_chunks/{$upload->upload_id}_assembled.{$ext}");
        $out     = fopen($tmpFile, 'wb');

        for ($i = 0; $i < $upload->total_chunks; $i++) {
            $chunkPath = "{$chunkDir}/chunk_{$i}";
            if (!file_exists($chunkPath)) {
                fclose($out);
                @unlink($tmpFile);
                $upload->update(['status' => 'failed']);
                return response()->json([
                    'success' => false,
                    'message' => "Assembly failed: missing chunk {$i}. Please retry the upload.",
                ], 500);
            }
            fwrite($out, file_get_contents($chunkPath));
        }
        fclose($out);

        // Move to secure course_videos disk (outside public/)
        Storage::disk('course_videos')->putFileAs(
            $finalDir,
            new \Illuminate\Http\File($tmpFile),
            $secureName
        );

        $fileSize = filesize($tmpFile);

        // Cleanup temp files
        $this->cleanupTemp($chunkDir, $tmpFile);

        // Update tracking record
        $upload->update([
            'final_path' => $finalPath,
            'file_size'  => $fileSize,
            'status'     => 'done',
        ]);

        // ── CRITICAL: Link video to lesson ────────────────────────────────
        // Use lesson_id from the upload record OR the passed param
        $effectiveLessonId = $lessonId ?? $upload->course_lesson_id;

        if ($effectiveLessonId && $lesson = CourseLesson::find($effectiveLessonId)) {
            // Delete old video file if it existed
            if ($lesson->video_path && $lesson->video_path !== $finalPath) {
                Storage::disk('course_videos')->delete($lesson->video_path);
            }
            $lesson->update([
                'video_type' => 'upload',
                'video_path' => $finalPath,
                'video_disk' => 'course_videos',
                'video_url'  => null, // clear any old YT url
            ]);

            // Also update the upload record with the lesson link
            $upload->update(['course_lesson_id' => $effectiveLessonId]);
        }

        return response()->json([
            'success'    => true,
            'progress'   => 100,
            'message'    => 'Video uploaded successfully!',
            'final_path' => $finalPath,
            'file_size'  => $this->formatBytes($fileSize),
            'lesson_id'  => $effectiveLessonId,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STATUS CHECK
    // ─────────────────────────────────────────────────────────────────────────
    public function status(Request $request)
    {
        $request->validate(['upload_id' => 'required|string']);
        $upload = CourseVideoUpload::where('upload_id', $request->upload_id)->firstOrFail();

        return response()->json([
            'status'          => $upload->status,
            'progress'        => $upload->total_chunks > 0
                ? round(($upload->uploaded_chunks / $upload->total_chunks) * 100)
                : 0,
            'uploaded_chunks' => $upload->uploaded_chunks,
            'total_chunks'    => $upload->total_chunks,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE VIDEO FROM LESSON
    // ─────────────────────────────────────────────────────────────────────────
    public function deleteVideo(CourseLesson $lesson)
    {
        if ($lesson->video_path) {
            Storage::disk('course_videos')->delete($lesson->video_path);
        }
        $lesson->update([
            'video_type' => 'youtube',
            'video_path' => null,
            'video_url'  => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Video removed from lesson.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────
    private function cleanupTemp(string $chunkDir, string $tmpFile): void
    {
        array_map('unlink', glob("{$chunkDir}/chunk_*") ?: []);
        @rmdir($chunkDir);
        if (file_exists($tmpFile)) @unlink($tmpFile);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
        return number_format($bytes / 1024, 2) . ' KB';
    }
}