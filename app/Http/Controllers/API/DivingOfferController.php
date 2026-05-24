<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DivingOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DivingOfferController extends Controller
{
    public function index(Request $request)
    {
        $perPage  = min((int) $request->query('per_page', 12), 50);
        $cacheKey = 'diving_offers_' . md5(serialize($request->all()));

        $result = Cache::tags(['diving_offers'])->remember($cacheKey, 180, function () use ($request, $perPage) {
            $query = DivingOffer::query();

            if ($q = $request->query('q')) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', "%{$q}%")
                        ->orWhere('location', 'like', "%{$q}%")
                        ->orWhere('spot', 'like', "%{$q}%");
                });
            }

            if ($region = $request->query('region')) {
                $query->where('region', $region);
            }

            if ($tag = $request->query('tag')) {
                $query->where('tag', 'like', "%{$tag}%");
            }

            $paginated = $query->paginate($perPage);

            return [
                'items' => collect($paginated->items())->map(fn($o) => $this->formatOffer($o, false))->values(),
                'meta'  => [
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $result['items'],
            'meta'   => $result['meta'],
        ]);
    }

    public function show(int $id)
    {
        $offer = DivingOffer::with('courseImages')->find($id);

        if (!$offer) {
            return response()->json(['status' => false, 'message' => '課程不存在'], 404);
        }

        return response()->json(['status' => true, 'data' => $this->formatOffer($offer, true)]);
    }

    private function formatOffer(DivingOffer $offer, bool $withImages): array
    {
        $data = array_merge($offer->toArray(), [
            'cover_image_url' => $offer->cover_image_url,
        ]);

        if ($withImages) {
            $data['images'] = $offer->courseImages->map(fn($img) => [
                'id'         => $img->id,
                'url'        => $img->url,
                'sort_order' => $img->sort_order,
            ])->values();
        }

        return $data;
    }
}
