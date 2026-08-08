<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection('landlord')->create('infrastructure_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // 'broadcasting' | 'storage' — extensible to more later (mail,
            // queue, ...) without a new table: just a new type value and a
            // new resolution call site (TenantInfrastructureResolver).
            $table->string('type');
            $table->string('name');
            // broadcasting: {key, secret, app_id, cluster, host}
            // storage: {key, secret, region, bucket, endpoint, use_path_style, url}
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('infrastructure_providers');
    }
};
