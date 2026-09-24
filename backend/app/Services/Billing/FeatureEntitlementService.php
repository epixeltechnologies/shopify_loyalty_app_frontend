<?php

namespace App\Services\Billing;

/**
 * Same-behavior alias for EntitlementService, under the name this
 * architecture's documentation and task requirements refer to it by.
 * See EntitlementService's docblock for why this is an alias rather
 * than a rename: `$shop->entitlements()` and every existing call site
 * continue to type-hint/return `EntitlementService` unchanged, while
 * new code is free to depend on `FeatureEntitlementService` explicitly
 * when "this is a feature-flag lookup" is the clearer name to read at
 * the call site.
 */
class FeatureEntitlementService extends EntitlementService {}
