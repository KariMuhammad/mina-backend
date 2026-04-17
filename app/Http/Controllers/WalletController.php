<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function wallet(Request $request)
    {
        return response()->json(['wallet_balance' => $request->user()->wallet_balance]);
    }

    public function loyaltyPoints(Request $request)
    {
        return response()->json(['loyalty_points' => $request->user()->loyalty_points]);
    }
}
