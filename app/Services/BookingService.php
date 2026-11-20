<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\BookingLeg;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BookingService
{
    /**
     * สร้างการจอง และกันที่นั่งครบทุกขาในธุรกรรมเดียว
     * $data: outbound_trip_id, return_trip_id?, passengers, contact_name, contact_phone, contact_email, idempotency_key
     */
    public function create(array $data): Booking
    {
        $this->expireDue();

        if ($existing = Booking::where('idempotency_key', $data['idempotency_key'])->first()) {
            return $existing;
        }

        $passengers = (int) $data['passengers'];
        if ($passengers < 1 || $passengers > config('ferry.max_passengers')) {
            throw new BookingException('จำนวนผู้โดยสารต้องอยู่ระหว่าง 1 ถึง '.config('ferry.max_passengers').' คน');
        }

        try {
            return DB::transaction(function () use ($data, $passengers) {
                $outbound = Trip::with('route')->find($data['outbound_trip_id'])
                    ?? throw new BookingException('ไม่พบรอบเรือขาไป', 'outbound');
                $return = null;
                if (! empty($data['return_trip_id'])) {
                    $return = Trip::with('route')->find($data['return_trip_id'])
                        ?? throw new BookingException('ไม่พบรอบเรือขากลับ', 'return');
                    $this->assertReturnValid($outbound, $return);
                }

                // กันที่นั่งเรียงตาม id เพื่อกัน deadlock เมื่อมีหลายคำขอพร้อมกัน
                $legs = collect(['outbound' => $outbound, 'return' => $return])->filter()
                    ->sortBy(fn (Trip $t) => $t->id);

                foreach ($legs as $direction => $trip) {
                    $this->takeSeats($trip, $passengers, $direction);
                }

                $booking = Booking::create([
                    'reference' => $this->newReference(),
                    'access_token' => Str::random(48),
                    'idempotency_key' => $data['idempotency_key'],
                    'trip_type' => $return ? 'round' : 'oneway',
                    'contact_name' => trim($data['contact_name']),
                    'contact_phone' => trim($data['contact_phone']),
                    'contact_email' => trim($data['contact_email']),
                    'passengers' => $passengers,
                    'total_amount' => 0,
                    'expires_at' => now()->addMinutes(config('ferry.hold_minutes')),
                ]);

                $total = 0;
                foreach (['outbound' => $outbound, 'return' => $return] as $direction => $trip) {
                    if (! $trip) {
                        continue;
                    }
                    $amount = $trip->price * $passengers; // คำนวณฝั่งเซิร์ฟเวอร์เสมอ
                    BookingLeg::create([
                        'booking_id' => $booking->id, 'direction' => $direction, 'trip_id' => $trip->id,
                        'unit_price' => $trip->price, 'passengers' => $passengers, 'amount' => $amount,
                    ]);
                    $total += $amount;
                }
                $booking->update(['total_amount' => $total]);

                return $booking->fresh('legs');
            });
        } catch (UniqueConstraintViolationException $e) {
            // ส่งซ้ำพร้อมกัน: อีกคำขอสร้างเลขจองไปแล้ว ให้คืนเลขเดิม
            if ($existing = Booking::where('idempotency_key', $data['idempotency_key'])->first()) {
                return $existing;
            }
            throw $e;
        }
    }

    private function assertReturnValid(Trip $outbound, Trip $return): void
    {
        $o = $outbound->route;
        $r = $return->route;
        if ($r->origin_port_id !== $o->destination_port_id || $r->destination_port_id !== $o->origin_port_id) {
            throw new BookingException('เส้นทางขากลับไม่ตรงกับขาไป', 'return');
        }
        if ($return->departs_at->lt($outbound->arrives_at)) {
            throw new BookingException('รอบขากลับต้องออกเดินทางหลังเรือขาไปถึงปลายทาง', 'return');
        }
    }

    /** กันที่นั่งแบบ atomic: สำเร็จเฉพาะเมื่อยังเหลือพอ จึงไม่มีทางจองเกิน */
    private function takeSeats(Trip $trip, int $passengers, string $direction): void
    {
        $ok = Trip::where('id', $trip->id)
            ->where('status', 'open')
            ->where('departs_at', '>', now())
            ->whereRaw('seats_taken + ? <= capacity', [$passengers])
            ->update(['seats_taken' => DB::raw('seats_taken + '.$passengers)]);

        if ($ok !== 1) {
            $label = $direction === 'return' ? 'ขากลับ' : 'ขาไป';
            throw new BookingException("รอบเรือ{$label}ที่นั่งไม่เพียงพอหรือปิดจองแล้ว กรุณาเลือกรอบใหม่", $direction);
        }
    }

    private function newReference(): string
    {
        do {
            $ref = 'FB'.now()->format('ymd').'-'.strtoupper(Str::random(5));
            $ref = strtr($ref, ['O' => '7', 'I' => '8', 'L' => '9', '0' => '6', '1' => '5']);
        } while (Booking::where('reference', $ref)->exists());

        return $ref;
    }

    /** คืนที่นั่งครบทุกขา "ครั้งเดียว" ไม่ว่าจะถูกเรียกซ้ำกี่ครั้ง */
    public function release(Booking $booking, string $newStatus): bool
    {
        return DB::transaction(function () use ($booking, $newStatus) {
            $claimed = Booking::where('id', $booking->id)
                ->whereNull('seats_released_at')
                ->whereIn('booking_status', ['pending', 'confirmed'])
                ->update(['booking_status' => $newStatus, 'seats_released_at' => now()]);

            if ($claimed !== 1) {
                return false;
            }

            foreach (BookingLeg::where('booking_id', $booking->id)->orderBy('trip_id')->get() as $leg) {
                $n = (int) $leg->passengers;
                Trip::where('id', $leg->trip_id)->update([
                    'seats_taken' => DB::raw("CASE WHEN seats_taken >= {$n} THEN seats_taken - {$n} ELSE 0 END"),
                ]);
            }

            return true;
        });
    }

    /** ปล่อยที่นั่งของรายการที่เลยเวลาโดยยังไม่แจ้งโอน (รายการที่แนบสลิปแล้วไม่ถูกหมดเวลา) */
    public function expireDue(): int
    {
        $count = 0;
        Booking::where('booking_status', 'pending')
            ->whereIn('payment_status', ['unpaid', 'rejected'])
            ->where('expires_at', '<', now())
            ->whereNull('seats_released_at')
            ->each(function (Booking $b) use (&$count) {
                $count += (int) $this->release($b, 'expired');
            });

        return $count;
    }

    public function cancel(Booking $booking, User $by, ?string $reason = null): void
    {
        if (! in_array($booking->booking_status, ['pending', 'confirmed'], true)) {
            throw new BookingException('รายการนี้ยกเลิกไม่ได้แล้ว');
        }
        $this->release($booking, 'cancelled');
        Booking::where('id', $booking->id)->update(['staff_note' => $reason]);
    }

    public function submitPayment(Booking $booking, int $amount, CarbonInterface $transferredAt, UploadedFile $slip): Payment
    {
        return DB::transaction(function () use ($booking, $amount, $transferredAt, $slip) {
            // ปิดสถานะก่อนบันทึก เพื่อกันการกดส่งซ้ำ
            $claimed = Booking::where('id', $booking->id)
                ->where('booking_status', 'pending')
                ->whereIn('payment_status', ['unpaid', 'rejected'])
                ->where('expires_at', '>', now())
                ->update(['payment_status' => 'submitted']);

            if ($claimed !== 1) {
                throw new BookingException('รายการนี้ไม่สามารถแจ้งโอนได้ (หมดเวลา ยกเลิก หรือแจ้งโอนแล้ว)');
            }

            $path = $slip->store('slips/'.$booking->reference, 'local'); // ส่วนตัว ไม่เปิดสาธารณะ

            return Payment::create([
                'booking_id' => $booking->id,
                'reported_amount' => $amount,
                'transferred_at' => $transferredAt,
                'slip_path' => $path,
            ]);
        });
    }

    /** ทีมงานยืนยันหลังตรวจว่าเงินเข้าบัญชีแล้ว (ยืนยันได้แม้เลยเวลาโอน ถ้ามีสลิปส่งทัน) */
    public function confirm(Booking $booking, User $by): void
    {
        DB::transaction(function () use ($booking, $by) {
            $claimed = Booking::where('id', $booking->id)
                ->where('booking_status', 'pending')
                ->where('payment_status', 'submitted')
                ->update(['booking_status' => 'confirmed', 'payment_status' => 'verified', 'confirmed_at' => now(), 'expires_at' => null]);

            if ($claimed !== 1) {
                throw new BookingException('ยืนยันไม่ได้: รายการต้องอยู่ในสถานะรอตรวจยอด');
            }

            Payment::where('booking_id', $booking->id)->where('status', 'submitted')
                ->update(['status' => 'verified', 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
        });

        Mail::to($booking->contact_email)->send(new BookingConfirmedMail($booking->fresh(['legs.trip.route.origin', 'legs.trip.route.destination'])));
    }

    /** ยอดไม่ตรง: บันทึกเหตุผลและเปิดให้ลูกค้าส่งหลักฐานใหม่ */
    public function requestNewEvidence(Booking $booking, User $by, string $reason): void
    {
        if (trim($reason) === '') {
            throw new BookingException('กรุณาระบุเหตุผลที่ขอหลักฐานใหม่');
        }

        DB::transaction(function () use ($booking, $by, $reason) {
            $claimed = Booking::where('id', $booking->id)
                ->where('booking_status', 'pending')
                ->where('payment_status', 'submitted')
                ->update([
                    'payment_status' => 'rejected',
                    'expires_at' => now()->addMinutes(config('ferry.evidence_minutes')),
                ]);

            if ($claimed !== 1) {
                throw new BookingException('ทำรายการไม่ได้: รายการต้องอยู่ในสถานะรอตรวจยอด');
            }

            Payment::where('booking_id', $booking->id)->where('status', 'submitted')
                ->update(['status' => 'rejected', 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'review_note' => $reason]);
        });
    }

    /** ข้อความสำหรับเจ้าหน้าที่คัดลอกไปส่งทาง LINE */
    public function lineMessage(Booking $booking): string
    {
        $booking->loadMissing('legs.trip.route.origin', 'legs.trip.route.destination');
        $lines = ['เรือไปเกาะ ยืนยันการจองแล้ว ✅', "เลขจอง: {$booking->reference}", "ผู้จอง: {$booking->contact_name}", "จำนวน: {$booking->passengers} คน"];
        foreach ($booking->legs as $leg) {
            $t = $leg->trip;
            $lines[] = ($leg->direction === 'return' ? 'ขากลับ' : 'ขาไป').': '.thai_datetime($t->departs_at).' '.$t->route->label;
            $lines[] = '  ท่าขึ้นเรือ: '.$t->route->origin->name.($t->route->origin->boarding_point ? ' ('.$t->route->origin->boarding_point.')' : '');
        }
        $lines[] = 'ยอดรวม: '.number_format($booking->total_amount).' บาท';
        $lines[] = 'กรุณามาถึงท่าก่อนเวลาเรือออกอย่างน้อย 30 นาที';

        return implode("\n", $lines);
    }
}
