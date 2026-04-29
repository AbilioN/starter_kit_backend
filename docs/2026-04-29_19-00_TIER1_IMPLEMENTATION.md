# Tier 1 — Core Infrastructure Implementation
**Created:** 2026-04-29 19:00  
**Status:** Complete (pending `sudo docker compose up -d --build && php artisan migrate:fresh --seed`)

---

## What Was Built

Four systems implemented following the existing Clean Architecture pattern:
`Domain Entities/Interfaces → Infrastructure Implementations → Application Use Cases → Presentation Controllers`

---

## 1. Settings System

**Purpose:** DB-backed key/value store with Redis caching. Foundation for feature flags, white-label, and module toggles.

### Files
| Layer | Path |
|---|---|
| Migration | `database/migrations/2026_04_29_000001_create_settings_table.php` |
| Model | `app/Models/Setting.php` |
| Domain Entity | `app/Domain/Entities/Setting.php` |
| Repository Interface | `app/Domain/Repositories/SettingRepositoryInterface.php` |
| Repository Impl | `app/Infrastructure/Repositories/SettingRepository.php` |
| DTO | `app/Application/DTOs/Setting/SettingDto.php` |
| Use Cases | `app/Application/UseCases/Setting/{GetAllSettings,GetSettingByKey,UpdateSetting}UseCase.php` |
| Controller | `app/Http/Controllers/Api/Admin/SettingController.php` |
| FormRequest | `app/Http/Requests/Admin/UpdateSettingRequest.php` |
| Seeder | `database/seeders/SettingSeeder.php` |
| Helper | `app/Helpers/Settings.php` |
| Exception | `app/Domain/Exceptions/SettingNotFoundException.php` |
| Tests | `tests/Feature/Admin/SettingTest.php` |

### API Endpoints
```
GET  /api/settings/public              — public settings (no auth)
GET  /api/admin/settings               — list all (setting-read)
GET  /api/admin/settings?group=features — filter by group
GET  /api/admin/settings/{key}         — single setting (setting-read)
PUT  /api/admin/settings/{key}         — update one (setting-update)
PUT  /api/admin/settings               — update many (setting-update)
```

### Helper Usage (anywhere in code)
```php
use App\Helpers\Settings;

Settings::get('app.name');                     // 'Starter Kit'
Settings::get('features.chat', false);         // true (boolean cast)
Settings::isEnabled('features.ai_agent');      // false
Settings::set('app.name', 'My App');           // updates + busts cache
```

### Seeded Settings Groups
- `general` — app.name, app.timezone
- `features` — chat, file_upload, notifications, ai_agent (all feature flags)
- `email` — from_name, from_address, welcome_enabled
- `storage` — default_disk, max_upload_mb, allowed_mimes

### New Permissions
- `setting-read` — view settings
- `setting-update` — update settings

### Cache Strategy
- `SettingRepository` is bound as **singleton** so cache state is shared within a request
- Cache TTL: 1 hour
- Keys: `setting:{key}`, `settings:all`, `settings:public`
- Cache busted automatically on any `update()` call

---

## 2. Laravel Horizon

**Purpose:** Unified queue management dashboard. Replaces two separate `queue_messages` and `queue_events` Docker containers with a single Horizon process that manages all queues.

### Files
| Layer | Path |
|---|---|
| Package | Added `laravel/horizon` to `composer.json` |
| Config | `config/horizon.php` |
| Docker | `docker-compose.yml` — replaced 2 queue workers with `horizon` service |
| Auth | `AppServiceProvider::boot()` — Horizon auth gate (super admin only in production) |

### Queues Managed by Horizon
- `default` — general jobs
- `message_processing` — chat message jobs
- `notifications` — notification dispatch jobs

### Dashboard URL
```
http://localhost:8006/horizon
```
Access: anyone in `local`, super admin only in `production`.

### .env changes
```
QUEUE_CONNECTION=redis   # was: database
CACHE_STORE=redis        # was: database
REDIS_HOST=redis         # was: 127.0.0.1 (use service name inside Docker)
```

---

## 3. Notification System

**Purpose:** Database (in-app) + email notifications using Laravel's built-in `Notifiable`. Both Admin and User models already use the `Notifiable` trait.

### Files
| Layer | Path |
|---|---|
| Migration | `database/migrations/2026_04_29_000002_create_notifications_table.php` |
| Repository Interface | `app/Domain/Repositories/NotificationRepositoryInterface.php` |
| Repository Impl | `app/Infrastructure/Repositories/NotificationRepository.php` |
| DTO | `app/Application/DTOs/Notification/NotificationDto.php` |
| Use Cases | `app/Application/UseCases/Notification/{GetNotifications,MarkNotificationAsRead}UseCase.php` |
| Admin Controller | `app/Http/Controllers/Api/Admin/NotificationController.php` |
| User Controller | `app/Http/Controllers/Api/NotificationController.php` |
| Notification Classes | `app/Notifications/{Welcome,AdminAction,PasswordChanged}Notification.php` |
| Tests | `tests/Feature/Admin/NotificationTest.php` |

### API Endpoints (Admin)
```
GET  /api/admin/notifications                  — list (with ?unread_only=true)
GET  /api/admin/notifications/unread-count     — badge count
POST /api/admin/notifications/{id}/read        — mark one read
POST /api/admin/notifications/read-all         — mark all read
```

### API Endpoints (User)
```
GET  /api/notifications                        — list (Sanctum auth)
GET  /api/notifications/unread-count
POST /api/notifications/{id}/read
POST /api/notifications/read-all
```

### Sending Notifications (from any use case or event)
```php
use App\Notifications\WelcomeNotification;
use App\Notifications\AdminActionNotification;

$user->notify(new WelcomeNotification($user->name));

$admin->notify(new AdminActionNotification(
    title: 'New user registered',
    message: 'John Doe just created an account.',
    actionUrl: '/admin/users/123',
));
```

### Response Shape
```json
{
  "id": "uuid",
  "type": "WelcomeNotification",
  "data": { "title": "...", "message": "...", "action_url": null },
  "read_at": null,
  "created_at": "2026-04-29 19:00:00"
}
```

---

## 4. File Storage System

**Purpose:** S3-compatible file storage with local fallback. Supports any uploaded file type, polymorphic ownership (Admin or User), and logical folder grouping.

### Files
| Layer | Path |
|---|---|
| Migration | `database/migrations/2026_04_29_000003_create_files_table.php` |
| Model | `app/Models/File.php` (with SoftDeletes) |
| Domain Entity | `app/Domain/Entities/File.php` |
| Repository Interface | `app/Domain/Repositories/FileRepositoryInterface.php` |
| Repository Impl | `app/Infrastructure/Repositories/FileRepository.php` |
| Storage Service Interface | `app/Domain/Services/StorageServiceInterface.php` |
| Storage Service Impl | `app/Infrastructure/Services/StorageService.php` |
| DTO | `app/Application/DTOs/File/FileDto.php` |
| Use Cases | `app/Application/UseCases/File/{Upload,GetFiles,DeleteFile}UseCase.php` |
| Controller | `app/Http/Controllers/Api/Admin/FileController.php` |
| FormRequest | `app/Http/Requests/UploadFileRequest.php` |
| Exception | `app/Domain/Exceptions/FileNotFoundException.php` |
| Tests | `tests/Feature/Admin/FileTest.php` |

### API Endpoints (Admin)
```
GET    /api/admin/files              — list files (file-read), ?folder=photos
POST   /api/admin/files              — upload (file-upload) — multipart/form-data
DELETE /api/admin/files/{id}         — delete (file-delete)
```

### New Permissions
- `file-read` — list/view files
- `file-upload` — upload files
- `file-delete` — delete files

### Upload Request Fields
```
file        required|file|max:102400  (100MB)
folder      nullable|string           e.g. "photos", "documents"
disk        nullable|in:local,s3      default: local
is_public   nullable|boolean
meta        nullable|array
```

### Response Shape
```json
{
  "id": 1,
  "original_name": "photo.jpg",
  "mime_type": "image/jpeg",
  "size": 204800,
  "size_human": "200 KB",
  "folder": "photos",
  "is_public": false,
  "url": "http://localhost:8006/api/files/serve/...",
  "meta": null,
  "created_at": "2026-04-29 19:00:00"
}
```

### Switching to S3
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket
```
`StorageService` detects the disk and returns the correct URL (CDN for S3, local API route for `local`).

---

## DomainServiceProvider Updates
All new bindings added to `app/Providers/DomainServiceProvider.php`:
- `SettingRepositoryInterface` → `SettingRepository` (singleton)
- `NotificationRepositoryInterface` → `NotificationRepository`
- `FileRepositoryInterface` → `FileRepository`
- `StorageServiceInterface` → `StorageService`

---

## How to Activate

```bash
# 1. Rebuild Docker (installs laravel/horizon, runs composer install)
sudo docker compose up -d --build

# 2. Run migrations + seed
sudo docker compose exec app php artisan migrate:fresh --seed

# 3. Publish Horizon assets (once after install)
sudo docker compose exec app php artisan horizon:install
sudo docker compose exec app php artisan horizon:publish

# 4. Run tests
sudo docker compose exec app php artisan test --filter=SettingTest
sudo docker compose exec app php artisan test --filter=NotificationTest
sudo docker compose exec app php artisan test --filter=FileTest
```

---

## Notes for Future Agents
- `Settings::get()` / `Settings::isEnabled()` is available globally — use it before loading chat routes, AI routes, etc.
- File soft-delete is in place — `FileRepository::delete()` soft-deletes the DB row AND physically removes the file from storage.
- `WelcomeNotification` is `ShouldQueue` — it goes through Horizon automatically.
- The `notifications` table uses UUID primary keys (Laravel standard).
- `SettingRepository` is a **singleton** — one instance per request, safe for cache consistency.
