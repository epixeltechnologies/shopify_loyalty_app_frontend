<?php

namespace Tests\Unit\Rules;

use App\Rules\HexColor;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HexColorTest extends TestCase
{
    public function test_valid_hex_colors_pass(): void
    {
        foreach (['#1A2B3C', '#ffffff', '#000000'] as $color) {
            $validator = Validator::make(['color' => $color], ['color' => [new HexColor]]);
            $this->assertFalse($validator->fails(), "Expected {$color} to be valid.");
        }
    }

    public function test_invalid_hex_colors_fail(): void
    {
        foreach (['not-a-color', '#12345', '#gggggg', '123456'] as $color) {
            $validator = Validator::make(['color' => $color], ['color' => [new HexColor]]);
            $this->assertTrue($validator->fails(), "Expected {$color} to be invalid.");
        }
    }
}
