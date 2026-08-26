<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_records', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('client_id')->constrained()->restrictOnDelete();
            $table->index(['loan_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('collection_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });
    }
};
