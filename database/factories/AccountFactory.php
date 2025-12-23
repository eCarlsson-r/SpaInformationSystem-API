<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'category' => $this->faker->randomElement([
                "Uang Tunai",
                "Bank",
                "Kartu Kredit",
                "Piutang",
                "Aktiva Tetap",
                "Penyusutan",
                "Hutang Dagang",
                "Equity",
                "Laba Rugi Berjalan",
                "Laba Rugi Di Tahan",
                "Penjualan",
                "Pendapatan Jasa Giro",
                "Biaya Produksi",
                "Biaya Personalia"
            ]),
            'type' => $this->faker->randomElement(
                [
                    "cash",
                    "account-receivable",
                    "inventory",
                    "raw-material",
                    "work-in-process",
                    "other-current-assets",
                    "fixed-assets",
                    "depreciation",
                    "amortization",
                    "other-assets",
                    "account-payable",
                    "other-current-liabilities",
                    "long-term-liabilities",
                    "equity-does-not-closed",
                    "equity-gets-closed",
                    "equity-re-end-year",
                    "equity-retain-earnings",
                    "income",
                    "cost-of-sales",
                    "cost-of-goods-manufactured",
                    "sales-expenses",
                    "adm-expenses",
                    "godown-expenses",
                    "direct-labor",
                    "overhead-cost",
                    "other-income",
                    "other-expenses",
                    "tax",
                    "marketable-securities",
                    "prepaid-expense",
                    "other-stock",
                    "finishing-inventory",
                    "purchasing"
                ]
            ),
        ];
    }
}
