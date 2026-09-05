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

            // This endpoint validated with its own inline rules and so walked
            // straight past UpdateSettingRequest's cap — a bulk write could
            // store megabytes into a key that is concatenated into every
            // system prompt. Same rules, one source.
            foreach ($pairs['settings'] as $index => $pair) {
                if (in_array($pair['key'] ?? null, UpdateSettingRequest::AI_INSTRUCTION_KEYS, true)) {
                    validator(
                        ['value' => $pair['value'] ?? null],
                        ['value' => UpdateSettingRequest::rulesForKey($pair['key'])],
                    )->validate();
                }
            }

            $keyValues = collect($pairs['settings'])->pluck('value', 'key')->all();
            $this->updateSetting->executeMany($keyValues);

            return response()->json(['success' => true, 'message' => 'Settings updated.']);
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
