<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DivingOffer;
use Illuminate\Http\Request;

class AdminOfferController extends Controller
{
    private function checkAdmin()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['status' => false, 'message' => '無權限存取'], 403);
        }
        return null;
    }

    public function index(Request $request)
    {
        if ($err = $this->checkAdmin()) return $err;

        $query = DivingOffer::query();

        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            });
        }

        $paginated = $query->latest('id')->paginate(15);

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

    public function destroy(int $id)
    {
        if ($err = $this->checkAdmin()) return $err;

        $offer = DivingOffer::find($id);
        if (!$offer) {
            return response()->json(['status' => false, 'message' => '課程不存在'], 404);
        }

        $offer->delete();
        return response()->json(['status' => true, 'message' => '課程已刪除']);
    }
}
