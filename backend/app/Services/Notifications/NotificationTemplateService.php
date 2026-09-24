<?php

namespace App\Services\Notifications;

use App\Models\NotificationSetting;
use App\Models\Shop;
use Illuminate\Support\Collection;

/**
 * NOTIFICATIONS: owns `notification_settings` — get/update the 9
 * per-type rows, and sanitizes merchant-authored subject/message
 * content before it's ever persisted. See docs/SETTINGS.md.
 *
 * SANITIZATION: a small, dependency-free allow-list sanitizer rather
 * than pulling in a full HTML-purifier library — merchant notification
 * copy only ever needs basic text formatting (bold/italic/links/line
 * breaks), never scripts, styles, iframes, or event handlers. Every
 * disallowed tag is stripped entirely (not escaped-and-shown), and
 * every attribute on an allowed tag is dropped except `href` on `<a>`
 * (itself restricted to `http(s)://` — never `javascript:`).
 */
class NotificationTemplateService
{
    private const ALLOWED_TAGS = ['a', 'b', 'strong', 'i', 'em', 'br', 'p', 'ul', 'ol', 'li'];

    public function allForShop(Shop $shop): Collection
    {
        $existing = NotificationSetting::query()->where('shop_id', $shop->id)->get()->keyBy('type');

        return collect(NotificationSetting::TYPES)->map(
            fn (string $type) => $existing->get($type) ?? new NotificationSetting(['shop_id' => $shop->id, 'type' => $type, 'enabled' => true])
        );
    }

    public function get(Shop $shop, string $type): NotificationSetting
    {
        return NotificationSetting::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'type' => $type],
            ['enabled' => true],
        );
    }

    public function update(Shop $shop, string $type, array $attributes): NotificationSetting
    {
        if (isset($attributes['subject'])) {
            $attributes['subject'] = strip_tags($attributes['subject']); // a subject line never needs any markup at all
        }

        if (isset($attributes['message'])) {
            $attributes['message'] = $this->sanitize($attributes['message']);
        }

        $setting = $this->get($shop, $type);
        $setting->update($attributes);

        return $setting->fresh();
    }

    public function sanitize(string $html): string
    {
        // Step 1: remove every tag that isn't on the allow-list
        // entirely (strip_tags() only filters by tag NAME — it leaves
        // attributes on whatever tags it keeps untouched, which is
        // exactly what step 2 handles).
        $stripped = strip_tags($html, '<'.implode('><', self::ALLOWED_TAGS).'>');

        // Step 2: rebuild EVERY remaining opening tag from scratch,
        // keeping only the tag name — and, for <a> specifically, a
        // validated http(s) href. This is what actually removes
        // dangerous attributes (onclick, style, javascript: hrefs, or
        // anything else) from EVERY allowed tag, not just <a> — an
        // earlier version of this method only special-cased <a> and
        // left attributes on every other allowed tag (<p onclick="...">,
        // <b style="...">) completely untouched, which would have been
        // a real XSS gap.
        return preg_replace_callback('/<(\/?)([a-z]+)([^>]*)>/i', function ($matches) {
            [, $closing, $tagName, $attributes] = $matches;
            $tagName = strtolower($tagName);

            if (! in_array($tagName, self::ALLOWED_TAGS, true)) {
                return ''; // shouldn't be reachable after strip_tags(), but never trust a single layer alone
            }

            if ($closing) {
                return "</{$tagName}>";
            }

            if ($tagName === 'a' && preg_match('/href\s*=\s*["\']?(https?:\/\/[^"\'>\s]+)["\']?/i', $attributes, $hrefMatch)) {
                return '<a href="'.htmlspecialchars($hrefMatch[1], ENT_QUOTES).'">';
            }

            return "<{$tagName}>"; // every other allowed tag: name only, every attribute dropped
        }, $stripped);
    }
}
