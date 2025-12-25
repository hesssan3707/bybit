<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\TicketReplyMail;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with(['user', 'admin'])->orderByDesc('created_at')->paginate(20);
        return view('admin.tickets.index', compact('tickets'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $request->validate([
            'reply' => 'required|string|min:2',
        ]);
        $ticket->reply = $request->input('reply');
        $ticket->replied_by = $request->user()->id;
        // Do NOT close on reply
        $ticket->save();

        // Send email to ticket owner; further conversation continues via email
        if ($ticket->user && $ticket->user->email) {
            try {
                Mail::to($ticket->user->email)->send(new TicketReplyMail($ticket, $request->user()));
            } catch (\Throwable $e) {
                // Silently ignore mail errors but keep admin informed
                return redirect()->route('admin.tickets')->with('success', 'پاسخ شما ثبت شد. ارسال ایمیل با خطا مواجه شد.');
            }
        }
        return redirect()->route('admin.tickets')->with('success', 'پاسخ شما ثبت شد. ادامه گفتگو از طریق ایمیل انجام خواهد شد.');
    }

    public function close(Request $request, Ticket $ticket)
    {
        $ticket->status = 'closed';
        $ticket->replied_by = $request->user()->id;
        $ticket->save();
        return redirect()->route('admin.tickets')->with('success', 'تیکت بسته شد.');
    }
}
