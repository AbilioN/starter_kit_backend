<?php

namespace App\Application\Services;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What an appointment is ABOUT.
 *
 * `appointments.subject_type`/`subject_id` is a nullable morph whose migration
 * states the intent outright: *"the starter kit has no 'client' — every
 * vertical brings its own noun."* It has been a place to STORE a pointer since
 * the table was created and never a place to RESOLVE one — `subject()` was
 * declared and nothing in the application ever loaded it. So the one field
 * carrying each vertical's own noun was invisible everywhere, the assistant
 * included.
 *
 * ## An allow-list, not `morphTo()`
 *
 * `subject_type` holds a class name written by whoever last wrote the row. A
 * bare `$appointment->subject` would instantiate whatever that string names,
 * which is the read-side twin of letting a tenant name the table the
 * reconciler will ALTER. Only registered types resolve; anything else is
 * reported as unknown rather than loaded.
 *
 * Registration is by hand, in a provider, following
 * `AppointmentActionRegistry` and `CustomFieldHostRegistry`: *nothing should
 * become invocable merely by existing in a folder.*
 *
 * ## Today it knows one type
 *
 * `User`, because that is the only entity this product has that an appointment
 * can plausibly be about. A vertical that needs a maintenance job, a matter or
 * a patient record adds a model and one line here — which is the same cost as
 * adding a custom-field host, and deliberately a reviewed diff.
 */
class AppointmentSubjectResolver
{
    /**
     * Morph key => [class, label resolver].
     *
     * The KEY is what is stored, and it is a short alias rather than a class
     * name on purpose: a stored FQCN breaks the day the class moves namespace,
     * and this table is meant to outlive several refactors.
     *
     * @var array<string, array{class: class-string<Model>, label: callable(Model): string}>
     */
    private array $types = [];

    /**
     * Resolved subjects for this request, keyed "type:id".
     *
     * A tool call maps over up to 50 appointments and most of a day's work is
     * about a handful of subjects, so without this the same client is fetched
     * once per appointment.
     *
     * @var array<string, array{type: string, id: string, label: string}|null>
     */
    private array $memo = [];

    public function register(string $key, string $class, callable $label): void
    {
        $this->types[$key] = ['class' => $class, 'label' => $label];
    }

    /**
     * Forget what was resolved.
     *
     * The container binds this as a singleton, so on a long-lived Horizon
     * worker the memo would otherwise outlive the request — and a subject id
     * is only unique within one tenant's database.
     */
    public function forget(): void
    {
        $this->memo = [];
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->types);
    }

    /**
     * What this appointment is about, as something a person or a model can
     * read — never the raw row.
     *
     * Returns null when the appointment is about nothing, which is the common
     * case and not an error: an internal meeting has no subject.
     *
     * @return array{type: string, id: string, label: string}|null
     */
    public function describe(Appointment $appointment): ?array
    {
        $key = $appointment->subject_type;
        $id = $appointment->subject_id;

        if ($key === null || $id === null) {
            return null;
        }

        $cacheKey = $key.':'.$id;

        if (array_key_exists($cacheKey, $this->memo)) {
            return $this->memo[$cacheKey];
        }

        $registered = $this->types[$key] ?? null;

        if ($registered === null) {
            // A type nobody registered. Reported rather than resolved, and
            // rather than silently dropped: a row pointing at something this
            // deployment does not know about is worth seeing.
            return $this->memo[$cacheKey] = ['type' => $key, 'id' => (string) $id, 'label' => '(unknown type)'];
        }

        /** @var Model|null $model */
        $model = $registered['class']::find($id);

        if ($model === null) {
            // The pointer outlived what it pointed at. There is no FK here —
            // deliberately, since the morph cannot have one — so this is a
            // normal state, not a corruption.
            return $this->memo[$cacheKey] = ['type' => $key, 'id' => (string) $id, 'label' => '(no longer exists)'];
        }

        return $this->memo[$cacheKey] = [
            'type' => $key,
            'id' => (string) $id,
            'label' => ($registered['label'])($model),
        ];
    }

    /** The registrations this product ships. */
    public static function withDefaults(): self
    {
        $resolver = new self();

        // The name, and NOTHING else. The first version fell back to the
        // email address, which put a person's contact details in front of any
        // admin holding `appointment-read` — a tool about the agenda, with no
        // `user-read` check and no row scope on `users` — and into the prompt
        // sent to whatever endpoint the tenant configured.
        $resolver->register('user', User::class, fn (User $u) => trim((string) $u->name) ?: '(unnamed)');

        return $resolver;
    }
}
