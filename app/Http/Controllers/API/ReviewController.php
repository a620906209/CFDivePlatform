<?php

namespace App\Http\Controllers\API;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DivingOffer;
use App\Models\Review;
use App\Models\ReviewEdit;
use App\Models\ReviewVote;
use App\Notifications\ReviewReceivedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    // ── 公開列表 ──────────────────────────────────────────────

    public function publicList(Request $request, int $offerId)
    {
        $offer   = DivingOffer::findOrFail($offerId);
        $user    = $request->user();
        $perPage = min((int) $request->query('per_page', 20), 50);
        $sort    = $request->query('sort', 'helpful');

        $query = Review::with('votes')->where('diving_offer_id', $offer->id);

        match ($sort) {
            'rating'  => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'newest'  => $query->orderByDesc('created_at'),
            default   => $query->orderByDesc('helpful_count')->orderByDesc('created_at'),
        };

        $paginator = $query->paginate($perPage);
        $reviews   = $paginator->getCollection();

        $memberId = $user?->id;

        $distribution = Cache::remember("offer_review_distribution_{$offerId}", 600, function () use ($offerId) {
            $distRaw = Review::where('diving_offer_id', $offerId)
                ->selectRaw('rating, COUNT(*) as cnt')
                ->groupBy('rating')
                ->pluck('cnt', 'rating');
            return collect([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0])->merge($distRaw);
        });

        $total   = $paginator->total();
        $average = $total > 0 ? round(
            Review::where('diving_offer_id', $offerId)->avg('rating'), 1
        ) : 0;

        $formatted = $reviews->map(function ($r) use ($user, $memberId) {
            $item = [
                'id'            => $r->id,
                'reviewer_name' => '匿名潛水者',
                'rating'        => $r->rating,
                'comment'       => $r->comment,
                'helpful_count' => $r->helpful_count,
                'is_edited'     => $r->is_edited,
                'created_at'    => $r->created_at?->toISOString(),
                'has_voted'     => $memberId
                    ? $r->votes->contains('member_id', $memberId)
                    : false,
            ];
            if ($user) {
                $item['is_mine'] = $r->member_id === $memberId;
            }
            return $item;
        });

        return response()->json([
            'status' => true,
            'data'   => [
                'summary' => [
                    'average'      => $average,
                    'total'        => $total,
                    'distribution' => $distribution,
                ],
                'reviews' => $formatted,
                'meta'    => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                ],
            ],
        ]);
    }

    // ── Member CRUD ───────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'diving_offer_id' => 'required|integer|exists:diving_offers,id',
            'rating'          => 'required|integer|min:1|max:5',
            'comment'         => 'required|string|min:1',
        ]);

        $memberId = $request->user()->id;
        $offerId  = $data['diving_offer_id'];

        // 資格驗證：有 completed booking
        $eligible = Booking::where('member_id', $memberId)
            ->whereHas('schedule', fn($q) => $q->where('diving_offer_id', $offerId))
            ->where('status', BookingStatus::Completed->value)
            ->exists();

        if (!$eligible) {
            return response()->json(['status' => false, 'message' => '須完成此課程後才能評價'], 403);
        }

        // 重複評價檢查
        if (Review::where('member_id', $memberId)->where('diving_offer_id', $offerId)->exists()) {
            return response()->json(['status' => false, 'message' => '已評價，如需修改請使用編輯功能'], 422);
        }

        $review = DB::transaction(function () use ($data, $memberId, $offerId) {
            $review = Review::create([
                'diving_offer_id' => $offerId,
                'member_id'       => $memberId,
                'rating'          => $data['rating'],
                'comment'         => $data['comment'],
            ]);
            $this->recalculateOfferRating($offerId);
            return $review;
        });

        Cache::forget("offer_review_distribution_{$offerId}");

        try {
            $offer    = DivingOffer::with('provider')->findOrFail($offerId);
            $provider = $offer->provider;
            if ($provider) {
                $provider->notify(new ReviewReceivedNotification($review));
            }
        } catch (\Throwable $e) {
            Log::error('ReviewReceivedNotification failed: ' . $e->getMessage());
        }

        return response()->json(['status' => true, 'message' => '評價已送出', 'data' => $this->formatReview($review)], 201);
    }

    public function update(Request $request, int $id)
    {
        $review = Review::findOrFail($id);
        if ($review->member_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => '無權修改此評價'], 403);
        }

        $data = $request->validate([
            'rating'  => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|string|min:1',
        ]);

        $offerId = $review->diving_offer_id;

        DB::transaction(function () use ($review, $data) {
            ReviewEdit::updateOrCreate(
                ['review_id' => $review->id],
                ['old_rating' => $review->rating, 'old_comment' => $review->comment, 'edited_at' => now()]
            );
            $review->update(array_merge($data, ['is_edited' => true]));
            $this->recalculateOfferRating($review->diving_offer_id);
        });

        Cache::forget("offer_review_distribution_{$offerId}");

        return response()->json(['status' => true, 'message' => '評價已更新', 'data' => $this->formatReview($review->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $review = Review::findOrFail($id);
        if ($review->member_id !== $request->user()->id) {
            return response()->json(['status' => false, 'message' => '無權刪除此評價'], 403);
        }

        $offerId = $review->diving_offer_id;
        DB::transaction(function () use ($review, $offerId) {
            $review->delete();
            $this->recalculateOfferRating($offerId);
        });

        Cache::forget("offer_review_distribution_{$offerId}");

        return response()->json(['status' => true, 'message' => '評價已刪除']);
    }

    // ── 有幫助投票 ────────────────────────────────────────────

    public function toggleHelpful(Request $request, int $id)
    {
        if (!$request->user()->isMember()) {
            return response()->json(['status' => false, 'message' => '只有會員可以投票'], 403);
        }

        $review = Review::findOrFail($id);
        $memberId = $request->user()->id;

        if ($review->member_id === $memberId) {
            return response()->json(['status' => false, 'message' => '不可對自己的評價投票'], 422);
        }

        DB::transaction(function () use ($review, $memberId) {
            $vote = ReviewVote::where('review_id', $review->id)
                ->where('member_id', $memberId)
                ->first();

            if ($vote) {
                $vote->delete();
                DB::table('reviews')
                    ->where('id', $review->id)
                    ->where('helpful_count', '>', 0)
                    ->decrement('helpful_count');
            } else {
                ReviewVote::create(['review_id' => $review->id, 'member_id' => $memberId, 'created_at' => now()]);
                $review->increment('helpful_count');
            }
        });

        $review->refresh();
        $hasVoted = ReviewVote::where('review_id', $review->id)->where('member_id', $memberId)->exists();

        return response()->json(['status' => true, 'data' => ['helpful_count' => $review->helpful_count, 'has_voted' => $hasVoted]]);
    }

    // ── 私有方法 ──────────────────────────────────────────────

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

    private function formatReview(Review $r): array
    {
        return [
            'id'            => $r->id,
            'reviewer_name' => '匿名潛水者',
            'rating'        => $r->rating,
            'comment'       => $r->comment,
            'helpful_count' => $r->helpful_count,
            'is_edited'     => $r->is_edited,
            'created_at'    => $r->created_at?->toISOString(),
        ];
    }
}
