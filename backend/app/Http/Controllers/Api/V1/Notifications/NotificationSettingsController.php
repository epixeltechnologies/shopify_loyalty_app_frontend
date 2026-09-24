<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\PreviewNotificationRequest;
use App\Http\Requests\Notifications\UpdateNotificationSettingRequest;
use App\Models\NotificationSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\NotificationTemplateService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class NotificationSettingsController extends Controller
{
    public function __construct(
        private readonly NotificationTemplateService $templates,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /** GET notification templates — every one of the 9 types, merchant customization if set. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->templates->allForShop(TenantContext::shop())->values()]);
    }

    public function show(string $type): JsonResponse
    {
        $this->assertKnownType($type);

        return response()->json(['data' => $this->templates->get(TenantContext::shop(), $type)]);
    }

    /** UPDATE notification templates — subject/message are sanitized before ever being persisted, see NotificationTemplateService::sanitize(). */
    public function update(UpdateNotificationSettingRequest $request, string $type): JsonResponse
    {
        $this->assertKnownType($type);
        $shop = TenantContext::shop();
        $before = $this->templates->get($shop, $type)->only(['enabled', 'subject', 'message']);

        $setting = $this->templates->update($shop, $type, $request->validated());

        $this->audit->logModelChange($shop, 'notification_settings.updated', $setting, $before);

        return response()->json(['data' => $setting]);
    }

    /** PREVIEW notification — renders with sample data, sends nothing. */
    public function preview(PreviewNotificationRequest $request, string $type): JsonResponse
    {
        $this->assertKnownType($type);

        $preview = $this->notifications->preview(
            TenantContext::shop(),
            $type,
            $request->validated('subject'),
            $request->validated('message'),
        );

        return response()->json(['data' => $preview]);
    }

    private function assertKnownType(string $type): void
    {
        if (! in_array($type, NotificationSetting::TYPES, true)) {
            throw ValidationException::withMessages(['type' => ["Unknown notification type [{$type}]."]]);
        }
    }
}
