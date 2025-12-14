<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
   public function show(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'balance' => $user->balance,
            'assets' => $user->assets->map(function ($asset) {
                return [
                    'symbol' => $asset->symbol,
                    'amount' => $asset->amount,
                    'locked_amount' => $asset->locked_amount,
                    'available' => bcsub($asset->amount, $asset->locked_amount, 8),
                ];
            }),
        ]);
    }
}
