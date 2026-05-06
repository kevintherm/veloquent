<?php

namespace Veloquent\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorageController extends Controller
{
    /**
     * Serve public files from the uploads directory.
     */
    public function show(Request $request, string $path): BinaryFileResponse
    {
        if (! str_starts_with($path, 'uploads/')) {
            abort(403);
        }

        if (str_contains($path, '..') || str_contains($path, '\\')) {
            abort(403);
        }

        $disk = Storage::disk((string) config('filesystems.default', 'local'));

        if (! $disk->exists($path)) {
            abort(404);
        }

        $absolutePath = $disk->path($path);

        if (! is_file($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath);
    }
}
