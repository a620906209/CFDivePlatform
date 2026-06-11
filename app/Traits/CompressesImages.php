<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

trait CompressesImages
{
    /**
     * 壓縮上傳圖片並存入 public disk，回傳相對路徑。
     * 參數與聊天圖片管線一致（scaleDown 2048 + JPEG quality 85），
     * 確保平台內影像處理行為單一來源。
     */
    private function compressToJpeg(UploadedFile $file, string $directory): string
    {
        $manager = new ImageManager(new Driver());
        $image   = $manager->read($file);

        if ($image->width() > 2048 || $image->height() > 2048) {
            $image->scaleDown(width: 2048, height: 2048);
        }

        $path = trim($directory, '/') . '/' . Str::uuid() . '.jpg';

        Storage::disk('public')->put($path, $image->toJpeg(quality: 85));

        return $path;
    }
}
