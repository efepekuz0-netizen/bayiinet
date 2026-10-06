<?php

namespace App\Services;

use InvalidArgumentException;

class TrendyolPriceCalculator
{
    public function calculate(float $cost, float $desi, array $options): ?float
    {
        if ($cost <= 0) {
            return null;
        }

        $margin = $this->rate((float) ($options['profit_margin'] ?? 0.25));
        $commission = $this->rate((float) ($options['commission_rate'] ?? 0.15));
        $minimumProfit = max(0, (float) ($options['min_profit'] ?? 10));
        $additionalCosts = max(0, (float) ($options['platform_fee'] ?? 0));
        $roundTo = (float) ($options['round_to'] ?? 0.99);
        $deliveryType = (string) ($options['delivery_type'] ?? 'standart');

        if ($margin < 0 || $commission < 0 || $commission >= 1 || $roundTo < 0 || $roundTo >= 1) {
            throw new InvalidArgumentException('Trendyol fiyatlandırma ayarları geçersiz.');
        }

        $desi = max($desi, 0.1);
        $cargoFee = 0.0;
        $salePrice = $this->priceWithCosts($cost, $cargoFee, $additionalCosts, $margin, $minimumProfit, $commission, $roundTo);
        $seen = [];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $cargoFee = $this->cargoFee($desi, $salePrice, $deliveryType);
            $state = [round($salePrice, 2), round($cargoFee, 2)];
            if (in_array($state, $seen, true)) {
                break;
            }
            $seen[] = $state;

            $nextPrice = $this->priceWithCosts($cost, $cargoFee, $additionalCosts, $margin, $minimumProfit, $commission, $roundTo);
            if (abs($nextPrice - $salePrice) < 0.01) {
                $salePrice = $nextPrice;
                break;
            }
            $salePrice = $nextPrice;
        }

        $finalCargoFee = $this->cargoFee($desi, $salePrice, $deliveryType);
        if (abs($finalCargoFee - $cargoFee) >= 0.01) {
            $salePrice = $this->priceWithCosts($cost, $finalCargoFee, $additionalCosts, $margin, $minimumProfit, $commission, $roundTo);
        }

        $minimumPrice = max(0, (float) ($options['min_price'] ?? 0));
        $maximumPrice = max(0, (float) ($options['max_price'] ?? 0));
        if (($minimumPrice > 0 && $salePrice < $minimumPrice) || ($maximumPrice > 0 && $salePrice > $maximumPrice)) {
            return null;
        }

        return round($salePrice, 2);
    }

    private function rate(float $rate): float
    {
        return $rate > 1 ? $rate / 100 : $rate;
    }

    private function priceWithCosts(
        float $cost,
        float $cargoFee,
        float $additionalCosts,
        float $margin,
        float $minimumProfit,
        float $commission,
        float $roundTo,
    ): float {
        $landedCost = $cost + $cargoFee + $additionalCosts;
        $profit = max($landedCost * $margin, $minimumProfit);

        return $this->roundPrice(($landedCost + $profit) / (1 - $commission), $roundTo);
    }

    private function roundPrice(float $price, float $roundTo): float
    {
        if ($price <= 0) {
            return 0;
        }

        return round(floor($price) + $roundTo, 2);
    }

    private function cargoFee(float $desi, float $salePrice, string $deliveryType): float
    {
        if ($salePrice < 350 && $desi <= 10) {
            $fees = $deliveryType === 'hizli'
                ? [[199.99, 48.74], [349.99, 79.58]]
                : [[199.99, 81.24], [349.99, 86.66]];

            foreach ($fees as [$maximumPrice, $fee]) {
                if ($salePrice <= $maximumPrice) {
                    return $fee;
                }
            }
        }

        foreach ([2 => 89.71, 5 => 114.94, 10 => 160.43, 20 => 251.15, 30 => 351.32, 9999 => 500.0] as $maximumDesi => $fee) {
            if ($desi <= $maximumDesi) {
                return $fee;
            }
        }

        return 500.0;
    }
}
