<?php

namespace App\Services;

use App\Models\Corridor;
use InvalidArgumentException;

/**
 * Calcule les frais d'un échange (USD uniquement).
 *
 * Règles : le palier s'applique au montant total (pas par tranche) ; tous les frais
 * (pourcentage + frais fixe) sont déduits du montant envoyé. Le calcul se fait en centimes
 * entiers : aucun flottant n'intervient dans les montants.
 */
class FeeCalculator
{
    /**
     * @return array{amount:string,percent:string,percent_fee:string,fixed_fee:string,total_fee:string,net:string}
     */
    public function quote(Corridor $corridor, string|int|float $amount): array
    {
        $cents = $this->toCents($amount);
        $min = $this->toCents($corridor->min_amount);

        if ($cents < $min) {
            throw new InvalidArgumentException('Le montant minimum pour cet échange est de '.$this->format($min).' $.');
        }

        $tier = $corridor->tiers->filter(fn ($t) => $this->toCents($t->min_amount) <= $cents)->sortByDesc('min_amount')->first();
        if (! $tier) {
            throw new InvalidArgumentException('Aucun barème de frais n\'est défini pour ce montant.');
        }

        $basisPoints = (int) round(((float) $tier->percent) * 100);
        $percentFee = intdiv($cents * $basisPoints + 5000, 10000); // arrondi au centime supérieur à partir de 0,5
        $fixedFee = $this->toCents($corridor->fixed_fee);
        $totalFee = $percentFee + $fixedFee;
        $net = $cents - $totalFee;

        if ($net <= 0) {
            throw new InvalidArgumentException('Les frais dépassent le montant envoyé.');
        }

        return [
            'amount' => $this->format($cents),
            'percent' => number_format($basisPoints / 100, 2, '.', ''),
            'percent_fee' => $this->format($percentFee),
            'fixed_fee' => $this->format($fixedFee),
            'total_fee' => $this->format($totalFee),
            'net' => $this->format($net),
        ];
    }

    public function toCents(string|int|float $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    public function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
