<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** ส่งตอนสร้างการจอง: เลขอ้างอิง ลิงก์หน้าสถานะ ยอดเงิน และเวลาหมดอายุ */
class BookingReceivedMail extends Mailable
{
    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'รับคำขอจองแล้ว เลขอ้างอิง '.$this->booking->reference.' — กรุณาแจ้งโอนภายในเวลาที่กำหนด');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.booking-received');
    }
}
