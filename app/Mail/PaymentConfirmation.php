<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\AccountsReceivable;
use App\Models\Payment;

class PaymentConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $ar;
    public $payment;
    public $booking;

    public function __construct(AccountsReceivable $ar, Payment $payment)
    {
        $this->ar = $ar;
        $this->payment = $payment;
        $this->booking = $ar->booking;
    }

    public function build()
    {
        return $this->subject('Payment Confirmation - XMFFI')
                    ->view('emails.payment-confirmation')
                    ->with([
                        'ar' => $this->ar,
                        'payment' => $this->payment,
                        'booking' => $this->booking,
                        'user' => $this->booking->user,
                    ]);
    }
}