<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('name')->nullable()->after('id');
            });
        }

        if (Schema::hasColumn('users', 'username')) {
            DB::table('users')->whereNull('name')->update(['name' => DB::raw('username')]);
        }
    }

    public function down(): void
    {
        // Keep the column: later application data may rely on user names.
    }
};
