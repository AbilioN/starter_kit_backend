<?php

namespace App\Application\UseCases\Admin;

use App\Application\CustomFields\FieldViewer;
use App\Application\UseCases\CustomField\ProjectCustomFieldsUseCase;
use App\Domain\Exceptions\UserNotFoundException;
use App\Models\User;

/**
 * Edits an end user, including whatever fields the tenant invented.
 *
 * **The e-mail is not editable here, and that is the line, not an omission.**
 * It is the login identity and the target of the verification flow; changing
 * it is a re-verification, not a field edit. The password has its own use case
 * for the same reason. What is left — the display name, the language, and the
 * tenant's own fields — is exactly the adaptable half.
 */
class UpdateUserUseCase
{
    public function __construct(private ProjectCustomFieldsUseCase $customFields) {}

    /**
     * @param  array<string, mixed>  $customValues  column => value
     * @return array{0: User, 1: array<int, string>}  the user, and the custom
     *         columns that were DROPPED because this viewer may not write them
     */
    public function execute(
        string $userId,
        FieldViewer $viewer,
        ?string $name = null,
        ?string $locale = null,
        array $customValues = [],
    ): array {
        $user = User::find($userId);

        if (! $user) {
            throw new UserNotFoundException("User with ID {$userId} not found");
        }

        $core = array_filter(
            ['name' => $name, 'locale' => $locale],
            fn ($value) => $value !== null,
        );

        if ($core !== []) {
            $user->update($core);
        }

        $ignored = [];

        if ($customValues !== []) {
            $writable = $this->customFields->writableColumns('users', $viewer);

            // $fillable is a fixed list and cf_* columns are invented at
            // runtime, so update(['cf_1' => ...]) would silently discard the
            // key and answer 200 with nothing stored.
            $user->setTenantFieldValues($customValues, $writable);
            $user->save();

            // Dropped and REPORTED, not refused: a form loaded before an
            // administrator changed the rules must still be submittable, and a
            // silent drop is the failure mode this feature rejects everywhere.
            $ignored = array_values(array_diff(array_keys($customValues), $writable));
        }

        return [$user->refresh(), $ignored];
    }
}
