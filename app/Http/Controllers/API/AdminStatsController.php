<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DivingOffer;
use App\Models\User;

class AdminStatsController extends Controller
{
    public function index()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['status' => false, 'message' => '無權限存取'], 403);
        }

        return response()->json([
            'status' => true,
            'data'   => [
                'total_members'   => User::where('role', 'member')->count(),
                'total_providers' => User::where('role', 'provider')->count(),
                'total_offers'    => DivingOffer::count(),
            ],
        ]);
    }
}
