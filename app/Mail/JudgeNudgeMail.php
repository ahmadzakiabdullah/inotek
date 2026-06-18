<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class JudgeNudgeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $judge;
    public Collection $pendingProjects;

    /**
     * Create a new message instance.
     */
    public function __construct(User $judge, Collection $pendingProjects)
    {
        $this->judge = $judge;
        $this->pendingProjects = $pendingProjects;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: Pending INOTEK Project Evaluations',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.judging-nudge',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
