<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Older INOTEK installations already have Spatie's roles table
        // (including guard_name). Preserve those roles and extend the table
        // with fields used by this application instead of recreating it.
        if (Schema::hasTable('roles')) {
            $addLabel = ! Schema::hasColumn('roles', 'label');
            $addDescription = ! Schema::hasColumn('roles', 'description');

            if ($addLabel || $addDescription) {
                Schema::table('roles', function (Blueprint $table) use ($addLabel, $addDescription): void {
                    if ($addLabel) {
                        $table->string('label')->nullable();
                    }

                    if ($addDescription) {
                        $table->string('description')->nullable();
                    }
                });
            }

            if ($addLabel) {
                DB::table('roles')->whereNull('label')->update(['label' => DB::raw('name')]);
            }

            return;
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration can adopt a legacy roles table. Keeping the table on
        // rollback prevents an accidental deletion of existing role data.
    }
};
