<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('supervisor_name')->nullable()->change();
            $table->string('supervisor_email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep imported historical projects valid even when they have no
        // supervisor contact details in the legacy registration record.
    }
};
