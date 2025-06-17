<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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

        try {
            // 生成一個唯一的檔案名稱，並指定 GCS 上的暫存資料夾
            $folderPath = 'temp-images/';
            $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $folderPath . $fileName;

            // 記錄檔案資訊
            Log::info('Uploading file', [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'file_path' => $filePath,
                'real_path' => $file->getRealPath(),
            ]);

            // 檢查檔案內容
            $fileContent = file_get_contents($file->getRealPath());
            if ($fileContent === false) {
                throw new \Exception('無法讀取檔案內容');
            }

            Log::info('File content read', ['content_size' => strlen($fileContent)]);

            // 將圖片內容上傳到 GCS 的 'gcs' 磁碟
            $uploadResult = Storage::disk('gcs')->put($filePath, $fileContent);

            Log::info('Upload result', ['result' => $uploadResult]);

            if (!$uploadResult) {
                throw new \Exception('檔案上傳失敗');
            }

            // 檢查檔案是否真的存在於 GCS
            $exists = Storage::disk('gcs')->exists($filePath);
            Log::info('File exists check', ['exists' => $exists, 'path' => $filePath]);

            if (!$exists) {
                throw new \Exception('檔案上傳後不存在於 GCS');
            }

            // 取得圖片的簽署網址 (Signed URL)，設定有效期，例如 10 分鐘
            $signedUrl = Storage::disk('gcs')->temporaryUrl(
                $filePath,
                now()->addMinutes(10)
            );

            Log::info('Signed URL generated', ['url' => $signedUrl]);

            return response()->json([
                'message' => '圖片上傳成功！',
                'file_path' => $filePath, // GCS 上的實際路徑
                'url' => $signedUrl,      // 簽署的暫時網址，用於前端顯示
                'upload_result' => $uploadResult,
                'file_exists' => $exists,
            ], 200);
        } catch (\Exception $e) {
            // 記錄詳細錯誤
            Log::error('Image upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => '圖片上傳失敗。',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
