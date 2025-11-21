<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\AccountsReceivable;

class PaymentRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $ar;
    public $booking;

    public function __construct(AccountsReceivable $ar)
    {
        $this->ar = $ar;
        $this->booking = $ar->booking;
    }

    public function build()
    {
        return $this->subject('Payment Request - Booking #' . $this->booking->booking_number)
                    ->view('emails.payment-request')
                    ->with([
                        'ar' => $this->ar,
                        'booking' => $this->booking,
                        'user' => $this->booking->user,
                    ]);
    }
}