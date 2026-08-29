<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Admin\Authorization\CheckAdminPermissionUseCase;
use App\Application\UseCases\Agenda\BuildAgendaUseCase;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One endpoint returns the whole agenda screen.
 *
 * Every navigation — a different week, a different grouping, a filter — returns
 * the entire state rather than a diff. It costs bandwidth and buys the absence
 * of a whole class of bug where the client and the server disagree about what
 * is on screen.
 *
 * The view and the date arrive as query parameters, not session state. MADCRM
 * keeps them in the session and the study says to change it: in the URL an
 * agenda view is linkable, and two tabs stop fighting over one cursor.
 */
class AgendaController extends Controller
{
    public function __construct(
        private BuildAgendaUseCase $buildAgenda,
        private AuthorizeActionUseCase $authorize,
        private CheckAdminPermissionUseCase $checkPermission,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'appointment-read');

        $validated = $request->validate([
            'view' => ['sometimes', 'in:day,week,month'],
            'date' => ['sometimes', 'date'],
            'group_by' => ['sometimes', 'nullable', 'in:assigned_admin,type,status,city'],
            'type_id' => ['sometimes', 'nullable', 'string'],
            'status_id' => ['sometimes', 'nullable', 'string'],
            'assigned_admin_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $agenda = $this->buildAgenda->execute(
            view: $validated['view'] ?? 'week',
            date: CarbonImmutable::parse($validated['date'] ?? 'today'),
            filters: array_filter([
                'type_id' => $validated['type_id'] ?? null,
                'status_id' => $validated['status_id'] ?? null,
                'assigned_admin_id' => $validated['assigned_admin_id'] ?? null,
            ]),
            groupBy: $validated['group_by'] ?? null,
            // Card menus are filtered by what this admin may actually do, so a
            // card never offers an action that would be refused when clicked.
            allows: fn (?string $slug) => $slug === null
                || $this->checkPermission->execute($admin, $slug),
        );

        return response()->json(['success' => true, 'data' => $agenda]);
    }
}
