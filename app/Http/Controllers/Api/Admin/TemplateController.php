<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Template\CreateTemplateUseCase;
use App\Application\UseCases\Template\DeleteTemplateBackgroundUseCase;
use App\Application\UseCases\Template\DeleteTemplateUseCase;
use App\Application\UseCases\Template\GetTemplateBackgroundFilesUseCase;
use App\Application\UseCases\Template\GetTemplatesUseCase;
use App\Application\UseCases\Template\GetTemplateUseCase;
use App\Application\UseCases\Template\RenderTemplateUseCase;
use App\Application\UseCases\Template\UpdateTemplateUseCase;
use App\Application\UseCases\Template\UploadTemplateBackgroundUseCase;
use App\Domain\Exceptions\AuthorizationException;
use App\Domain\Exceptions\FileNotFoundException;
use App\Domain\Exceptions\TemplateNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Http\Requests\UploadTemplateBackgroundRequest;

class TemplateController extends Controller
{
    public function __construct(
        private AuthorizeActionUseCase $authorizeAction,
    ) {}

    public function index(Request $request, GetTemplatesUseCase $getTemplates): JsonResponse
    {
        try {
            $this->authorizeAction->execute($this->currentAdmin($request), 'template-read');

            $page = max((int) $request->query('page', 1), 1);
            $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
            $type = $request->query('type');

            $result = $getTemplates->execute($page, $perPage, $type);

            return response()->json([
                'success' => true,
                'data' => array_map(fn ($t) => $this->toArray($t), $result['data']),
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to'],
                ],
            ]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(CreateTemplateRequest $request, CreateTemplateUseCase $createTemplate): JsonResponse
    {
        try {
            $this->authorizeAction->execute($this->currentAdmin($request), 'template-create');

            $template = $createTemplate->execute(
                name: $request->string('name')->toString(),
                type: $request->string('type')->toString(),
                bodyFormat: $request->string('body_format')->toString(),
                body: $request->input('body'),
                subject: $request->input('subject'),
                description: $request->input('description'),
                isActive: $request->boolean('is_active', true),
                options: $request->input('options', []),
            );

            return response()->json(['success' => true, 'data' => $this->toArray($template)], 201);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(string $id, Request $request, GetTemplateUseCase $getTemplate): JsonResponse
    {
        try {
            $this->authorizeAction->execute($this->currentAdmin($request), 'template-read');

            return response()->json(['success' => true, 'data' => $this->toArray($getTemplate->execute($id))]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (TemplateNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(string $id, UpdateTemplateRequest $request, UpdateTemplateUseCase $updateTemplate): JsonResponse
    {
        try {
            $this->authorizeAction->execute($this->currentAdmin($request), 'template-update');

            $template = $updateTemplate->execute(
                id: $id,
                name: $request->input('name'),
                bodyFormat: $request->input('body_format'),
                body: $request->input('body'),
                subject: $request->input('subject'),
                description: $request->input('description'),
                isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
                options: $request->input('options'),
            );

            return response()->json(['success' => true, 'data' => $this->toArray($template)]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id, Request $request, DeleteTemplateUseCase $deleteTemplate): JsonResponse
    {
        try {
            $this->authorizeAction->execute($this->currentAdmin($request), 'template-delete');

            $deleteTemplate->execute($id);

            return response()->json(['success' => true]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function uploadBackground(string $id, UploadTemplateBackgroundRequest $request, UploadTemplateBackgroundUseCase $uploadBackground): JsonResponse
    {
        try {
            $this->authorizeAction->execute($this->currentAdmin($request), 'template-update');

            $files = $uploadBackground->execute($id, $request->file('file'));

            return response()->json(['success' => true, 'data' => array_map(fn ($f) => [
                'id' => $f->id,
                'original_name' => $f->originalName,
                'size' => $f->size,
                'sort' => $f->meta['sort'] ?? null,
            ], $files)], 201);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (TemplateNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function listBackground(string $id, Request $request, GetTemplateBackgroundFilesUseCase $getBackgroundFiles): JsonResponse
    {
        try {
            $this->authorizeAction->execute($this->currentAdmin($request), 'template-read');

            $files = $getBackgroundFiles->execute($id);

            return response()->json(['success' => true, 'data' => array_map(fn ($f) => [
                'id' => $f->id,
                'original_name' => $f->originalName,
                'size' => $f->size,
                'sort' => $f->meta['sort'] ?? null,
            ], $files)]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteBackground(string $id, string $fileId, Request $request, DeleteTemplateBackgroundUseCase $deleteBackground): JsonResponse
    {
        try {
            $this->authorizeAction->execute($this->currentAdmin($request), 'template-update');

            $deleteBackground->execute($id, $fileId);

            return response()->json(['success' => true]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (FileNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function preview(string $id, Request $request, RenderTemplateUseCase $renderTemplate): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        try {
            $this->authorizeAction->execute($this->currentAdmin($request), 'template-read');

            $result = $renderTemplate->execute(
                templateId: $id,
                recordId: 1, // preview always uses the StubMergeContext's one fake record
                promptValues: $request->input('prompt_values', []),
                isPreview: true,
            );

            if ($result['content_type'] === 'application/pdf') {
                return response($result['content'], 200, ['Content-Type' => 'application/pdf']);
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (TemplateNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function currentAdmin(Request $request)
    {
        return AdminFactory::createFromModel($request->user());
    }

    private function toArray($template): array
    {
        return [
            'id' => $template->id,
            // Read-only — identifies a system email slot (e.g.
            // 'welcome_email'), never settable through this API. Null for
            // every ordinary, user-authored template.
            'key' => $template->key,
            'name' => $template->name,
            'type' => $template->type,
            'body_format' => $template->bodyFormat,
            'body' => $template->body,
            'subject' => $template->subject,
            'description' => $template->description,
            'is_active' => $template->isActive,
            'options' => $template->options,
            'created_at' => $template->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $template->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
