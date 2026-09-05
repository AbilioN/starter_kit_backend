<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\CustomFields\FieldViewerFactory;
use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Admin\CreateUserUseCase;
use App\Application\UseCases\Admin\DeleteUserUseCase;
use App\Application\UseCases\Admin\GetUserUseCase;
use App\Application\UseCases\Admin\ListUsersUseCase;
use App\Application\UseCases\Admin\UpdateUserUseCase;
use App\Application\UseCases\CustomField\ProjectCustomFieldsUseCase;
use App\Domain\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * End users, as an administrator manages them.
 *
 * `create`, `update` and `delete` were registered in routes/api.php long before
 * they existed — three live 500s. They are implemented here, together with the
 * authorization that `index` and `show` were also missing: `user-read` was a
 * slug nothing checked.
 *
 * Every read carries the tenant's own fields as CONTEXT, so a screen that draws
 * a user form knows which controls to draw without a second request.
 */
class UserController extends Controller
{
    public function __construct(
        private AuthorizeActionUseCase $authorize,
        private ProjectCustomFieldsUseCase $customFields,
        private FieldViewerFactory $viewers,
    ) {}

    public function index(Request $request, ListUsersUseCase $listUsers): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'user-read');

        $page = max(1, (int) $request->get('page', 1));
        $perPage = (int) $request->get('per_page', 15);
        $perPage = ($perPage < 1 || $perPage > 100) ? 15 : $perPage;

        $result = $listUsers->execute($page, $perPage);

        // Described once for the whole list, not per row.
        $result['custom_fields'] = $this->customFields->context('users', $this->viewer($request));

        return response()->json($result, 200);
    }

    public function show(Request $request, string $id, GetUserUseCase $getUser): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'user-read');

        try {
            $result = $getUser->execute($id);
        } catch (UserNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $viewer = $this->viewer($request);
        $user = User::findOrFail($id);

        $result['custom_fields'] = $this->customFields->context('users', $viewer);
        $result['custom'] = $this->customFields->values('users', $user, $viewer);

        return response()->json($result, 200);
    }

    public function create(Request $request, CreateUserUseCase $createUser): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'user-create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(config('app.available_locales', []))],
        ]);

        $user = $createUser->execute(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            locale: $data['locale'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $user], 201);
    }

    public function update(Request $request, string $id, UpdateUserUseCase $updateUser): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'user-update');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(config('app.available_locales', []))],
            // The tenant's own fields, keyed by storage column: {"cf_1": "…"}.
            'custom' => ['sometimes', 'array'],
        ]);

        $viewer = $this->viewer($request);

        try {
            [$user, $ignored] = $updateUser->execute(
                userId: $id,
                viewer: $viewer,
                name: $data['name'] ?? null,
                locale: array_key_exists('locale', $data) ? $data['locale'] : null,
                customValues: (array) ($data['custom'] ?? []),
            );
        } catch (UserNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(array_filter([
            'success' => true,
            'data' => $user,
            'custom_fields' => $this->customFields->context('users', $viewer),
            'custom' => $this->customFields->values('users', $user, $viewer),
            'ignored_fields' => $ignored ?: null,
        ], fn ($v) => $v !== null));
    }

    public function delete(Request $request, string $id, DeleteUserUseCase $deleteUser): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'user-delete');

        try {
            $deleteUser->execute($id);
        } catch (UserNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['success' => true], 200);
    }

    /**
     * Built per request, in the controller.
     *
     * Never memoised on the container: a viewer held by a singleton on a
     * long-lived Horizon worker would carry one tenant's admin into the next
     * tenant's job — the settings-cache bug with a worse blast radius.
     */
    private function viewer(Request $request): \App\Application\CustomFields\FieldViewer
    {
        return $this->viewers->forAdmin($request->user());
    }
}
