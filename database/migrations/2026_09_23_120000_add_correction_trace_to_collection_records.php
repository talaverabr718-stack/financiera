<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_records', function (Blueprint $table) {
            $table->foreignId('correction_of_id')->nullable()->after('payment_id')->unique()
                ->constrained('collection_records')->restrictOnDelete();
            $table->text('correction_reason')->nullable()->after('notes');
            $table->foreignId('corrected_by')->nullable()->after('recorded_by')
                ->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collection_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('correction_of_id');
            $table->dropConstrainedForeignId('corrected_by');
            $table->dropColumn('correction_reason');
        });
    }
};
