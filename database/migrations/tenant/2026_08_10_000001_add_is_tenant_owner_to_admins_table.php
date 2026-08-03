<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('admins', function (Blueprint $table) {
            $table->boolean('is_tenant_owner')->default(false)->after('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('admins', function (Blueprint $table) {
            $table->dropColumn('is_tenant_owner');
        });
    }
};
