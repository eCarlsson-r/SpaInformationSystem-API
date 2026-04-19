<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Category;
use App\Models\Treatment;
use App\Models\Room;
use App\Models\Bed;
use App\Models\Supplier;
use App\Models\Agent;
use App\Models\Shift;
use App\Models\Attendance;
use App\Models\Session;
use App\Models\Sales;
use App\Models\SalesRecord;
use App\Models\Voucher;
use App\Models\Income;
use App\Models\IncomeItem;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Journal;
use App\Models\JournalRecord;
use App\Models\Feedback;
use App\Models\ChatSession;
use App\Models\Bonus;
use App\Models\Discount;
use App\Models\Wallet;
use App\Models\Bank;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. ADMIN & MANAGER USERS
        User::factory()->create([
            'username' => 'demo_admin',
            'password' => Hash::make('Am12345'),
            'type' => 'ADMIN'
        ]);

        // 2. ACCOUNTS (Financial Structure)
        $accounts = $this->seedAccounts();

        // 3. BRANCHES
        $grandSpa = Branch::factory()->create([
            'name' => 'Grand Luxury Spa',
            'address' => '123 Elite Plaza, Jakarta',
            'city' => 'Jakarta',
            'phone' => '021-5550001',
            'cash_account' => $accounts['cash_bca']->id,
            'walkin_account' => $accounts['sales_walkin']->id,
            'voucher_purchase_account' => $accounts['sales_voucher']->id,
            'voucher_usage_account' => $accounts['payable_voucher']->id,
        ]);

        $expressSpa = Branch::factory()->create([
            'name' => 'City Express Spa',
            'address' => '45 Metro Mall, Bandung',
            'city' => 'Bandung',
            'phone' => '022-7770002',
            'cash_account' => $accounts['cash_mandiri']->id,
            'walkin_account' => $accounts['sales_walkin']->id,
            'voucher_purchase_account' => $accounts['sales_voucher']->id,
            'voucher_usage_account' => $accounts['payable_voucher']->id,
        ]);

        // 4. MASTER DATA (Rooms & Beds)
        $this->seedRoomsAndBeds($grandSpa);
        $this->seedRoomsAndBeds($expressSpa);

        // 5. MASTER DATA (Categories & Treatments)
        $categories = $this->seedCategoriesAndTreatments();
        $treatments = Treatment::all();

        // 6. EMPLOYEES
        $staff = $this->seedEmployees($grandSpa, $expressSpa);

        // 7. CUSTOMERS
        $customers = Customer::factory(10)->create();
        $demoCustomer = Customer::factory()->create([
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'mobile' => '08123456789',
            'user_id' => User::factory()->create([
                'username' => 'alice@example.com',
                'password' => Hash::make('password'),
                'type' => 'CUSTOMER'
            ])->id
        ]);

        // 8. BANNERS
        $this->seedBanners();

        // 9. SUPPLIERS & AGENTS
        Supplier::factory(5)->create();
        Agent::factory(3)->create();

        // 10. HRD (Shifts & Attendance)
        $this->seedHRD($staff);

        // 11. PROMOTIONS (Discounts & Bonuses)
        $this->seedPromotions($accounts, $treatments);

        // 12. OPERATIONAL (Sessions/Bookings)
        $this->seedSessions($grandSpa, $customers, $categories, $staff['therapists']);

        // 13. OPERATIONAL (Sales & Vouchers)
        $this->seedSales($grandSpa, $demoCustomer, $categories, $staff['therapists'], $accounts);

        // 14. FINANCIALS (Incomes & Expenses)
        $this->seedFinancials($grandSpa, $accounts);

        // 15. ENGAGEMENT (Feedback & Chat)
        $this->seedEngagement($demoCustomer, $staff['therapists']);

        // 16. WALLETS
        $this->seedWallets($accounts);
    }

    private function seedAccounts()
    {
        $data = [
            'cash_bca' => ['name' => 'EDC BCA', 'type' => 'cash', 'category' => 'Kartu Kredit'],
            'cash_mandiri' => ['name' => 'EDC Mandiri', 'type' => 'cash', 'category' => 'Kartu Kredit'],
            'cash_tunai' => ['name' => 'Kas Tunai', 'type' => 'cash', 'category' => 'Uang Tunai'],
            'sales_walkin' => ['name' => 'Penjualan Walk In', 'type' => 'income', 'category' => 'Penjualan'],
            'sales_voucher' => ['name' => 'Penjualan Voucher', 'type' => 'income', 'category' => 'Penjualan'],
            'payable_voucher' => ['name' => 'Hutang Voucher Customer', 'type' => 'account-payable', 'category' => 'Hutang Dagang'],
            'receivable_trade' => ['name' => 'Piutang Usaha', 'type' => 'account-receivable', 'category' => 'Piutang'],
            'expense_salary' => ['name' => 'Biaya Gaji Karyawan', 'type' => 'cost-of-sales', 'category' => 'Biaya Produksi'],
            'expense_rent' => ['name' => 'Biaya Sewa', 'type' => 'adm-expenses', 'category' => 'Biaya Personalia'],
            'expense_utility' => ['name' => 'Biaya Listrik & Air', 'type' => 'adm-expenses', 'category' => 'Biaya Personalia'],
        ];

        $results = [];
        foreach ($data as $key => $attr) {
            $results[$key] = Account::factory()->create($attr);
        }
        return $results;
    }

    private function seedRoomsAndBeds($branch)
    {
        $rooms = Room::factory(3)->create(['branch_id' => $branch->id]);
        foreach ($rooms as $room) {
            Bed::factory(2)->create(['room_id' => $room->id]);
        }
    }

    private function seedCategoriesAndTreatments()
    {
        $cats = [
            'Body' => ['Wellness Massage', 'Scrub Massage', 'Herbal Massage'],
            'Face' => ['Modern Facial', 'Aloe Vera Facial', 'Totok Wajah'],
            'Foot' => ['Wellness Reflexology', 'Scrub Reflexology'],
        ];

        $results = [];
        foreach ($cats as $catName => $treats) {
            $category = Category::factory()->create(['name' => $catName]);
            $results[$catName] = $category;
            foreach ($treats as $tName) {
                Treatment::factory()->create([
                    'name' => $tName,
                    'category_id' => $category->id,
                    'price' => rand(10, 50) * 10000,
                    'duration' => rand(6, 12) * 10
                ]);
            }
        }
        return $results;
    }

    private function seedEmployees($branch1, $branch2)
    {
        $types = ['STAFF', 'THERAPIST', 'MANAGER'];
        $results = ['staff' => [], 'therapists' => []];

        foreach ([$branch1, $branch2] as $branch) {
            foreach ($types as $type) {
                $count = ($type === 'THERAPIST') ? 4 : 1;
                for ($i = 0; $i < $count; $i++) {
                    $user = User::factory()->create([
                        'username' => strtolower($type) . "_" . $branch->id . "_" . $i,
                        'type' => $type
                    ]);
                    $emp = Employee::factory()->create([
                        'user_id' => $user->id,
                        'branch_id' => $branch->id,
                        'gender' => ($i % 2 === 0) ? 'M' : 'F'
                    ]);
                    if ($type === 'THERAPIST') $results['therapists'][] = $emp;
                    else $results['staff'][] = $emp;
                }
            }
        }
        return $results;
    }

    private function seedBanners()
    {
        Banner::factory()->create([
            'image' => '/storage/images/slider1.webp',
            'introduction' => 'Welcome',
            'title' => 'CARLSSON Spa & Salon',
            'subtitle' => 'Refresh your body and soul here',
            'description' => 'Enjoy premier class relaxation experience with our treatments.',
            'action' => 'Refresh with our treatments',
            'action_page' => 'treatments'
        ]);

        Banner::factory()->create([
            'image' => '/storage/images/slider4.webp',
            'introduction' => "Let's invest with",
            'title' => 'Voucher Set',
            'subtitle' => 'To enjoy our treatment later without having to think about cost',
            'description' => 'Collect the voucher and enjoy the treatment as you like',
            'action' => "Let's invest in vouchers",
            'action_page' => 'catalog'
        ]);
    }

    private function seedHRD($employees)
    {
        $shifts = [
            ['id'=> 'M', 'name' => 'Morning', 'start_time' => '09:00:00', 'end_time' => '17:00:00'],
            ['id'=> 'A', 'name' => 'Afternoon', 'start_time' => '13:00:00', 'end_time' => '21:00:00'],
        ];
        foreach ($shifts as $s) Shift::factory()->create($s);

        $allEmps = array_merge($employees['staff'], $employees['therapists']);
        foreach ($allEmps as $emp) {
            for ($i = 0; $i < 5; $i++) {
                Attendance::factory()->create([
                    'employee_id' => $emp->id,
                    'date' => Carbon::now()->subDays($i)->toDateString(),
                    'clock_in' => '09:00:00',
                    'clock_out' => '17:00:00',
                    'shift_id' => 'M'
                ]);
            }
        }
    }

    private function seedPromotions($accounts, $treatments)
    {
        Discount::create([
            'code' => 'WLCOMDISCN',
            'name' => 'Welcome Discount',
            'type' => 'percentage',
            'percent' => 10,
            'amount' => 0,
            'quantity' => 100,
            'expiry_date' => Carbon::now()->addMonths(6),
            'account_id' => $accounts['sales_walkin']->id
        ]);

        foreach ($treatments as $t) {
            Bonus::create([
                'treatment_id' => $t->id,
                'grade' => 'A',
                'gross_bonus' => $t->price * 0.1,
                'trainer_deduction' => 0,
                'savings_deduction' => 0
            ]);
        }
    }

    private function seedSessions($branch, $customers, $categories, $therapists)
    {
        $treatments = Treatment::all();
        $beds = Bed::whereHas('room', fn($q) => $q->where('branch_id', $branch->id))->get();

        for ($i = 0; $i < 5; $i++) {
            Session::factory()->create([
                'customer_id' => $customers->random()->id,
                'employee_id' => $therapists[array_rand($therapists)]->id,
                'treatment_id' => $treatments->random()->id,
                'bed_id' => $beds->random()->id,
                'status' => 'completed',
                'date' => Carbon::now()->subDays($i)->toDateString(),
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            Session::factory()->create([
                'customer_id' => $customers->random()->id,
                'employee_id' => $therapists[array_rand($therapists)]->id,
                'treatment_id' => $treatments->random()->id,
                'bed_id' => $beds->random()->id,
                'status' => 'confirmed',
                'date' => Carbon::now()->addDays($i)->toDateString(),
                'start' => '10:00:00',
            ]);
        }
    }

    private function seedSales($branch, $customer, $categories, $therapists, $accounts)
    {
        $treatment = Treatment::first();
        
        $sale = Sales::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'employee_id' => $therapists[0]->id,
            'date' => Carbon::now()->toDateString(),
            'time' => Carbon::now()->toTimeString(),
            'subtotal' => $treatment->price,
            'total' => $treatment->price,
            'discount' => 0,
            'rounding' => 0,
        ]);

        SalesRecord::create([
            'sales_id' => $sale->id,
            'treatment_id' => $treatment->id,
            'quantity' => 1,
            'price' => $treatment->price,
            'total_price' => $treatment->price,
            'discount' => 0,
            'redeem_type' => 'walk-in'
        ]);
    }

    private function seedFinancials($branch, $accounts)
    {
        $income = Income::create([
            'journal_reference' => 'INC-' . time(),
            'date' => Carbon::now()->toDateString(),
            'partner_type' => 'Walk-in',
            'partner' => 1,
            'description' => 'Daily Sales Summary'
        ]);

        IncomeItem::create([
            'income_id' => $income->id,
            'type' => 'Service',
            'transaction' => 'CASH-001',
            'amount' => 500000,
            'description' => 'Massage Services'
        ]);

        $expense = Expense::create([
            'journal_reference' => 'EXP-' . time(),
            'date' => Carbon::now()->toDateString(),
            'partner_type' => 'Supplier',
            'partner' => 'General Store',
            'description' => 'Office Supplies'
        ]);

        ExpenseItem::create([
            'expense_id' => $expense->id,
            'account_id' => $accounts['expense_utility']->id,
            'amount' => 100000,
            'description' => 'Cleaning materials'
        ]);

        $journal = Journal::create([
            'reference' => 'JRN-' . time(),
            'date' => Carbon::now()->toDateString(),
            'description' => 'Opening Balance'
        ]);

        JournalRecord::create([
            'journal_id' => $journal->id,
            'account_id' => $accounts['cash_tunai']->id,
            'debit' => 1000000,
            'credit' => 0,
            'description' => 'Opening Balance'
        ]);
    }

    private function seedEngagement($customer, $therapists)
    {
        $session = Session::where('status', 'completed')->first();
        if ($session) {
            Feedback::factory()->create([
                'customer_id' => $customer->id,
                'session_id' => $session->id,
                'rating' => 5,
                'comment' => 'Excellent service!'
            ]);
        }
    }

    private function seedWallets($accounts)
    {
        $bank = Bank::factory()->create(['name' => 'BCA']);
        Wallet::create([
            'name' => 'Main Business Wallet',
            'bank_account_number' => '1234567890',
            'bank_id' => $bank->id,
            'account_id' => $accounts['cash_bca']->id,
            'edc_machine' => true
        ]);
    }
}