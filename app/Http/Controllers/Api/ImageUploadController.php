<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageUploadController extends Controller
{
    public function uploadTemp(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        if (!$request->hasFile('image')) {
            return response()->json(['message' => 'No image uploaded.'], 400);
        }

        $file = $request->file('image');

        if (!$file->isValid()) {
            return response()->json(['message' => 'Uploaded file is invalid.'], 422);
        }

        $path = $file->store('temp-images', 'public');

        return response()->json([
            'url' => url(Storage::url($path)),
        ]);
    }
}
