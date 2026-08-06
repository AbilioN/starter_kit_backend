<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_active');
            $table->string('tertiary_color')->nullable()->after('is_public');
            $table->json('icon_paths')->nullable()->after('tertiary_color');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'tertiary_color', 'icon_paths']);
        });
    }
};
