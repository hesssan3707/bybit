<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use Illuminate\Http\Request;

class PnlHistoryController extends Controller
{
    public function index()
    {
        $trades = Trade::forUser(auth()->id())
            ->latest('closed_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $trades]);
    }
}
