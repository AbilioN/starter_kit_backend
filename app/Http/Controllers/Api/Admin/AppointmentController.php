<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\CustomFields\FieldViewerFactory;
use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\CustomField\ProjectCustomFieldsUseCase;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(
        private AuthorizeActionUseCase $authorize,
        private ProjectCustomFieldsUseCase $customFields,
        private FieldViewerFactory $viewers,
    ) {}

    /**
     * One appointment, with its custom fields as context.
     *
     * Added with the panel's edit dialog: `routes/api.php` advertised
     * create/update/delete on this controller long before there was a way to
     * READ a single record, so a form had nothing to open with.
     *
     * The context rides along rather than being fetched separately. A form
     * needs to know which controls to draw before it knows any values — and a
     * second round trip for that is a second chance for the two to disagree.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'appointment-read');

        $appointment = Appointment::with(['type', 'status'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $appointment,
            ...$this->customFieldPayload($request, $appointment, []),
        ]);
    }

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

            // Superseded by custom fields. Still accepted so an existing
            // client does not start silently losing writes — removing the key
            // would make $request->validate() drop it with a 200 and no
            // error, which is the "looked like it worked" class this feature
            // refuses everywhere else. It will be refused with a named code
            // once the panel has migrated.

            // Tenant-defined values, keyed by storage column: {"cf_1": "..."}.
            'custom' => ['sometimes', 'array'],
        ]);

        $appointment = Appointment::create([
            ...collect($data)->except('custom')->all(),
            'created_by_admin_id' => $request->user()->id,
        ]);

        $ignored = $this->writeCustomFields($request, $appointment);

        return response()->json([
            'success' => true,
            'data' => $appointment,
            ...$this->customFieldPayload($request, $appointment, $ignored),
        ], 201);
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
            'custom' => ['sometimes', 'array'],
        ]);

        $appointment->update(collect($data)->except('custom')->all());

        $ignored = $this->writeCustomFields($request, $appointment);

        return response()->json([
            'success' => true,
            'data' => $appointment->fresh(),
            ...$this->customFieldPayload($request, $appointment->fresh(), $ignored),
        ]);
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

    /**
     * Stores the custom values this admin is allowed to write, and reports the
     * ones it dropped.
     *
     * A `readonly` or `hidden` field arriving in a payload is DROPPED AND
     * REPORTED rather than refused with a 422. A stale form — one loaded
     * before an administrator changed the rules — must still be submittable,
     * and a silent drop is the failure mode this feature rejects everywhere
     * else. The client gets `ignored_fields` and can say so.
     *
     * @return array<int, string> the columns that were not written
     */
    private function writeCustomFields(Request $request, Appointment $appointment): array
    {
        $submitted = (array) $request->input('custom', []);

        if ($submitted === []) {
            return [];
        }

        $viewer = $this->viewers->forAdmin($request->user());
        $writable = $this->customFields->writableColumns('appointments', $viewer);

        // $fillable is a fixed list and cf_* columns are invented at runtime,
        // so update(['cf_1' => ...]) would silently discard the key and answer
        // 200 with nothing stored. setTenantFieldValues forceFills over an
        // explicit whitelist for exactly that reason.
        $appointment->setTenantFieldValues($submitted, $writable);
        $appointment->save();

        return array_values(array_diff(array_keys($submitted), $writable));
    }

    /**
     * The field context and this record's values, so the panel needs no second
     * request after a write.
     *
     * @param  array<int, string>  $ignored
     * @return array<string, mixed>
     */
    private function customFieldPayload(Request $request, Appointment $appointment, array $ignored): array
    {
        $viewer = $this->viewers->forAdmin($request->user());

        return array_filter([
            'custom_fields' => $this->customFields->context('appointments', $viewer),
            'custom' => $this->customFields->values('appointments', $appointment, $viewer),
            'ignored_fields' => $ignored ?: null,
        ], fn ($v) => $v !== null);
    }
}
