# Implemented Features (Backend)

All use cases below exist in `app/Application/UseCases/`.

## Auth — Users (`/api/*`)

| Use Case | Endpoint | Notes |
|---|---|---|
| RegisterUseCase | `POST /api/register` | Creates user + sends verification code |
| VerifyEmailUseCase | `POST /api/verify-email` | Validates code, marks email verified |
| ResendVerificationCodeUseCase | `POST /api/resend-verification-code` | |
| LoginUseCase | `POST /api/login` | Returns `{ user, token }` |
| ForgotPasswordUseCase | `POST /api/forgot-password` | Sends reset link |
| ResetPasswordUseCase | `POST /api/reset-password` | Resets password via token |

## Auth — Admins (`/api/admin/auth/*`)

| Use Case | Endpoint | Notes |
|---|---|---|
| AdminRegisterUseCase | `POST /api/admin/register` | First-run admin creation |
| AdminLoginUseCase | `POST /api/admin/login` | Returns `{ admin, token, roles, channel }` |

## User Profile (`/api/user/*`)

| Use Case | Endpoint |
|---|---|
| GetUserProfileUseCase | `GET /api/user/me` |
| UpdateUserProfileUseCase | `PATCH /api/user/me` |
| ChangeUserPasswordUseCase | `PATCH /api/user/password` |
| SearchUsersUseCase | `GET /api/users/search?q=` |

## Admin Management (`/api/admin/*`)

| Use Case | Endpoint |
|---|---|
| GetAllAdminsUseCase | `GET /api/admin/admins` |
| CreateAdminUseCase | `POST /api/admin/admins` |
| UpdateAdminUseCase | `PUT /api/admin/admins/{id}` |
| DeleteAdminUseCase | `DELETE /api/admin/admins/{id}` |
| GetAdminProfileUseCase | `GET /api/admin/me` |
| UpdateAdminProfileUseCase | `PATCH /api/admin/me` |
| ChangeAdminPasswordUseCase | `PATCH /api/admin/me/password` |
| ListUsersUseCase | `GET /api/admin/users` |
| GetUserUseCase | `GET /api/admin/users/{id}` |
| AssignRoleToAdminUseCase | `POST /api/admin/admins/{id}/roles` |

## RBAC (`/api/admin/roles`, `/api/admin/permissions`)

| Use Case | Endpoint |
|---|---|
| GetRolesUseCase | `GET /api/admin/roles` |
| CreateRoleUseCase | `POST /api/admin/roles` |
| UpdateRoleUseCase | `PUT /api/admin/roles/{id}` |
| DeleteRoleUseCase | `DELETE /api/admin/roles/{id}` |
| GetAllPermissionsUseCase | `GET /api/admin/permissions` |
| AttachPermissionsToRoleUseCase | `POST /api/admin/roles/{id}/permissions` |
| UpdatePermissionsToRoleUseCase | `PUT /api/admin/roles/{id}/permissions` |
| GetAdminPermissionsUseCase | `GET /api/admin/me/permissions` |
| AssignRoleUseCase | `POST /api/admin/admins/{id}/roles/{roleId}` |
| CheckAdminPermissionUseCase | (internal) |
| AuthorizeActionUseCase | (internal guard) |

## Chat — Users (`/api/chat/*`, `/api/chats`)

| Use Case | Endpoint |
|---|---|
| GetChatsUseCase | `GET /api/chats` |
| CreatePrivateChatUseCase | `POST /api/chat/private` |
| CreateGroupChatUseCase | `POST /api/chat/group` |
| GetChatMessagesUseCase | `GET /api/chat/{chatId}/messages` |
| SendMessageUseCase | `POST /api/chat/{chatId}/messages` |
| SendMessageToChatUseCase | `POST /api/chat/{chatId}/send` (queued) |
| EditMessageUseCase | `PATCH /api/chat/{chatId}/messages/{id}` |
| DeleteMessageUseCase | `DELETE /api/chat/{chatId}/messages/{id}` |
| AddParticipantUseCase | `POST /api/chat/{chatId}/participants` |
| RemoveParticipantUseCase | `DELETE /api/chat/{chatId}/participants/{userId}` |
| LeaveChatUseCase | `DELETE /api/chat/{chatId}/leave` |
| RenameChatUseCase | `PATCH /api/chat/{chatId}` |
| GetConversationUseCase | `GET /api/chat/{chatId}` |

## Audit (`/api/admin/audit`)

| Use Case | Endpoint |
|---|---|
| GetAuditLogsUseCase | `GET /api/admin/audit` (paginated, filterable) |
| LogAuditUseCase | (internal — called from other use cases) |

## Files (`/api/admin/files`)

| Use Case | Endpoint |
|---|---|
| UploadFileUseCase | `POST /api/admin/files` |
| GetFilesUseCase | `GET /api/admin/files` |
| DeleteFileUseCase | `DELETE /api/admin/files/{id}` |

## Notifications (`/api/admin/notifications`)

| Use Case | Endpoint |
|---|---|
| GetNotificationsUseCase | `GET /api/admin/notifications` |
| MarkNotificationAsReadUseCase | `POST /api/admin/notifications/{id}/read` |

## Settings (`/api/admin/settings`)

| Use Case | Endpoint |
|---|---|
| GetAllSettingsUseCase | `GET /api/admin/settings` |
| GetSettingByKeyUseCase | `GET /api/admin/settings/{key}` |
| UpdateSettingUseCase | `PUT /api/admin/settings/{key}` |

## Dashboard

| Endpoint | Notes |
|---|---|
| `GET /api/admin/dashboard` | Returns aggregate metrics; currently minimal — needs expansion |

## Pusher Events (Broadcasting)

| Event class | Channel | Trigger |
|---|---|---|
| `MessageSent` | `private-user.{type}.{id}` (fan-out) | New message sent |
| `MessageEdited` | same | Message edited |
| `MessageDeleted` | same | Message soft-deleted |
| `MessageRead` | same | Message marked read |

Typing indicators are **client events** on `private-chat.{chatId}` — no server event class needed.
