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
            // Endereço alternativo para notificações críticas (suspensão/reativação
            // do tenant). Só o tenant owner pode defini-lo — ver
            // UpdateAdminProfileRequest::authorize(). Null = usa `email`.
            $table->string('notification_email')->nullable()->after('email');

            // Caminho relativo no disco `public` (ex: admin-avatars/{tenantId}/x.jpg).
            // Guardamos o caminho e não a URL porque `asset()` depende de APP_URL,
            // que muda entre ambientes — a URL é derivada na leitura (AdminDto).
            $table->string('avatar_path')->nullable()->after('is_tenant_owner');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('admins', function (Blueprint $table) {
            $table->dropColumn(['notification_email', 'avatar_path']);
        });
    }
};
