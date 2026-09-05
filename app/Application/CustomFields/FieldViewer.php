<?php

namespace App\Application\CustomFields;

/**
 * Who is looking, reduced to the two facts custom-field visibility depends on.
 *
 * ## Why not AuthorizableUser
 *
 * That interface — the one AuthorizeActionUseCase and every admin controller
 * already speak — exposes getId/getName/getEmail/isSuperAdmin/isActive/
 * hasPermission and no roles at all, and App\Domain\Entities\Admin::hasPermission()
 * returns false unconditionally. Widening it would be a frozen-core RBAC
 * change made by a new feature, which is exactly the kind of thing this
 * design refuses elsewhere. So this is a separate, immutable value object
 * built once per request.
 *
 * ## Why an array of ids
 *
 * The read path touches this once per row per field — the highest-frequency
 * caller in the product. Handing it a list means the compiled catalogue never
 * calls back into the authorization stack, which is the stance
 * AppointmentActionRegistry::menuFor() already takes with its $allows
 * callback, generalised one step further.
 *
 * ## Why it must never be memoised on the container
 *
 * A container-level singleton on a long-lived Horizon worker would carry one
 * tenant's admin into the next tenant's job. That is the settings-cache bug
 * with a worse blast radius. Build it per request, in the controller.
 */
final class FieldViewer
{
    /** @param array<int, string> $roleIds */
    public function __construct(
        public readonly ?string $adminId,
        public readonly array $roleIds = [],
        /**
         * is_super_admin. It bypasses every rule, loudly documented, because
         * AdminFactory already returns a SudoAdmin for these people and
         * AuthorizeActionUseCase already returns early for them.
         *
         * The consequence worth repeating in every test file: a freshly
         * provisioned tenant's only admin is BOTH is_super_admin and
         * is_tenant_owner, so any visibility test written against it passes
         * vacuously.
         */
        public readonly bool $bypass = false,
    ) {}

    /** Nobody in particular — a queued job, the scheduler, a console command. */
    public static function system(): self
    {
        return new self(adminId: null, roleIds: [], bypass: true);
    }

    /**
     * Whether a rule applies to this viewer.
     *
     * **Deny-wins**, and it is the first inverted rule in a codebase where
     * every existing check is allow-wins — so here is the argument rather than
     * an assumption.
     *
     * CheckAdminPermissionUseCase ORs GRANTS across an admin's roles:
     * most-permissive-wins. `hidden`, `readonly` and `required` are denials
     * and obligations, so ORing them is the same operator applied to a
     * differently-signed list. When a tenant ticks "hidden for Support" they
     * mean *Support must not see this*, and an admin holding both Support and
     * Manager is still, at that moment, sitting in Support.
     *
     * Most-permissive-wins would also be vacuously true over an EMPTY role
     * set — and every freshly created admin holds zero roles until somebody
     * assigns one.
     *
     * @param  array<int, string>  $ruleRoleIds
     */
    public function matches(array $ruleRoleIds): bool
    {
        if ($ruleRoleIds === []) {
            return false;
        }

        return array_intersect($this->roleIds, $ruleRoleIds) !== [];
    }
}
