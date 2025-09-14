<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Trade;

class PnlHistoryController extends Controller
{
    public function index()
    {
        $trades = Trade::forUser(auth()->id())
            ->latest('closed_at')
            ->paginate(20);

        return view('futures.pnl_history', ['positions' => $trades]);
    }
}
