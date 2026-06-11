<?php

namespace App\Http\Controllers\API;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ProviderVerificationApprovedNotification;
use App\Notifications\ProviderVerificationRejectedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminVerificationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', VerificationStatus::Pending->value);

        $query = User::where('role', 'provider')
            ->with(['providerProfile', 'providerCertifications'])
            ->whereHas('providerProfile');

        if ($status !== 'all') {
            $query->whereHas('providerProfile', fn($q) => $q->where('verification_status', $status));
        }

        $providers = $query->orderByDesc('updated_at')->get()->map(fn($u) => [
            'user_id'             => $u->id,
            'name'                => $u->name,
            'email'               => $u->email,
            'business_name'       => $u->providerProfile->business_name,
            'verification_status' => $u->providerProfile->verification_status->value,
            'rejection_reason'    => $u->providerProfile->rejection_reason,
            'certifications'      => $u->providerCertifications->map(fn($c) => ['id' => $c->id, 'url' => $c->url])->values(),
        ]);

        return response()->json(['status' => true, 'data' => $providers]);
    }

    public function approve(Request $request, int $userId)
    {
        $profile = $this->findProviderProfile($userId);

        if (!$profile->verification_status->canTransitionTo(VerificationStatus::Approved)) {
            return response()->json(['status' => false, 'message' => '當前狀態無法通過審核'], 422);
        }

        $profile->update(['verification_status' => VerificationStatus::Approved, 'rejection_reason' => null]);

        // 驗證狀態影響公開課程可見性，需立即讓快取失效
        Cache::tags(['diving_offers'])->flush();

        $this->notify($profile->user, new ProviderVerificationApprovedNotification());

        return response()->json(['status' => true, 'message' => '教練已通過審核']);
    }

    public function reject(Request $request, int $userId)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);

        $profile = $this->findProviderProfile($userId);

        if (!$profile->verification_status->canTransitionTo(VerificationStatus::Rejected)) {
            return response()->json(['status' => false, 'message' => '當前狀態無法駁回'], 422);
        }

        $profile->update([
            'verification_status' => VerificationStatus::Rejected,
            'rejection_reason'    => $data['reason'],
        ]);

        Cache::tags(['diving_offers'])->flush();

        $this->notify($profile->user, new ProviderVerificationRejectedNotification($data['reason']));

        return response()->json(['status' => true, 'message' => '已駁回，教練將收到原因通知']);
    }

    private function findProviderProfile(int $userId)
    {
        return User::where('role', 'provider')->findOrFail($userId)->providerProfile()->firstOrFail();
    }

    private function notify(User $provider, object $notification): void
    {
        try {
            $provider->notify($notification);
        } catch (\Throwable $e) {
            Log::error('ProviderVerification notification failed: ' . $e->getMessage());
        }
    }
}
