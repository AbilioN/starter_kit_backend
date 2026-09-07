<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives back the languages the panel used to offer unconditionally.
 *
 * Until now nothing read `locales.enabled` outside the templates editor, and
 * nothing could WRITE it — there was no screen. So its value in every tenant is
 * `["en"]`, SettingSeeder's shipped default, and not one of them is a choice
 * anybody made: four of the five tenants here carried it untouched.
 *
 * The Languages screen now gates the header switcher and the custom-field
 * authoring tabs on that list. Shipping without this would silently reduce
 * every existing tenant to English — on a product whose first customers are
 * Portuguese and Brazilian, and who could see all four yesterday.
 *
 * Only widens a value still equal to the old default, so a tenant that has
 * since chosen English deliberately keeps it. That distinction only exists
 * from today; before it, there was nothing to distinguish.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    private const OLD_DEFAULT = '["en"]';

    public function up(): void
    {
        $available = (array) config('app.available_locales', ['en']);

        DB::connection('tenant')->table('settings')
            ->where('key', 'locales.enabled')
            ->where('value', self::OLD_DEFAULT)
            ->update([
                'value' => json_encode(array_values($available)),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Not reversed. Narrowing a tenant's languages back is a product
        // decision, not a schema one, and doing it blindly would discard a
        // choice made through the screen after this ran.
    }
};
