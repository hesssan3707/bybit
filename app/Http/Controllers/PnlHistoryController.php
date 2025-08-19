<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Trade;

class PnlHistoryController extends Controller
{
    public function index()
    {
        $closedPositions = ClosedPosition::latest('closed_at')->paginate(20);
        return view('pnl_history', ['positions' => $closedPositions]);
    }
}
