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

        $title = 'مشکل ژورنال';
        $description = 'ژورنال من کار نمی کند';

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
            'category' => 'issue',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'گزارش شما با موفقیت ثبت شد. تیم پشتیبانی به‌زودی بررسی می‌کند.',
            'ticket_id' => $ticket->id,
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:issue,suggestion',
        ]);

        $user = $request->user();

        Ticket::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'open',
            'category' => $request->category,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تیکت شما با موفقیت ثبت شد. نتیجه به ایمیل شما ارسال خواهد شد.',
        ]);
    }
}
