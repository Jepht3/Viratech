<?php

namespace Tests\Feature;

use App\Models\Corridor;
use App\Services\FeeCalculator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class FeeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function corridor(string $code): Corridor
    {
        $this->seed(DatabaseSeeder::class);

        return Corridor::with('tiers')->where('code', $code)->firstOrFail();
    }

    public function test_paypal_vers_equity_par_paliers(): void
    {
        $c = $this->corridor('paypal_equity');
        $f = new FeeCalculator;

        $this->assertSame('180.00', $f->quote($c, 200)['net']);        // 10 %
        $this->assertSame('135.00', $f->quote($c, 150)['net']);        // minimum
        $this->assertSame('449.10', $f->quote($c, 499)['net']);        // 499 : encore 10 % (49,90)
        $this->assertSame('465.00', $f->quote($c, 500)['net']);        // 500 : 7 %
        $this->assertSame('930.00', $f->quote($c, 1000)['net']);
        $this->assertSame('1860.00', $f->quote($c, 2000)['net']);      // 2 000 : encore 7 %
        $this->assertSame('2350.00', $f->quote($c, 2500)['net']);      // 2 001 et plus : 6 %
        $this->assertSame('7.00', $f->quote($c, 2000.50)['percent']);  // 2 000,50 reste à 7 %
        $this->assertSame('1860.46', $f->quote($c, 2000.50)['net']);
    }

    public function test_vers_mobile_money_ajoute_2_dollars_fixes(): void
    {
        $c = $this->corridor('paypal_mobile');
        $q = (new FeeCalculator)->quote($c, 200);

        $this->assertSame('20.00', $q['percent_fee']);
        $this->assertSame('2.00', $q['fixed_fee']);
        $this->assertSame('22.00', $q['total_fee']);
        $this->assertSame('178.00', $q['net']);
        $this->assertSame('928.00', (new FeeCalculator)->quote($c, 1000)['net']);
    }

    public function test_mobile_money_vers_paypal_10_pour_cent_minimum_100(): void
    {
        $c = $this->corridor('mobile_paypal');
        $q = (new FeeCalculator)->quote($c, 100);

        $this->assertSame('10.00', $q['percent_fee']);
        $this->assertSame('88.00', $q['net']); // 100 - 10 % - 2 $ fixes
        $this->assertSame('10.00', $q['percent']);
        $this->assertSame('2698.00', (new FeeCalculator)->quote($c, 3000)['net']); // 10 % quel que soit le montant
    }

    public function test_equity_vers_paypal_sans_frais_fixe(): void
    {
        $c = $this->corridor('equity_paypal');
        $this->assertSame('90.00', (new FeeCalculator)->quote($c, 100)['net']);
    }

    public function test_sous_le_minimum_est_refuse(): void
    {
        $c = $this->corridor('paypal_equity');
        $this->expectException(InvalidArgumentException::class);
        (new FeeCalculator)->quote($c, 149.99);
    }
}
