<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ClosedPosition;

class PnlHistoryController extends Controller
{
    public function index()
    {
        $closedPositions = ClosedPosition::latest('closed_at')->paginate(50);

        return view('pnl_history', ['positions' => $closedPositions]);
    }
}
