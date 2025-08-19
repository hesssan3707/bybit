<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Trade;

class PnlHistoryController extends Controller
{
    public function index()
    {
        $closedPositions = Trade::latest('closed_at')->paginate(50);

        return view('pnl_history', ['positions' => $closedPositions]);
    }
}
