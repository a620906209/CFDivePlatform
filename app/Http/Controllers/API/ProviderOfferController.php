<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DivingOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProviderOfferController extends Controller
{
    public function index()
    {
        $offers = DivingOffer::where('provider_id', auth()->id())
            ->paginate(12);

        return response()->json([
            'status' => true,
            'data'   => $offers->items(),
            'meta'   => [
                'total'        => $offers->total(),
                'per_page'     => $offers->perPage(),
                'current_page' => $offers->currentPage(),
                'last_page'    => $offers->lastPage(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $offer = DivingOffer::find($id);

        if (!$offer) {
            return response()->json(['status' => false, 'message' => '課程不存在'], 404);
        }

        if ($offer->provider_id !== auth()->id()) {
            return response()->json(['status' => false, 'message' => '無權限查看此課程'], 403);
        }

        return response()->json(['status' => true, 'data' => $offer]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'location'    => 'required|string|max:255',
            'spot'        => 'nullable|string|max:255',
            'price'       => 'required|integer|min:0',
            'region'      => 'required|string|max:100',
            'tag'         => 'nullable|string|max:100',
            'badges'      => 'nullable|array',
            'badges.*'    => 'string|max:50',
            'description' => 'nullable|string',
        ]);

        $validated['provider_id'] = auth()->id();
        $validated['rating']      = 0;
        $validated['reviews']     = 0;

        $offer = DivingOffer::create($validated);

        Cache::tags(['diving_offers'])->flush();

        return response()->json(['status' => true, 'data' => $offer], 201);
    }

    public function update(Request $request, int $id)
    {
        $offer = DivingOffer::find($id);

        if (!$offer) {
            return response()->json(['status' => false, 'message' => '課程不存在'], 404);
        }

        if ($offer->provider_id !== auth()->id()) {
            return response()->json(['status' => false, 'message' => '無權限修改此課程'], 403);
        }

        $validated = $request->validate([
            'title'       => 'nullable|string|max:255',
            'location'    => 'nullable|string|max:255',
            'spot'        => 'nullable|string|max:255',
            'price'       => 'nullable|integer|min:0',
            'region'      => 'nullable|string|max:100',
            'tag'         => 'nullable|string|max:100',
            'badges'      => 'nullable|array',
            'badges.*'    => 'string|max:50',
            'description' => 'nullable|string',
        ]);

        $offer->fill($validated)->save();

        Cache::tags(['diving_offers'])->flush();

        return response()->json(['status' => true, 'data' => $offer]);
    }

    public function destroy(int $id)
    {
        $offer = DivingOffer::find($id);

        if (!$offer) {
            return response()->json(['status' => false, 'message' => '課程不存在'], 404);
        }

        if ($offer->provider_id !== auth()->id()) {
            return response()->json(['status' => false, 'message' => '無權限刪除此課程'], 403);
        }

        $offer->delete();

        Cache::tags(['diving_offers'])->flush();

        return response()->json(['status' => true, 'message' => '課程已刪除']);
    }
}
