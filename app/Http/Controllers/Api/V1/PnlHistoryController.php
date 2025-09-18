<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use Illuminate\Http\Request;

class PnlHistoryController extends Controller
{
    public function index()
    {
        $tradesQuery = Trade::forUser(auth()->id());
        
        // Filter by current account type (demo/real)
        $user = auth()->user();
        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
        if ($currentExchange) {
            $tradesQuery->accountType($currentExchange->is_demo_active);
        }
        
        $trades = $tradesQuery->latest('closed_at')->paginate(20);

        return response()->json(['success' => true, 'data' => $trades]);
    }
}
