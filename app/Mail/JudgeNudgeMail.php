<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JudgeNudgeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $judge;

    /** @var list<array{pcode: string|null, title: string, category_name: string, round_no: int}> */
    public array $pendingProjects;

    /**
     * Create a new message instance.
     */
    /** @param list<array{pcode: string|null, title: string, category_name: string, round_no: int}> $pendingProjects */
    public function __construct(User $judge, array $pendingProjects)
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
    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [];
    }
}
