<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DivingOffer;
use Illuminate\Http\Request;

class DivingOfferController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 12), 50);

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

        return response()->json([
            'status' => true,
            'data'   => $paginated->items(),
            'meta'   => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $offer = DivingOffer::find($id);

        if (!$offer) {
            return response()->json([
                'status'  => false,
                'message' => '課程不存在',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $offer,
        ]);
    }
}
