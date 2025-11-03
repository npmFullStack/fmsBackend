<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;
    public $password;

    public function __construct(Booking $booking, $password)
    {
        $this->booking = $booking;
        $this->password = $password;
    }

    public function build()
    {
        return $this->subject('Your Booking Has Been Approved!')
                    ->view('emails.booking-approved')
                    ->with([
                        'booking' => $this->booking,
                        'password' => $this->password,
                    ]);
    }
}