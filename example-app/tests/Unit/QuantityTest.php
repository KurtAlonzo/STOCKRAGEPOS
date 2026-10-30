<?php

namespace Tests\Unit;

use App\Support\Quantity;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuantityTest extends TestCase
{
    public function test_exact_quantities_and_arithmetic(): void
    {
        $this->assertSame(125, Quantity::parse('0.125', 'g'));
        $this->assertSame(2000, Quantity::parse('2', 'each'));
        $this->assertSame('-0.125', Quantity::format(-125));
        $this->assertSame(375, Quantity::multiply(125, 3));
        $this->assertSame(-100000, Quantity::add(250000, -350000));
        $this->assertSame(9007199254740991, Quantity::parse('9007199254740.991', 'ml'));
    }

    public function test_invalid_quantities_are_rejected(): void
    {
        foreach (['0.0001', '1e3', '1,000', '-1', '9007199254740.992'] as $value) {
            try {
                Quantity::parse($value, 'g');
                $this->fail('Accepted '.$value);
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(ValidationException::class);
        Quantity::parse('0.5', 'each');
    }

    public function test_overflow_is_rejected_before_arithmetic(): void
    {
        $this->expectException(ValidationException::class);
        Quantity::multiply(9007199254740991, 2);
    }
}
