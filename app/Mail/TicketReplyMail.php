<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public User $admin;

    public function __construct(Ticket $ticket, User $admin)
    {
        $this->ticket = $ticket;
        $this->admin = $admin;
    }

    public function build()
    {
        return $this->subject('پاسخ به تیکت شما')
            ->view('emails.ticket_reply')
            ->with([
                'ticket' => $this->ticket,
                'admin' => $this->admin,
            ]);
    }
}