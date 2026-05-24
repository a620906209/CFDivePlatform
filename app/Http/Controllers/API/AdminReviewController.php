<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DivingOffer;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReviewController extends Controller
{
    public function index(Request $request)
    {
        $perPage   = min((int) $request->query('per_page', 20), 100);
        $paginator = Review::with(['divingOffer', 'member'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $reviews = $paginator->getCollection()->map(fn($r) => [
            'id'           => $r->id,
            'offer_title'  => $r->divingOffer?->title,
            'member_email' => $r->member?->email,
            'rating'       => $r->rating,
            'comment'      => mb_strimwidth($r->comment, 0, 50, '...'),
            'is_edited'    => $r->is_edited,
            'helpful_count'=> $r->helpful_count,
            'created_at'   => $r->created_at?->toISOString(),
        ]);

        return response()->json([
            'status' => true,
            'data'   => $reviews,
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function destroy(int $id)
    {
        $review  = Review::findOrFail($id);
        $offerId = $review->diving_offer_id;

        DB::transaction(function () use ($review, $offerId) {
            $review->delete();
            $this->recalculateOfferRating($offerId);
        });

        return response()->json(['status' => true, 'message' => '評價已刪除']);
    }

    private function recalculateOfferRating(int $offerId): void
    {
        $stats = Review::where('diving_offer_id', $offerId)
            ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total')
            ->first();

        DivingOffer::where('id', $offerId)->update([
            'rating'  => $stats->total > 0 ? $stats->avg_rating : 0,
            'reviews' => $stats->total,
        ]);
    }
}
