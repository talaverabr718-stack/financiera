<?php

namespace Tests\Unit;

use App\Services\SimpleInterestProjectionService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SimpleInterestProjectionServiceTest extends TestCase
{
    public function test_it_matches_the_approved_three_month_weekly_example(): void
    {
        $projection = (new SimpleInterestProjectionService)->calculate('5000', '16', 3, 'monthly', 'weekly');

        $this->assertSame(12, $projection['payments']);
        $this->assertSame('48.000000', $projection['total_rate']);
        $this->assertSame('2400.00', $projection['total_interest']);
        $this->assertSame('7400.00', $projection['total_payable']);
        $this->assertSame('616.67', $projection['installment_amount']);
        $this->assertSame('616.63', $projection['last_payment']);
    }

    #[DataProvider('termConversions')]
    public function test_it_converts_commercial_terms_to_payment_counts(string $unit, int $term, string $frequency, int $payments): void
    {
        $projection = (new SimpleInterestProjectionService)->calculate('1000', '0', $term, $unit, $frequency);

        $this->assertSame($payments, $projection['payments']);
    }

    public static function termConversions(): array
    {
        return [
            'three months weekly' => ['monthly', 3, 'weekly', 12],
            'three months biweekly' => ['monthly', 3, 'biweekly', 6],
            'one year monthly' => ['yearly', 1, 'monthly', 12],
            'two weeks daily' => ['weekly', 2, 'daily', 14],
            'ten days weekly' => ['daily', 10, 'weekly', 2],
        ];
    }

    public function test_it_rejects_more_than_365_payments(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SimpleInterestProjectionService)->calculate('1000', '5', 2, 'yearly', 'daily');
    }
}