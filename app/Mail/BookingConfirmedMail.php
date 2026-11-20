<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingConfirmedMail extends Mailable
{
    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ยืนยันการจองเรือ เลขจอง '.$this->booking->reference);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.booking-confirmed');
    }
}
