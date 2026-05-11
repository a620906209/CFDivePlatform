<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CourseImage;
use App\Models\DivingOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseImageController extends Controller
{
    private function validateImage(Request $request): void
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
    }

    private function authorizeOffer(Request $request, DivingOffer $offer): void
    {
        if ($offer->provider_id !== $request->user()->id) {
            abort(403, '無權操作此課程');
        }
    }

    public function uploadCover(Request $request, int $offerId)
    {
        $offer = DivingOffer::findOrFail($offerId);
        $this->authorizeOffer($request, $offer);
        $this->validateImage($request);

        if ($offer->cover_image) {
            Storage::disk('public')->delete($offer->cover_image);
        }

        $path = $request->file('image')->store("offers/{$offerId}/cover", 'public');
        $offer->update(['cover_image' => $path]);

        return response()->json([
            'status'          => true,
            'message'         => '封面已上傳',
            'cover_image_url' => $offer->cover_image_url,
        ]);
    }

    public function deleteCover(Request $request, int $offerId)
    {
        $offer = DivingOffer::findOrFail($offerId);
        $this->authorizeOffer($request, $offer);

        if ($offer->cover_image) {
            Storage::disk('public')->delete($offer->cover_image);
            $offer->update(['cover_image' => null]);
        }

        return response()->json(['status' => true, 'message' => '封面已刪除']);
    }

    public function uploadImage(Request $request, int $offerId)
    {
        $offer = DivingOffer::findOrFail($offerId);
        $this->authorizeOffer($request, $offer);
        $this->validateImage($request);

        if ($offer->courseImages()->count() >= 3) {
            return response()->json(['status' => false, 'message' => '相簿最多 3 張圖片'], 422);
        }

        $path       = $request->file('image')->store("offers/{$offerId}/gallery", 'public');
        $sortOrder  = ($offer->courseImages()->max('sort_order') ?? 0) + 1;

        $image = CourseImage::create([
            'diving_offer_id' => $offerId,
            'image_path'      => $path,
            'sort_order'      => $sortOrder,
        ]);

        return response()->json([
            'status'  => true,
            'message' => '圖片已上傳',
            'data'    => ['id' => $image->id, 'url' => $image->url, 'sort_order' => $image->sort_order],
        ], 201);
    }

    public function deleteImage(Request $request, int $imageId)
    {
        $image = CourseImage::with('divingOffer')->findOrFail($imageId);

        if ($image->divingOffer->provider_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => '無權刪除此圖片'], 403);
        }

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return response()->json(['status' => true, 'message' => '圖片已刪除']);
    }
}
