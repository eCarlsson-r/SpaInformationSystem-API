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
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'username' => 'demo_admin',
            'password' => Hash::make('Am12345'),
            'type' => 'ADMIN'
        ]);

        $demoBranch = Branch::factory()->create([
            'name' => 'Demo Branch',
            'address' => 'Somewhere',
            'phone' => '06123456543',
            'cash_account' => Account::factory()->create([
                'name' => 'EDC BCA',
                'type' => 'cash',
                'category' => 'Kartu Kredit'
            ])->id,
            'walkin_account' => Account::factory()->create([
                'name' => 'Penjualan Walk In',
                'type' => 'income',
                'category' => 'Penjualan'
            ])->id,
            'voucher_purchase_account' => Account::factory()->create([
                'name' => 'Penjualan Voucher',
                'type' => 'income',
                'category' => 'Penjualan'
            ])->id,
            'voucher_usage_account'=>Account::factory()->create([
                'name' => 'Hutang Voucher Customer',
                'type' => 'account-payable',
                'category' => 'Hutang Dagang'
            ])->id
        ]);

        Employee::factory()->create([
            'user_id' => User::factory()->create([
                'username' => 'demo_mstaff',
                'password' => Hash::make('Ms12345'),
                'type' => 'STAFF'
            ])->id,
            'complete_name' => 'Demo Male Staff',
            'branch_id' => $demoBranch->id,
            'name' => 'MStaff',
            'gender' => 'M',
            'status' => 'fixed',
            'recruiter' => 0,
            'place_of_birth' => 'Demo',
            'date_of_birth' => '1987-02-20',
            'base_salary' => 0,
            'absent_deduction' => 50000,
            'base_salary' => 0,
            'late_deduction' => 20000,
            'certified' => 0,
            'vaccine1' => 0,
            'vaccine2' => 0
        ]);

        Employee::factory()->create([
            'user_id' => User::factory()->create([
                'username' => 'demo_fstaff',
                'password' => Hash::make('Fs12345'),
                'type' => 'STAFF'
            ])->id,
            'complete_name' => 'Demo Female Staff',
            'branch_id' => $demoBranch->id,
            'name' => 'FStaff',
            'gender' => 'F',
            'status' => 'fixed',
            'recruiter' => 0,
            'place_of_birth' => 'Demo',
            'date_of_birth' => '1989-08-14',
            'base_salary' => 0,
            'absent_deduction' => 50000,
            'base_salary' => 0,
            'late_deduction' => 20000,
            'certified' => 0,
            'vaccine1' => 0,
            'vaccine2' => 0
        ]);

        Employee::factory()->create([
            'user_id' => User::factory()->create([
                'username' => 'demo_ftherapist',
                'password' => Hash::make('Ft12345'),
                'type' => 'THERAPIST'
            ])->id,
            'complete_name' => 'Demo Female Therapist',
            'branch_id' => $demoBranch->id,
            'name' => 'FTherapist',
            'gender' => 'F',
            'status' => 'fixed',
            'recruiter' => 0,
            'place_of_birth' => 'Demo',
            'date_of_birth' => '1988-06-21',
            'base_salary' => 0,
            'absent_deduction' => 50000,
            'base_salary' => 0,
            'late_deduction' => 20000,
            'certified' => 0,
            'vaccine1' => 0,
            'vaccine2' => 0
        ]);

        Employee::factory()->create([
            'user_id' => User::factory()->create([
            'username' => 'demo_mtherapist',
            'password' => Hash::make('Mt12345'),
            'type' => 'THERAPIST'
        ])->id,
            'complete_name' => 'Demo Male Therapist',
            'branch_id' => $demoBranch->id,
            'name' => 'MTherapist',
            'gender' => 'M',
            'status' => 'fixed',
            'recruiter' => 0,
            'place_of_birth' => 'Demo',
            'date_of_birth' => '1986-07-25',
            'base_salary' => 0,
            'absent_deduction' => 50000,
            'base_salary' => 0,
            'late_deduction' => 20000,
            'certified' => 0,
            'vaccine1' => 0,
            'vaccine2' => 0
        ]);

        Customer::factory()->create([
            'name' => 'Demo Customer',
            'gender' => 'M',
            'city' => 'Medan',
            'country' => 'Indonesia',
            'place_of_birth' => 'Anywhere',
            'date_of_birth' => '1976-08-01',
            'mobile' => '08357583908',
            'email' => 'demo.customer@ymail.com',
        ]);

        User::factory()->create([
            'username' => 'demo.customer@ymail.com',
            'password' => Hash::make('Demo12345'),
            'type' => 'CUSTOMER'
        ]);

        Account::factory()->create([
            'name' => 'Piutang Usaha',
            'type' => 'account-receivable',
            'category' => 'Piutang'
        ]);

        Account::factory()->create([
            'name' => 'Piutang Karyawan',
            'type' => 'account-receivable',
            'category' => 'Piutang'
        ]);

        Account::factory()->create([
            'name' => 'Bangunan',
            'type' => 'fixed-assets',
            'category' => 'Aktiva Tetap'
        ]);

        Account::factory()->create([
            'name' => 'Inventaris Kantor',
            'type' => 'fixed-assets',
            'category' => 'Aktiva Tetap'
        ]);

        Account::factory()->create([
            'name' => 'Inventaris Kerja',
            'type' => 'fixed-assets',
            'category' => 'Aktiva Tetap'
        ]);

        Account::factory()->create([
            'name' => 'Penyusutan Inventaris Kantor',
            'type' => 'depreciation',
            'category' => 'Penyusutan'
        ]);

        Account::factory()->create([
            'name' => 'Penyusutan Kerja',
            'type' => 'depreciation',
            'category' => 'Penyusutan'
        ]);

        Account::factory()->create([
            'name' => 'Laba Rugi Berjalan',
            'type' => 'equity-retain-earnings',
            'category' => 'Laba Rugi Berjalan'
        ]);

        Account::factory()->create([
            'name' => 'Laba Rugi Di Tahan',
            'type' => 'equity-re-end-year',
            'category' => 'Laba Rugi Di Tahan'
        ]);

        Account::factory()->create([
            'name' => 'Potongan Penjualan Walk In',
            'type' => 'income',
            'category' => 'Penjualan'
        ]);

        Account::factory()->create([
            'name' => 'Potongan Penjualan Voucher',
            'type' => 'income',
            'category' => 'Penjualan'
        ]);

        Account::factory()->create([
            'name' => 'Pendapatan Jasa Giro',
            'type' => 'other-income',
            'category' => 'Pendapatan Jasa Giro'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Keperluan Massage',
            'type' => 'cost-of-sales',
            'category' => 'Biaya Produksi'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Keperluan Dapur',
            'type' => 'cost-of-sales',
            'category' => 'Biaya Produksi'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Iklan & Promosi',
            'type' => 'cost-of-sales',
            'category' => 'Biaya Produksi'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Gaji Karyawan',
            'type' => 'cost-of-sales',
            'category' => 'Biaya Produksi'
        ]);

        Account::factory()->create([
            'name' => 'Biaya THR',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Keperluan Kantor',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Rekening Listrik',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Rekening Air',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Rekening Telepon',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Sewa dibayar dimuka',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Asuransi dibayar dimuka',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Renovasi dibayar dimuka',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya ADM BANK',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Komisi Voucher',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Pajak',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Account::factory()->create([
            'name' => 'Biaya Serba-Serbi',
            'type' => 'adm-expenses',
            'category' => 'Biaya Personalia'
        ]);

        Banner::factory()->create([
            'image' => '/storage/images/slider1.webp',
            'title' => 'Welcome',
            'subtitle' => 'CARLSSON Spa & Salon',
            'description' => 'Refresh your body and soul here',
            'action' => 'Refresh with our treatments',
            'action_page' => 'treatments'
        ]);

        Banner::factory()->create([
            'image' => 'images/resource/slider2',
            'title' => 'Enjoy our exclusive treatments with',
            'subtitle' => 'TREATMENT PACKAGES',
            'description' => 'Combination of treatments that provides maximum freshness.',
            'action' => 'Enjoy combination of treatments for maximum relaxation.',
            'action_page' => 'package-max-fresh'
        ]);

        Banner::factory()->create([
            'image' => 'images/resource/slider4',
            'title' => "Let's invest with",
            'subtitle' => 'Voucher Set',
            'description' => 'To enjoy our treatment later without having to think about cost',
            'action' => 'Collect the voucher and enjoy the treatment as you like',
            'action_page' => 'lets-invest-voucher'
        ]);

        $bodyCategory = Category::factory()->create([
            'name' => 'Body',
            'description' => 'Body treatments'
        ])->id;

        $faceCategory = Category::factory()->create([
            'name' => 'Face',
            'description' => 'Face treatments'
        ])->id;

        $hairCategory = Category::factory()->create([
            'name' => 'Hair',
            'description' => 'Hair treatments'
        ])->id;

        $nailCategory = Category::factory()->create([
            'name' => 'Nail',
            'description' => 'Nail treatments'
        ])->id;

        $waxingCategory = Category::factory()->create([
            'name' => 'Waxing',
            'description' => 'Waxing treatments'
        ])->id;

        Treatment::factory()->create([
            'name' => 'Body Scrub',
            'category_id' => $bodyCategory,
            'price' => 150000,
            'duration' => 60,
            'description' => 'Relaxing body scrub treatment'
        ]);

        Treatment::factory()->create([
            'name' => 'Body Massage',
            'category_id' => $bodyCategory,
            'price' => 200000,
            'duration' => 60,
            'description' => 'Relaxing body massage treatment'
        ]);

        Treatment::factory()->create([
            'name' => 'Facial',
            'category_id' => $faceCategory,
            'price' => 250000,
            'duration' => 60,
            'description' => 'Relaxing facial treatment'
        ]);

        Treatment::factory()->create([
            'name' => 'Hair Spa',
            'category_id' => $hairCategory,
            'price' => 300000,
            'duration' => 60,
            'description' => 'Relaxing hair spa treatment'
        ]);

        Treatment::factory()->create([
            'name' => 'Manicure',
            'category_id' => $nailCategory,
            'price' => 100000,
            'duration' => 60,
            'description' => 'Relaxing manicure treatment'
        ]);

        Treatment::factory()->create([
            'name' => 'Pedicure',
            'category_id' => $nailCategory,
            'price' => 150000,
            'duration' => 60,
            'description' => 'Relaxing pedicure treatment'
        ]);

        Treatment::factory()->create([
            'name' => 'Waxing',
            'category_id' => $waxingCategory,
            'price' => 200000,
            'duration' => 60,
            'description' => 'Relaxing waxing treatment'
        ]);

        Treatment::factory()->create([
            'name' => 'Threading',
            'category_id' => $waxingCategory,
            'price' => 100000,
            'duration' => 60,
            'description' => 'Relaxing threading treatment'
        ]);
    }
}