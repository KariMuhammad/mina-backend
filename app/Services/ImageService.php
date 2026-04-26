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
     * Generate an absolute URL from a stored image path.
     * If the path is a full Cloudinary URL (starts with http), inject transformations
     * for auto format (WebP), auto quality compression, and max width 800px.
     * Old local paths (e.g. products/abc.jpg) are no longer served — return null.
     */
    public static function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return str_replace(
                '/image/upload/',
                '/image/upload/f_auto,q_auto,w_800/',
                $path
            );
        }

        return null; // old local path → return null, don't serve from storage
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
