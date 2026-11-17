<?php

namespace App\Notifications;

use App\Models\Quote;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to admins when a client accepts, declines or asks for changes. */
class QuoteAnswered extends Notification
{
    private const WORDS = ['accepted' => 'accepted', 'declined' => 'declined', 'changes_requested' => 'asked for changes to', 'renewal' => 'wants to accept expired'];

    public function __construct(private Quote $quote, private string $answer) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function sentence(): string
    {
        return "{$this->quote->client->business_name} ".(self::WORDS[$this->answer] ?? $this->answer)." quote {$this->quote->reference()}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->sentence())->line($this->sentence().'.');

        if ($this->quote->client_message) {
            $mail->line('Their message: "'.$this->quote->client_message.'"');
        }

        return $mail->action('Open quote', route('admin.quotes.show', $this->quote));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->sentence(), 'body' => $this->quote->client_message, 'url' => route('admin.quotes.show', $this->quote, false)];
    }
}
