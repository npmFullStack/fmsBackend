<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;

class BookingApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;
    public $password;
    public $user;

    public function __construct(Booking $booking, $password)
    {
        $this->booking = $booking;
        $this->password = $password;
        
        // Create or get user with customer role
        $this->user = User::firstOrCreate(
            ['email' => $booking->email],
            [
                'first_name' => $booking->first_name,
                'last_name' => $booking->last_name,
                'password' => Hash::make($password),
                'role' => 'customer', // Set as customer
                'contact_number' => $booking->contact_number,
            ]
        );
    }

    public function build()
    {
        return $this->subject("Your Booking Has Been Approved!")
            ->view('emails.booking-approved')
            ->with([
                'booking' => $this->booking,
                'password' => $this->password,
                'user' => $this->user,
            ]);
    }
}