<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Audit\GetAuditLogsUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Audit Controller
 * 
 * IMPORTANT SECURITY NOTE:
 * Audit logs are IMMUTABLE for security and compliance reasons.
 * - NO delete endpoints exist (and never will)
 * - NO update/edit endpoints exist (and never will)
 * - Only READ permission exists: 'audit-read'
 * - Even Super Admin cannot delete or modify audit logs
 * 
 * This ensures:
 * - Compliance with LGPD/GDPR
 * - Non-repudiation (logs cannot be tampered with)
 * - Complete audit trail integrity
 */
class AuditController extends Controller
{
    public function __construct(
        private GetAuditLogsUseCase $getAuditLogsUseCase,
        private AuthorizeActionUseCase $authorizeActionUseCase
    ) {}

    /**
     * Lista logs de auditoria com filtros
     * GET /api/admin/audit
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            
            // Requer permissão específica para ver audit logs
            // IMPORTANT: Audit logs are IMMUTABLE - only read permission exists
            $this->authorizeActionUseCase->execute($admin, 'audit-read');

            // Filtros da query string
            $filters = [
                'user_id' => $request->query('user_id'),
                'user_type' => $request->query('user_type'),
                'model_type' => $request->query('model_type'),
                'model_id' => $request->query('model_id'),
                'action' => $request->query('action'),
                'tags' => $request->query('tags') ? explode(',', $request->query('tags')) : null,
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
            ];

            // Remove filtros vazios
            $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

            $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

            $result = $this->getAuditLogsUseCase->execute($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostra um log específico
     * GET /api/admin/audit/{id}
     */
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            
            $this->authorizeActionUseCase->execute($admin, 'audit-read');

            $log = $this->getAuditLogsUseCase->getById($id);

            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audit log not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $log,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Histórico de um modelo específico
     * GET /api/admin/audit/model/{type}/{id}
     */
    public function modelHistory(string $type, int $id, Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            
            $this->authorizeActionUseCase->execute($admin, 'audit-read');

            $logs = $this->getAuditLogsUseCase->getForModel($type, $id);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atividade de um usuário
     * GET /api/admin/audit/user/{type}/{id}
     */
    public function userActivity(string $type, int $id, Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            
            $this->authorizeActionUseCase->execute($admin, 'audit-read');

            $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

            $result = $this->getAuditLogsUseCase->getForUser($id, ucfirst($type), $perPage);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logs por ação
     * GET /api/admin/audit/action/{action}
     */
    public function byAction(string $action, Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            
            $this->authorizeActionUseCase->execute($admin, 'audit-read');

            $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

            $result = $this->getAuditLogsUseCase->getByAction($action, $perPage);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logs por tag
     * GET /api/admin/audit/tag/{tag}
     */
    public function byTag(string $tag, Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            
            $this->authorizeActionUseCase->execute($admin, 'audit-read');

            $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

            $result = $this->getAuditLogsUseCase->getByTag($tag, $perPage);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

