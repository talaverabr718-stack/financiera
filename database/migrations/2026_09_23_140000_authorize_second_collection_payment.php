<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_additional_payment_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_route_stop_id')->unique('collection_second_payment_stop_unique')
                ->constrained()->restrictOnDelete();
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('authorized_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('collection_records', function (Blueprint $table) {
            $table->foreignId('additional_payment_authorization_id')->nullable()
                ->after('correction_of_id')->unique('collection_record_second_payment_unique')
                ->constrained('collection_additional_payment_authorizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collection_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('additional_payment_authorization_id');
        });

        Schema::dropIfExists('collection_additional_payment_authorizations');
    }
};
