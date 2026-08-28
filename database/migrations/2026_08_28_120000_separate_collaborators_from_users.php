<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->string('full_name', 180)->nullable()->after('code');
            $table->string('email', 180)->nullable()->after('full_name');
        });

        DB::table('seller_profiles')->whereNotNull('user_id')->orderBy('id')->each(function ($profile): void {
            $user = DB::table('users')->find($profile->user_id);
            if ($user) {
                DB::table('seller_profiles')->where('id', $profile->id)->update(['full_name' => $user->name, 'email' => $user->email]);
            }
        });

        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->dropColumn(['full_name', 'email']);
        });
    }
};
