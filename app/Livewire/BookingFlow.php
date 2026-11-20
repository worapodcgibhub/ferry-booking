<?php

namespace App\Livewire;

use App\Exceptions\BookingException;
use App\Models\Route;
use App\Models\Trip;
use App\Services\BookingService;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

class BookingFlow extends Component
{
    #[Url] public ?int $origin = null;
    #[Url] public ?int $dest = null;
    #[Url] public string $type = 'oneway';
    #[Url] public ?string $date = null;
    #[Url] public ?string $ret = null;
    #[Url] public int $pax = 1;

    public string $step = 'outbound'; // outbound | return | details
    public ?int $outboundTripId = null;
    public ?int $returnTripId = null;

    public string $contactName = '';
    public string $contactPhone = '';
    public string $contactEmail = '';

    /** คีย์กันส่งซ้ำ: สร้างครั้งเดียวตอนเปิดหน้า กดซ้ำกี่ครั้งก็ได้เลขจองเดียว */
    public string $idempotencyKey = '';
    public ?string $notice = null;

    public function mount(): void
    {
        if (! $this->route() || ! $this->date) {
            $this->redirectRoute('home');

            return;
        }
        $this->pax = max(1, min(config('ferry.max_passengers'), $this->pax));
        $this->idempotencyKey = (string) Str::uuid();
    }

    private function route(): ?Route
    {
        return Route::with(['origin', 'destination', 'island'])->where('is_active', true)
            ->where('origin_port_id', $this->origin)->where('destination_port_id', $this->dest)->first();
    }

    private function isRound(): bool
    {
        return $this->type === 'round' && $this->ret;
    }

    private function tripsFor(Route $route, string $date, $notBefore = null)
    {
        $q = Trip::where('route_id', $route->id)->where('status', 'open')->whereDate('departs_at', $date)->where('departs_at', '>', now());
        if ($notBefore) {
            $q->where('departs_at', '>=', $notBefore);
        }

        return $q->orderBy('departs_at')->get();
    }

    public function chooseOutbound(int $id): void
    {
        $route = $this->route();
        $trip = $route ? $this->tripsFor($route, $this->date)->firstWhere('id', $id) : null;
        if (! $trip || ! $trip->isBookable($this->pax)) {
            $this->notice = 'รอบเรือนี้เต็มหรือปิดจองแล้ว กรุณาเลือกรอบอื่น';

            return;
        }
        $this->notice = null;
        $this->outboundTripId = $id;
        $this->returnTripId = null;
        $this->step = $this->isRound() ? 'return' : 'details';
    }

    public function chooseReturn(int $id): void
    {
        $trip = Trip::find($id);
        $valid = $trip && collect($this->returnOptions())->contains('id', $id);
        if (! $valid || ! $trip->isBookable($this->pax)) {
            $this->notice = 'รอบเรือขากลับนี้เต็มหรือปิดจองแล้ว กรุณาเลือกรอบอื่น';

            return;
        }
        $this->notice = null;
        $this->returnTripId = $id;
        $this->step = 'details';
    }

    public function goBack(): void
    {
        $this->notice = null;
        $this->step = match ($this->step) {
            'details' => $this->isRound() ? 'return' : 'outbound',
            'return' => 'outbound',
            default => 'outbound',
        };
    }

    private function returnOptions()
    {
        $route = $this->route();
        $outbound = $this->outboundTripId ? Trip::find($this->outboundTripId) : null;
        $reverse = $route?->reverse();
        if (! $reverse || ! $this->isRound()) {
            return collect();
        }

        return $this->tripsFor($reverse, $this->ret, $outbound?->arrives_at);
    }

    public function submit()
    {
        $this->validate([
            'contactName' => 'required|string|max:120',
            'contactPhone' => ['required', 'regex:/^[0-9+\-\s]{9,15}$/'],
            'contactEmail' => 'required|email|max:190',
        ], [
            'contactName.required' => 'กรุณากรอกชื่อผู้จอง',
            'contactPhone.required' => 'กรุณากรอกเบอร์โทรศัพท์',
            'contactPhone.regex' => 'เบอร์โทรศัพท์ไม่ถูกต้อง',
            'contactEmail.required' => 'กรุณากรอกอีเมล',
            'contactEmail.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
        ]);

        try {
            $booking = app(BookingService::class)->create([
                'outbound_trip_id' => $this->outboundTripId,
                'return_trip_id' => $this->isRound() ? $this->returnTripId : null,
                'passengers' => $this->pax,
                'contact_name' => $this->contactName,
                'contact_phone' => $this->contactPhone,
                'contact_email' => $this->contactEmail,
                'idempotency_key' => $this->idempotencyKey,
            ]);
        } catch (BookingException $e) {
            // ขาที่เต็มต้องเลือกรอบใหม่ก่อนแจ้งโอน ไม่มีขาไหนถูกจองค้าง (ธุรกรรมถูกย้อนกลับทั้งหมด)
            $this->notice = $e->getMessage();
            if ($e->direction === 'return') {
                $this->returnTripId = null;
                $this->step = 'return';
            } else {
                $this->outboundTripId = null;
                $this->returnTripId = null;
                $this->step = 'outbound';
            }

            return;
        }

        return $this->redirectRoute('booking.show', ['token' => $booking->access_token]);
    }

    public function render()
    {
        $route = $this->route();
        $outTrips = $route ? $this->tripsFor($route, $this->date) : collect();
        $retTrips = $this->step === 'return' ? $this->returnOptions() : collect();
        $out = $this->outboundTripId ? Trip::find($this->outboundTripId) : null;
        $ret = $this->returnTripId ? Trip::find($this->returnTripId) : null;
        $total = (($out?->price ?? 0) + ($ret?->price ?? 0)) * $this->pax; // แสดงผลเท่านั้น ยอดจริงคำนวณตอนสร้างการจอง

        return view('livewire.booking-flow', ['route' => $route, 'outTrips' => $outTrips, 'retTrips' => $retTrips, 'outTrip' => $out, 'retTrip' => $ret, 'total' => $total])
            ->layout('layouts::app', ['title' => 'จองเรือ — เรือไปเกาะ']);
    }
}
