<?php

namespace Tests\Unit\Services\Notifications;

use App\Services\Notifications\NotificationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function sanitize(string $html): string
    {
        return app(NotificationTemplateService::class)->sanitize($html);
    }

    public function test_allowed_formatting_tags_pass_through(): void
    {
        $result = $this->sanitize('<p>Hi <b>there</b>, <i>welcome</i>!</p>');

        $this->assertSame('<p>Hi <b>there</b>, <i>welcome</i>!</p>', $result);
    }

    public function test_script_tags_are_removed_entirely(): void
    {
        $result = $this->sanitize('<p>Hello</p><script>alert(document.cookie)</script>');

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function test_onclick_and_other_attributes_are_stripped_from_every_allowed_tag_not_just_anchors(): void
    {
        // Regression test: an earlier version of this sanitizer only
        // stripped dangerous attributes from <a> tags, leaving
        // <p onclick="..."> and <b style="..."> completely untouched —
        // a real XSS gap caught during development.
        $result = $this->sanitize('<p onclick="evil()">Text</p><b style="background:url(javascript:evil())">Bold</b>');

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('style', $result);
        $this->assertStringNotContainsString('evil', $result);
        $this->assertSame('<p>Text</p><b>Bold</b>', $result);
    }

    public function test_a_javascript_href_is_dropped(): void
    {
        $result = $this->sanitize('<a href="javascript:alert(1)">Click me</a>');

        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertSame('<a>Click me</a>', $result);
    }

    public function test_a_valid_http_href_is_preserved(): void
    {
        $result = $this->sanitize('<a href="https://example.com/redeem">Redeem now</a>');

        $this->assertSame('<a href="https://example.com/redeem">Redeem now</a>', $result);
    }

    public function test_an_a_tag_with_onclick_and_a_valid_href_keeps_only_the_href(): void
    {
        $result = $this->sanitize('<a href="https://example.com" onclick="steal()">Link</a>');

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('steal', $result);
        $this->assertSame('<a href="https://example.com">Link</a>', $result);
    }

    public function test_disallowed_tags_like_iframe_and_img_are_removed(): void
    {
        $result = $this->sanitize('<p>Text</p><iframe src="evil.com"></iframe><img src="x" onerror="evil()">');

        $this->assertStringNotContainsString('<iframe', $result);
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringNotContainsString('evil', $result);
    }
}
