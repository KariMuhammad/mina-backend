<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * The storage disk to use for image operations
     */
    private string $disk;

    /**
     * Create a new ImageService instance
     *
     * @param string $disk The storage disk name (default: 'public')
     */
    public function __construct(string $disk = 'public')
    {
        $this->disk = $disk;
    }

    /**
     * Upload an image file and return the relative path
     *
     * @param UploadedFile $file The uploaded file
     * @param string $folder The folder within the disk (e.g., 'avatars', 'products')
     * @return string The relative path (e.g., 'avatars/abc123.webp')
     */
    public function upload(UploadedFile $file, string $folder): string
    {
        $filename = $this->generateFilename($file);
        $path = Storage::disk($this->disk)->putFileAs($folder, $file, $filename);
        
        return $path;
    }

    /**
     * Generate an absolute URL from a relative path (stored as e.g. products/abc.jpg on the public disk).
     * Uses asset() so the host/port always match config('app.url') — required when the React app runs on
     * another origin (e.g. Vite :5173) and APP_URL must be the Laravel server (e.g. http://127.0.0.1:8000).
     */
    public function url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        // Already a public path segment
        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/' . $path);
    }

    /**
     * Delete an image file from storage
     *
     * @param string|null $path The relative path to delete
     * @return bool True if deleted or path was null, false on failure
     */
    public function delete(?string $path): bool
    {
        if (empty($path)) {
            return true;
        }

        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Replace an existing image with a new one
     *
     * @param string|null $oldPath The old relative path to delete
     * @param UploadedFile $newFile The new file to upload
     * @param string $folder The folder within the disk
     * @return string The new relative path
     */
    public function replace(?string $oldPath, UploadedFile $newFile, string $folder): string
    {
        // Upload new file first
        $newPath = $this->upload($newFile, $folder);
        
        // Delete old file only after successful upload
        if (!empty($oldPath)) {
            $this->delete($oldPath);
        }
        
        return $newPath;
    }

    /**
     * Generate a unique filename for the uploaded file
     *
     * @param UploadedFile $file The uploaded file
     * @return string The unique filename with extension
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = Str::random(32);
        
        return $hash . '.' . $extension;
    }
}
