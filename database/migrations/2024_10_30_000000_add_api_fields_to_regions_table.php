<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            // Type de source : 'api' ou 'sql'
            $table->string('source_type', 10)->default('sql')->after('actif');

            // Configuration API
            $table->string('api_url', 500)->nullable()->after('source_type');
            $table->text('api_key')->nullable()->after('api_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'api_url', 'api_key']);
        });
    }
};


