<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function reportJournalIssue(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'لطفاً ابتدا وارد شوید.'], 401);
        }

        $title = 'My journal is not updating';
        $description = 'My journal is not updating';

        $exists = Ticket::where('user_id', $user->id)
            ->where('title', $title)
            ->where('description', $description)
            ->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'گزارش شما قبلاً ثبت شده است و امکان ثبت مجدد آن وجود ندارد.'
            ], 200);
        }

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'title' => $title,
            'description' => $description,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'گزارش شما با موفقیت ثبت شد. تیم پشتیبانی به‌زودی بررسی می‌کند.',
            'ticket_id' => $ticket->id,
        ], 200);
    }
}