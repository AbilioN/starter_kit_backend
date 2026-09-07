<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Setting\GetAllSettingsUseCase;
use App\Application\UseCases\Setting\GetSettingByKeyUseCase;
use App\Application\UseCases\Setting\UpdateSettingUseCase;
use App\Domain\Exceptions\AuthorizationException;
use App\Domain\Exceptions\SettingNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function __construct(
        private GetAllSettingsUseCase $getAllSettings,
        private GetSettingByKeyUseCase $getSettingByKey,
        private UpdateSettingUseCase $updateSetting,
        private AuthorizeActionUseCase $authorizeAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $admin = AdminFactory::createFromModel($request->user());
            $this->authorizeAction->execute($admin, 'setting-read');

            $group = $request->query('group');
            $data = $this->getAllSettings->execute(publicOnly: false, group: $group);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(string $key, Request $request): JsonResponse
    {
        try {
            $admin = AdminFactory::createFromModel($request->user());
            $this->authorizeAction->execute($admin, 'setting-read');

            $setting = $this->getSettingByKey->execute($key);

            if (!$setting) {
                return response()->json(['success' => false, 'message' => 'Setting not found.'], 404);
            }

            return response()->json(['success' => true, 'data' => $setting]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(string $key, UpdateSettingRequest $request): JsonResponse
    {
        try {
            $admin = AdminFactory::createFromModel($request->user());
            $this->authorizeAction->execute($admin, 'setting-update');

            $data = $this->updateSetting->execute($key, $request->validated('value'));

            return response()->json(['success' => true, 'data' => $data]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (SettingNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMany(Request $request): JsonResponse
    {
        try {
            $admin = AdminFactory::createFromModel($request->user());
            $this->authorizeAction->execute($admin, 'setting-update');

            $pairs = $request->validate([
                'settings' => 'required|array',
                'settings.*.key' => 'required|string',
                'settings.*.value' => 'required',
            ]);

            // EVERY key goes through the same rules as the single-key
            // endpoint, not a list of special cases. This endpoint validated
            // inline and so walked past UpdateSettingRequest entirely: a bulk
            // write could store megabytes into a key concatenated into every
            // system prompt, or empty the tenant's language list. Special-casing
            // one family of keys here would only have left the next family
            // exposed.
            // A batch is judged as a whole. `locales.default` is validated
            // against the offered languages, and validating every pair before
            // writing any of them meant it was checked against the PRE-write
            // list — so enabling French and making it the default in one
            // request was impossible, and a default that had fallen outside
            // the set would 422 the entire settings tab forever.
            $incomingEnabled = collect($pairs['settings'])
                ->firstWhere('key', 'locales.enabled')['value'] ?? null;

            foreach ($pairs['settings'] as $pair) {
                $key = (string) ($pair['key'] ?? '');

                $rules = ['value' => $key === 'locales.default' && is_array($incomingEnabled)
                    ? ['required', 'string', Rule::in($incomingEnabled)]
                    : UpdateSettingRequest::rulesForKey($key)];

                if ($key === 'locales.enabled') {
                    $rules['value.*'] = ['string', Rule::in(config('app.available_locales', []))];
                }

                validator(['value' => $pair['value'] ?? null], $rules)->validate();
            }

            $keyValues = collect($pairs['settings'])->pluck('value', 'key')->all();
            $this->updateSetting->executeMany($keyValues);

            return response()->json(['success' => true, 'message' => 'Settings updated.']);
        } catch (ValidationException $e) {
            // Rethrown, not swallowed. The generic handler below turns every
            // exception into a 500, so a refused value reported itself as a
            // server fault — validation that answers 500 tells the caller
            // nothing and tells a monitor the wrong thing.
            throw $e;
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (SettingNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function public(): JsonResponse
    {
        try {
            $data = $this->getAllSettings->execute(publicOnly: true);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
