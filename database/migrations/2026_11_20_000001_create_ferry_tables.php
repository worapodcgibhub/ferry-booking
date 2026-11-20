<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('staff')->after('email'); // owner | staff
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('islands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('headline')->nullable();      // เช่น "ไปเกาะกูด"
            $table->string('cover_path')->nullable();     // public disk
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('island_id')->nullable()->constrained()->nullOnDelete(); // ท่าที่อยู่บนเกาะ
            $table->string('name');
            $table->string('location')->nullable();       // อ.เมืองตราด จ.ตราด
            $table->string('boarding_point')->nullable(); // จุดขึ้นเรือ
            $table->text('note')->nullable();
            $table->string('map_url', 500)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_port_id')->constrained('ports')->restrictOnDelete();
            $table->foreignId('destination_port_id')->constrained('ports')->restrictOnDelete();
            $table->foreignId('island_id')->constrained()->restrictOnDelete(); // เกาะที่เส้นทางนี้ให้บริการ
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['origin_port_id', 'destination_port_id']);
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->restrictOnDelete();
            $table->dateTime('departs_at');
            $table->dateTime('arrives_at');
            $table->unsignedInteger('price');             // บาทต่อคน
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('seats_taken')->default(0);
            $table->string('provider')->default('เรือไปเกาะ');
            $table->string('status', 20)->default('open'); // open | closed
            $table->timestamps();
            $table->index(['route_id', 'departs_at']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('access_token', 64)->unique();   // ลิงก์เฉพาะรายการ
            $table->string('idempotency_key', 64)->unique();
            $table->string('trip_type', 10);                // oneway | round
            $table->string('contact_name');
            $table->string('contact_phone', 30);
            $table->string('contact_email');
            $table->unsignedSmallInteger('passengers');
            $table->unsignedInteger('total_amount');
            $table->string('booking_status', 20)->default('pending'); // pending|confirmed|cancelled|expired
            $table->string('payment_status', 20)->default('unpaid');  // unpaid|submitted|verified|rejected
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('seats_released_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->text('staff_note')->nullable();
            $table->timestamps();
            $table->index(['booking_status', 'payment_status']);
        });

        Schema::create('booking_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);               // outbound | return
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('unit_price');          // ราคาต่อคน ณ ตอนจอง
            $table->unsignedSmallInteger('passengers');
            $table->unsignedInteger('amount');
            $table->timestamps();
            $table->unique(['booking_id', 'direction']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('reported_amount');
            $table->dateTime('transferred_at');
            $table->string('slip_path');                    // local (private) disk
            $table->string('status', 20)->default('submitted'); // submitted|verified|rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'booking_legs', 'bookings', 'trips', 'routes', 'ports', 'islands', 'areas'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
