<?php

namespace App\Http\Controllers\API;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\ProviderCertification;
use App\Traits\CompressesImages;
use Illuminate\Http\Request;

class ProviderVerificationController extends Controller
{
    use CompressesImages;

    private const MAX_CERTIFICATIONS = 3;

    public function show(Request $request)
    {
        $profile = $request->user()->providerProfile;

        return response()->json([
            'status' => true,
            'data'   => [
                'verification_status' => $profile->verification_status->value,
                'rejection_reason'    => $profile->rejection_reason,
                'certifications'      => $request->user()->providerCertifications
                    ->map(fn($c) => ['id' => $c->id, 'url' => $c->url])->values(),
            ],
        ]);
    }

    public function uploadCertification(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        if ($err = $this->ensureCertificationsEditable($request)) {
            return $err;
        }

        $user = $request->user();

        if ($user->providerCertifications()->count() >= self::MAX_CERTIFICATIONS) {
            return response()->json(['status' => false, 'message' => '證照最多 3 張'], 422);
        }

        $path = $this->compressToJpeg($request->file('image'), "providers/{$user->id}/certifications");

        $certification = ProviderCertification::create([
            'user_id'    => $user->id,
            'image_path' => $path,
        ]);

        return response()->json([
            'status'  => true,
            'message' => '證照已上傳',
            'data'    => ['id' => $certification->id, 'url' => $certification->url],
        ], 201);
    }

    public function deleteCertification(Request $request, int $id)
    {
        $certification = ProviderCertification::findOrFail($id);

        if ($certification->user_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => '無權刪除此證照'], 403);
        }

        if ($err = $this->ensureCertificationsEditable($request)) {
            return $err;
        }

        $certification->delete();

        return response()->json(['status' => true, 'message' => '證照已刪除']);
    }

    public function submit(Request $request)
    {
        $user    = $request->user();
        $profile = $user->providerProfile;

        if (!$profile->verification_status->canTransitionTo(VerificationStatus::Pending)) {
            return response()->json(['status' => false, 'message' => '當前狀態無法送審'], 422);
        }

        if ($user->providerCertifications()->count() < 1) {
            return response()->json(['status' => false, 'message' => '請先上傳至少 1 張證照'], 422);
        }

        $profile->update([
            'verification_status' => VerificationStatus::Pending,
            'rejection_reason'    => null,
        ]);

        return response()->json(['status' => true, 'message' => '已送出審核，請等待平台審核結果']);
    }

    /**
     * 證照僅於 unsubmitted / rejected 可增刪：pending / approved 是審核依據，不可變動
     */
    private function ensureCertificationsEditable(Request $request)
    {
        $status = $request->user()->providerProfile->verification_status;

        if (!in_array($status, [VerificationStatus::Unsubmitted, VerificationStatus::Rejected])) {
            return response()->json(['status' => false, 'message' => '審核期間或通過後不可變更證照'], 422);
        }

        return null;
    }
}
