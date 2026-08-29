<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(private AuthorizeActionUseCase $authorize) {}

    public function store(Request $request): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'appointment-create');

        $data = $request->validate([
            'appointment_type_id' => ['required', 'string', 'exists:appointment_types,id'],
            'appointment_status_id' => ['required', 'string', 'exists:appointment_statuses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'all_day' => ['sometimes', 'boolean'],
            'assigned_admin_id' => ['sometimes', 'nullable', 'string'],
            'subject_type' => ['sometimes', 'nullable', 'string'],
            'subject_id' => ['sometimes', 'nullable', 'string'],
            'location_address' => ['sometimes', 'nullable', 'string'],
            'location_postcode' => ['sometimes', 'nullable', 'string', 'max:32'],
            'location_city' => ['sometimes', 'nullable', 'string'],
            'location_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $appointment = Appointment::create([
            ...$data,
            'created_by_admin_id' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $appointment], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'appointment-update');

        $appointment = Appointment::findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date'],
            'appointment_type_id' => ['sometimes', 'string', 'exists:appointment_types,id'],
            'appointment_status_id' => ['sometimes', 'string', 'exists:appointment_statuses,id'],
            'assigned_admin_id' => ['sometimes', 'nullable', 'string'],
            'location_address' => ['sometimes', 'nullable', 'string'],
            'location_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        $appointment->update($data);

        return response()->json(['success' => true, 'data' => $appointment->fresh()]);
    }

    /**
     * The one-click status change the card offers.
     *
     * Its own endpoint rather than a general update, because it is the action
     * a triage screen performs constantly and it should not require sending —
     * or being allowed to send — the rest of the record.
     */
    public function changeStatus(Request $request, string $id): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'appointment-update');

        $data = $request->validate([
            'appointment_status_id' => ['required', 'string', 'exists:appointment_statuses,id'],
        ]);

        $appointment = Appointment::findOrFail($id);
        $appointment->update($data);

        return response()->json(['success' => true, 'data' => $appointment->fresh()->load('status')]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'appointment-delete');

        Appointment::findOrFail($id)->delete();

        return response()->json(['success' => true, 'data' => ['message' => 'Appointment deleted']]);
    }
}
