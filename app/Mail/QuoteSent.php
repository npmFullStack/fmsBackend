<?php
// app/Mail/QuoteSent.php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteSent extends Mailable
{
    use Queueable, SerializesModels;

    public $quote;

    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
    }

    public function build()
    {
        return $this->subject("Your Shipping Quote - {$this->quote->first_name} {$this->quote->last_name}")
            ->view('emails.quote-sent')
            ->with([
                'quote' => $this->quote,
            ]);
    }
}