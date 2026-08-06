<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\PermissionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\Auth\ResendVerificationCodeController;
use App\Http\Controllers\Api\Auth\AdminLoginController;
use App\Http\Controllers\Api\Auth\AdminRegisterController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\AuditController;
use App\Http\Controllers\Api\Admin\FileController;
use App\Http\Controllers\Api\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Chat\ChatController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Api\Admin\TenantController;
use App\Http\Controllers\Api\Public\PublicSubscriptionPlanController;
use App\Http\Controllers\Api\Public\PublicTenantSignupController;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
| Everything below is wrapped in `tenant.identify`, which resolves the
| tenant from the request's subdomain before anything else runs (Admin/
| User data itself lives per-tenant). GodAdmin's own routes (routes/god.php,
| Sprint 0.3) are registered separately and are never wrapped by this.
|
*/

// Landlord-level public marketing/signup surface - deliberately NOT wrapped
// by `tenant.identify` below, since these run on the root domain before any
// tenant exists (a visitor hasn't picked one yet). Mirrors the reasoning in
// routes/web.php's `/signup` route.
Route::prefix('public')->group(function () {
    Route::get('/subscription-plans', [PublicSubscriptionPlanController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/subscription-plans/{slug}', [PublicSubscriptionPlanController::class, 'show'])->middleware('throttle:60,1');
    Route::post('/signup', [PublicTenantSignupController::class, 'store'])->middleware('throttle:5,60');
});

Route::middleware(['tenant.identify'])->group(function () {

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    // Public settings (no auth required)
    Route::get('/settings/public', [SettingController::class, 'public']);

    // Public tenant branding - resolved by tenant.identify above, no login
    // required so Nuxt can theme the login page before auth.
    Route::get('/tenant/theme', [TenantController::class, 'theme']);

    // Auth routes
    Route::post('/login', LoginController::class);
    Route::post('/register', RegisterController::class);
    Route::post('/verify-email', VerifyEmailController::class);
    Route::post('/resend-verification-code', ResendVerificationCodeController::class);
    Route::post('/forgot-password', ForgotPasswordController::class);
    Route::post('/reset-password', ResetPasswordController::class);

    // User notifications + profile
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        Route::get('/user/me', [UserProfileController::class, 'show']);
        Route::patch('/user/me', [UserProfileController::class, 'update']);
        Route::patch('/user/password', [UserProfileController::class, 'changePassword']);
    });

    // Chat routes
    Route::middleware(['auth:sanctum', 'update.last.seen'])->group(function () {
        Route::post('/chat/create-private', [ChatController::class, 'createPrivateChat']);
        Route::post('/chat/create-group', [ChatController::class, 'createGroupChat']);
        Route::post('/chat/{chatId}/send', [ChatController::class, 'sendMessageToChat']);
        Route::get('/chat/{chatId}/messages', [ChatController::class, 'getChatMessages']);
        Route::post('/chat/{chatId}/read', [ChatController::class, 'markMessagesAsRead']);
        Route::get('/chat/{chatId}/unread-count', [ChatController::class, 'getUnreadCount']);
        Route::get('/chat/conversation/{otherUserId}/{otherUserType}', [ChatController::class, 'getConversation']);
        Route::get('/chats', [ChatController::class, 'getChats']);
        Route::post('/broadcasting/auth', [ChatController::class, 'broadcastAuth']);

        // Message edit / delete
        Route::patch('/chat/{chatId}/messages/{messageId}', [ChatController::class, 'editMessage']);
        Route::delete('/chat/{chatId}/messages/{messageId}', [ChatController::class, 'deleteMessage']);

        // User search
        Route::get('/users/search', [ChatController::class, 'searchUsers']);

        // Group chat management
        Route::post('/chat/{chatId}/participants', [ChatController::class, 'addParticipant']);
        Route::delete('/chat/{chatId}/participants/{userId}', [ChatController::class, 'removeParticipant']);
        Route::delete('/chat/{chatId}/leave', [ChatController::class, 'leaveChat']);
        Route::patch('/chat/{chatId}/name', [ChatController::class, 'renameChat']);
    });

    // Admin Auth routes
    Route::prefix('admin')->group(function () {
        Route::post('/login', AdminLoginController::class);
        Route::post('/register', AdminRegisterController::class);

        // Protected admin routes
        Route::middleware(['auth:sanctum', 'admin.auth', 'update.last.seen'])->group(function () {
            // Role management routes
            Route::get('/roles', [RoleController::class, 'index']);
            Route::post('/role/create', [RoleController::class, 'create']);
            Route::put('/role/update', [RoleController::class, 'update']);
            Route::post('/role/delete', [RoleController::class, 'delete']);
            Route::post('/role/update-permissions', [RoleController::class, 'updatePermissions']);
            Route::get('/permissions', [PermissionController::class, 'index']);


            // Admin self-profile (current authenticated admin)
            Route::get('/me', [AdminProfileController::class, 'show']);
            Route::patch('/me', [AdminProfileController::class, 'update']);
            Route::patch('/me/password', [AdminProfileController::class, 'changePassword']);

            // Admin management routes
            Route::get('/admins', [AdminController::class, 'index']);
            Route::post('/admins', [AdminController::class, 'create']);
            Route::put('/admins', [AdminController::class, 'update']);
            Route::delete('/admins', [AdminController::class, 'delete']);

            // User management routes
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/users/{id}', [UserController::class, 'show']);
            Route::post('/users', [UserController::class, 'create']);
            Route::put('/users/{id}', [UserController::class, 'update']);
            Route::delete('/users/{id}', [UserController::class, 'delete']);

            // Dashboard
            Route::get('/dashboard', [DashboardController::class, 'index']);

            // Settings routes
            Route::get('/settings', [SettingController::class, 'index']);
            Route::get('/settings/{key}', [SettingController::class, 'show']);
            Route::put('/settings/{key}', [SettingController::class, 'update']);
            Route::put('/settings', [SettingController::class, 'updateMany']);

            // Admin notifications
            Route::get('/notifications', [AdminNotificationController::class, 'index']);
            Route::get('/notifications/unread-count', [AdminNotificationController::class, 'unreadCount']);
            Route::post('/notifications/{id}/read', [AdminNotificationController::class, 'markRead']);
            Route::post('/notifications/read-all', [AdminNotificationController::class, 'markAllRead']);

            // Admin chat management (requires chat-manage permission)
            Route::get('/chats', [AdminChatController::class, 'allChats']);
            Route::get('/chats/{chatId}/messages', [AdminChatController::class, 'chatMessages']);

            // File management
            Route::get('/files', [FileController::class, 'index']);
            Route::post('/files', [FileController::class, 'upload']);
            Route::delete('/files/{id}', [FileController::class, 'delete']);

            // Tenant subscription plan / branding (tenant owner only)
            Route::middleware('tenant.owner')->group(function () {
                Route::patch('/tenant/subscription-plan', [TenantController::class, 'updateSubscriptionPlan']);
                Route::patch('/tenant/branding', [TenantController::class, 'updateBranding']);
            });

            // Audit routes
            Route::prefix('audit')->group(function () {
                Route::get('/', [AuditController::class, 'index']);
                Route::get('/{id}', [AuditController::class, 'show']);
                Route::get('/model/{type}/{id}', [AuditController::class, 'modelHistory']);
                Route::get('/user/{type}/{id}', [AuditController::class, 'userActivity']);
                Route::get('/action/{action}', [AuditController::class, 'byAction']);
                Route::get('/tag/{tag}', [AuditController::class, 'byTag']);
            });

        });
    });

    // File serve — authenticated download/stream for local-disk files
    Route::middleware('auth:sanctum')->get('/files/serve/{encodedPath}', [FileController::class, 'serve'])
        ->where('encodedPath', '.+');

    // Broadcast routes for private channels
    Route::middleware('auth:sanctum')->post('/broadcasting/auth', function (Request $request) {
        return \Illuminate\Support\Facades\Broadcast::auth($request);
    });

});
