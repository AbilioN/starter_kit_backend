<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\File\DeleteFileUseCase;
use App\Application\UseCases\File\GetFilesUseCase;
use App\Application\UseCases\File\UploadFileUseCase;
use App\Domain\Exceptions\FileNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadFileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(
        private UploadFileUseCase $uploadFile,
        private GetFilesUseCase $getFiles,
        private DeleteFileUseCase $deleteFile,
        private AuthorizeActionUseCase $authorizeAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $admin = AdminFactory::createFromModel($request->user());
            $this->authorizeAction->execute($admin, 'file-read');

            $page = (int) $request->query('page', 1);
            $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
            $folder = $request->query('folder');

            $result = $this->getFiles->execute($page, $perPage, $folder);

            return response()->json(['success' => true, 'data' => $result['data'], 'pagination' => $result['pagination']]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function upload(UploadFileRequest $request): JsonResponse
    {
        try {
            $admin = AdminFactory::createFromModel($request->user());
            $this->authorizeAction->execute($admin, 'file-upload');

            $file = $this->uploadFile->execute(
                file: $request->file('file'),
                folder: $request->input('folder', 'uploads'),
                disk: $request->input('disk', 'local'),
                isPublic: $request->boolean('is_public', false),
                uploadableType: 'App\Models\Admin',
                uploadableId: $admin->id,
                meta: $request->input('meta'),
            );

            return response()->json(['success' => true, 'data' => $file], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function delete(int $id, Request $request): JsonResponse
    {
        try {
            $admin = AdminFactory::createFromModel($request->user());
            $this->authorizeAction->execute($admin, 'file-delete');

            $this->deleteFile->execute($id);

            return response()->json(['success' => true]);
        } catch (FileNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
