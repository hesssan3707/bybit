<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Trade;

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

        return view('pnl_history.index', compact('trades'));
    }
}
