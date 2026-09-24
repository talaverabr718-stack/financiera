<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_payment_correction_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_record_id')->unique('collection_correction_authorization_record_unique')->constrained()->restrictOnDelete();
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('authorized_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('collection_records', function (Blueprint $table) {
            $table->foreignId('correction_authorization_id')->nullable()
                ->after('additional_payment_authorization_id')->unique('collection_record_correction_authorization_unique')
                ->constrained('collection_payment_correction_authorizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collection_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('correction_authorization_id');
        });

        Schema::dropIfExists('collection_payment_correction_authorizations');
    }
};
