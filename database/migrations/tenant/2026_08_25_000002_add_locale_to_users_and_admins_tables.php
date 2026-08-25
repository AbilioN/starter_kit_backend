<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // First link of the send-time cascade: recipient -> tenant default ->
        // any translation that exists (ResolveTemplateLocaleUseCase).
        //
        // Nullable, and null is not a gap to be filled: it means "this person
        // never said", which is different from "this person chose the tenant
        // default". Writing the tenant default in here at signup would freeze
        // a choice nobody made, and the row would stop following the tenant
        // when it adds or changes languages.
        foreach (['users', 'admins'] as $table) {
            Schema::connection('tenant')->table($table, function (Blueprint $table) {
                $table->string('locale', 10)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'admins'] as $table) {
            Schema::connection('tenant')->table($table, function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
