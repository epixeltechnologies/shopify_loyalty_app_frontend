<?php

namespace Tests\Unit\Services\Analytics;

use App\Models\Shop;
use App\Services\Analytics\DateRangeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DateRangeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_resolves_to_the_shops_current_day(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);

        [$start, $end] = app(DateRangeResolver::class)->resolve($shop, 'today');

        $this->assertTrue($start->isToday());
        $this->assertTrue($end->isToday());
        $this->assertTrue($start->lt($end));
    }

    public function test_last_7_days_spans_exactly_seven_days_inclusive(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);

        [$start, $end] = app(DateRangeResolver::class)->resolve($shop, 'last_7_days');

        $this->assertSame(6, $start->diffInDays($end->copy()->startOfDay()));
    }

    public function test_this_month_starts_on_the_first_of_the_month(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);

        [$start] = app(DateRangeResolver::class)->resolve($shop, 'this_month');

        $this->assertSame(1, $start->day);
    }

    public function test_last_month_covers_the_entire_previous_calendar_month(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);

        [$start, $end] = app(DateRangeResolver::class)->resolve($shop, 'last_month');

        $expectedStart = Carbon::now('UTC')->subMonthNoOverflow()->startOfMonth();
        $this->assertSame($expectedStart->format('Y-m-d'), $start->format('Y-m-d'));
        $this->assertSame($start->month, $end->month); // both within the same (previous) month
    }

    public function test_custom_range_requires_both_from_and_to(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);

        $this->expectException(\InvalidArgumentException::class);
        app(DateRangeResolver::class)->resolve($shop, 'custom', null, null);
    }

    public function test_custom_range_resolves_the_given_dates(): void
    {
        $shop = Shop::factory()->create(['timezone' => 'UTC']);

        [$start, $end] = app(DateRangeResolver::class)->resolve($shop, 'custom', '2026-01-01', '2026-01-31');

        $this->assertSame('2026-01-01', $start->format('Y-m-d'));
        $this->assertSame('2026-01-31', $end->format('Y-m-d'));
    }

    public function test_boundaries_are_computed_in_the_shops_own_timezone_not_utc(): void
    {
        // A shop in Auckland (UTC+13): "today" there can already be a
        // different calendar day than UTC "today" for part of every day.
        $shop = Shop::factory()->create(['timezone' => 'Pacific/Auckland']);

        [$start] = app(DateRangeResolver::class)->resolve($shop, 'today');

        $expectedStartOfDayAuckland = Carbon::now('Pacific/Auckland')->startOfDay()->utc();
        $this->assertSame($expectedStartOfDayAuckland->format('Y-m-d H:i'), $start->format('Y-m-d H:i'));
    }

    public function test_a_shop_with_no_timezone_falls_back_to_utc(): void
    {
        $shop = Shop::factory()->create(['timezone' => null]);

        [$start] = app(DateRangeResolver::class)->resolve($shop, 'today');

        $this->assertSame(Carbon::now('UTC')->startOfDay()->format('Y-m-d H:i'), $start->format('Y-m-d H:i'));
    }
}
