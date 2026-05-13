<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseLesson;
use App\Models\CourseVideoUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles chunked video uploads for course lessons.
 *
 * Flow:
 *   1. Client sends POST /admin/courses/video/upload-chunk with:
 *      - upload_id  : unique session UUID (generated client-side)
 *      - chunk      : the blob chunk file
 *      - chunk_index: 0-based index
 *      - total_chunks
 *      - filename   : original file name
 *      - lesson_id  : (optional) existing lesson to attach to immediately after assembly
 *
 *   2. Server stores each chunk in temp/chunks/{upload_id}/chunk_{n}
 *
 *   3. When all chunks received, server assembles, moves to secure disk,
 *      updates lesson record, and cleans up temp files.
 */
class CourseVideoUploadController extends Controller
{
    private const ALLOWED_MIMES = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo'];
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

        $uploadId    = $request->upload_id;
        $chunkIndex  = (int)$request->chunk_index;
        $totalChunks = (int)$request->total_chunks;
        $filename    = $request->filename;
        $lessonId    = $request->lesson_id;

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

        // Store chunk in local temp dir
        $chunkDir  = storage_path("app/temp_chunks/{$uploadId}");
        if (!is_dir($chunkDir)) mkdir($chunkDir, 0755, true);

        $chunkPath = "{$chunkDir}/chunk_{$chunkIndex}";
        file_put_contents($chunkPath, file_get_contents($request->file('chunk')->getRealPath()));

        $upload->increment('uploaded_chunks');

        // ── All chunks received → assemble ──────────────────────────────────
        if ($upload->fresh()->uploaded_chunks >= $totalChunks) {
            return $this->assembleChunks($upload, $chunkDir, $filename, $lessonId);
        }

        return response()->json([
            'success'  => true,
            'progress' => round(($upload->fresh()->uploaded_chunks / $totalChunks) * 100),
            'message'  => "Chunk {$chunkIndex} uploaded",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASSEMBLE
    // ─────────────────────────────────────────────────────────────────────────
    private function assembleChunks(CourseVideoUpload $upload, string $chunkDir, string $filename, ?int $lessonId)
    {
        $upload->update(['status' => 'processing']);

        $ext         = pathinfo($filename, PATHINFO_EXTENSION) ?: 'mp4';
        $secureName  = Str::uuid() . '.' . strtolower($ext);
        $finalDir    = 'videos';                                  // inside course_videos disk
        $finalPath   = "{$finalDir}/{$secureName}";

        // Assembly into a temp file first
        $tmpFile = storage_path("app/temp_chunks/{$upload->upload_id}_assembled.{$ext}");
        $out     = fopen($tmpFile, 'wb');

        for ($i = 0; $i < $upload->total_chunks; $i++) {
            $chunkPath = "{$chunkDir}/chunk_{$i}";
            if (!file_exists($chunkPath)) {
                fclose($out);
                $upload->update(['status' => 'failed']);
                return response()->json(['success' => false, 'message' => "Missing chunk {$i}"], 500);
            }
            fwrite($out, file_get_contents($chunkPath));
        }
        fclose($out);

        // Move assembled file to secure storage disk
        Storage::disk('course_videos')->putFileAs(
            $finalDir,
            new \Illuminate\Http\File($tmpFile),
            $secureName
        );

        $fileSize = filesize($tmpFile);

        // Clean up temp
        $this->cleanupTemp($chunkDir, $tmpFile);

        // Update tracking record
        $upload->update([
            'final_path' => $finalPath,
            'file_size'  => $fileSize,
            'status'     => 'done',
        ]);

        // Update lesson record if attached
        if ($lessonId && $lesson = CourseLesson::find($lessonId)) {
            // Remove old video if any
            if ($lesson->video_path) {
                Storage::disk('course_videos')->delete($lesson->video_path);
            }
            $lesson->update([
                'video_type'  => 'upload',
                'video_path'  => $finalPath,
                'video_disk'  => 'course_videos',
                'video_url'   => null,
            ]);
        }

        return response()->json([
            'success'     => true,
            'progress'    => 100,
            'message'     => 'Video uploaded and assembled successfully',
            'final_path'  => $finalPath,
            'file_size'   => $this->formatBytes($fileSize),
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
            'progress'        => $upload->progress,
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
        $lesson->update(['video_type' => 'youtube', 'video_path' => null, 'video_url' => null]);

        return response()->json(['success' => true, 'message' => 'Video removed']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────
    private function cleanupTemp(string $chunkDir, string $tmpFile): void
    {
        // Remove chunk files
        array_map('unlink', glob("{$chunkDir}/chunk_*"));
        @rmdir($chunkDir);
        // Remove assembled temp file
        if (file_exists($tmpFile)) @unlink($tmpFile);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return number_format($bytes / 1048576, 2) . ' MB';
        return number_format($bytes / 1024, 2) . ' KB';
    }
}