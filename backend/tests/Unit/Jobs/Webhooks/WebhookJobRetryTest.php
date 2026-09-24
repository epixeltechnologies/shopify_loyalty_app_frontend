<?php

namespace Tests\Unit\Jobs\Webhooks;

use App\Jobs\Webhooks\WebhookJob;
use App\Models\Shop;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Directly exercises App\Jobs\Webhooks\WebhookJob's status-lifecycle
 * template — deliberately a Unit test calling handle()/failed()
 * directly rather than a Feature test through the HTTP endpoint, since
 * Laravel's `sync` queue driver (used in tests — see phpunit.xml) runs
 * a job exactly once and immediately invokes the framework's own
 * failed-job handling on any exception, which makes asserting
 * multi-attempt retry behavior through a real dispatch unreliable. This
 * calls the job's own methods directly, which is what actually
 * determines its behavior regardless of which queue driver is running it.
 */
class WebhookJobRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_job_marks_the_event_processed(): void
    {
        $webhook = Webhook::factory()->create(['status' => 'received']);
        $job = new AlwaysSucceedsTestJob($webhook->id);

        $job->handle();

        $webhook->refresh();
        $this->assertSame('processed', $webhook->status);
        $this->assertNotNull($webhook->processed_at);
    }

    public function test_a_failed_attempt_records_the_error_without_marking_the_event_terminally_failed(): void
    {
        $webhook = Webhook::factory()->create(['status' => 'received', 'attempts' => 0]);
        $job = new AlwaysThrowsTestJob($webhook->id);

        try {
            $job->handle();
            $this->fail('Expected the job to throw.');
        } catch (RuntimeException) {
            // expected — WebhookJob rethrows so the queue's retry/backoff takes over
        }

        $webhook->refresh();
        $this->assertSame(1, $webhook->attempts);
        $this->assertSame('processing', $webhook->status); // not yet terminally failed
        $this->assertStringContainsString('simulated failure', $webhook->error);
    }

    public function test_exhausting_retries_calls_failed_and_marks_the_event_permanently_failed(): void
    {
        $webhook = Webhook::factory()->create(['status' => 'received']);
        $job = new AlwaysThrowsTestJob($webhook->id);

        $job->failed(new RuntimeException('final simulated failure'));

        $webhook->refresh();
        $this->assertSame('failed', $webhook->status);
        $this->assertNotNull($webhook->failed_at);
        $this->assertStringContainsString('final simulated failure', $webhook->error);
    }

    public function test_an_already_terminal_event_is_skipped_not_reprocessed(): void
    {
        $webhook = Webhook::factory()->processed()->create();
        $job = new AlwaysThrowsTestJob($webhook->id); // would throw if it ran

        $job->handle(); // should return early without calling process()

        $this->addToAssertionCount(1); // reaching here without an exception is the assertion
    }

    public function test_a_missing_event_row_is_handled_gracefully(): void
    {
        $job = new AlwaysSucceedsTestJob(999999);

        $job->handle();

        $this->addToAssertionCount(1); // must not throw
    }
}

/** @internal test fixture — not a real webhook topic handler. */
class AlwaysSucceedsTestJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        // no-op success
    }
}

/** @internal test fixture — not a real webhook topic handler. */
class AlwaysThrowsTestJob extends WebhookJob
{
    protected function process(Webhook $webhook, ?Shop $shop): void
    {
        throw new RuntimeException('simulated failure');
    }
}
