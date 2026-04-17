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
            'introduction' => 'Welcome',
            'title' => 'CARLSSON Spa & Salon',
            'subtitle' => 'Refresh your body and soul here',
            'description' => 'Enjoy premier class relaxation experience with our treatments.',
            'action' => 'Refresh with our treatments',
            'action_page' => 'treatments'
        ]);

        Banner::factory()->create([
            'image' => '/storage/images/slider2.webp',
            'introduction' => 'Enjoy our exclusive treatments with',
            'title' => 'TREATMENT PACKAGES',
            'subtitle' => 'Combination of treatments that provides maximum freshness.',
            'description' => 'Enjoy combination of treatments for maximum relaxation.',
            'action' => 'Find out about our refreshing packages',
            'action_page' => 'packages'
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

        $bodyCategory = Category::factory()->create([
            'name' => 'Body',
            'description' => 'Berbagai jenis perawatan yang berfungsi untuk menyegarkan badan.'
        ])->id;

        $faceCategory = Category::factory()->create([
            'name' => 'Face',
            'description' => 'Perawatan untuk menjaga agar kesegaran wajah tetap terjaga.'
        ])->id;

        $footCategory = Category::factory()->create([
            'name' => 'Foot',
            'description' => 'Treatment untuk kaki yang bisa memperbaiki fungsi organ tubuh.'
        ])->id;

        Treatment::factory()->create([
            'name' => 'Wellness Massage',
            'category_id' => $bodyCategory,
            'price' => 250000,
            'duration' => 90,
            'description' => 'Nikmati relaksasi sekaligus terapi kecantikan lewat minyak dan cream dengan Wellness Massage yang akan mengembalikan kebugaran tubuh. Banyak yang berminat dengan treatment ini karena banyak manfaat yang bisa diperoleh dari Wellness Massage kami.<br/><br/>Berikut adalah manfaat yang bisa Anda dapatkan :<br/>\r\n - Mengurangi ketegangan hasil dari kegiatan yang padat, sekaligus mengusir stress, gejala depresi, dan kecemasan.<br/>\r\n - Meredakan nyeri pada tubuh dengan membuat otot-otot yang tadinya sakit dan tegang mengendur dan kembali santai. Sehingga apabila ada bagian tubuh yang terlalu sering dipakai akan beristirahat dan santai sejenak.<br/>\r\n - Melancarkan sirkulasi darah pada tubuh sehingga meratakan warna kulit tubuh, juga menstimulasi pertumbuhan tissue pada tubuh yang membantu mengurangi tampilan bekas luka atau stretch marks pada bagian tubuh tertentu.<br/>\r\n - Memperkuat sistem imun oleh adanya stimuli untuk sistem limfatik tubuh yang bertanggung jawab atas sistem imun tubuh, sehingga bisa meningkatkan kekebalan agar Anda tidak gampang sakit.<br/><br/>\r\nDikarenakan sulit untuk menemukan waktu yang tepat, kami menyediakan durasi 1.5 jam dan 2 jam. Harap konsultasikan pada terapis untuk menemukan teknik massage yang sesuai untuk kebutuhan tubuh Anda.'
        ]);

        Treatment::factory()->create([
            'name' => 'Scrub Massage',
            'category_id' => $bodyCategory,
            'price' => 300000,
            'duration' => 90,
            'description' => 'Kembalikan cerahnya kulitmu dari kusam dengan Scrub Massage kami! Scrub Massage mampu mengangkat sel kulit mati yang membuat kulit menjadi lebih cerah, halus, juga mengurangi serta mencegah jerawat di kulit tubuh. Selain itu, Scrub Massage juga mampu melancarkan peredaran darah karena pijitan lembut yang diberikan ketika memakai scrub. Kami menyediakan aroma Coklat, Green Tea, Bengkoang, dan Stroberi. Saat ini ada beberapa varian baru yakni Lemon, Jeruk, dan Alpukat.<br/><br/>Lantas apa aja manfaat dari setiap aroma?<br/> - Scrub Coklat menjaga kelembaban, kelembutan, dan elastisitas kulit. Masker Coklat menjaga kelembaban dan elastisitas kulit.<br/> - Scrub Green Tea membantu meremajakan sel kulit dan menyegarkan kulit. Masker Green Tea sebagai antioksidan untuk memperbaiki sirkulasi oksigen pada tubuh.<br/> - Scrub Bengkoang membantu membersihkan dan mencerahkan kulit. Masker Bengkoang memutihkan kulit secara alami, serta tampak bersih, sehat, dan segar.<br/> - Scrub Stroberi menbantu mencerahkan kulit. Masker Stroberi menutrisi kulit, memperbaiki sirkulasi oksigen dan peredaran darah tepi.<br/> - Scrub Lemon menbantu meluruhkan sel kulit mati tanpa membuat kulit iritasi. Masker Lemon berfungsi untuk menetralkan kulit berminyak serta menyegarkan pori-pori kulit.<br/> - Scrub Alpukat membersihkan kulit dari kotoran, menjaga kekenyalan kulit, serta mencegah penuaan, melembabkan dan mencerahkan kulit. Masker Alpukat mengurangi kerut pada kulit dengan meningkatkan kandungan kolagen sehingga memulihkan elastisitas kulit serta mencegah kulit menjadi kendur.<br/> - Scrub Jeruk menyegarkan berfungsi menghidrasi, melembabkan, membersihkan kulit mati,  mengecilkan pori-pori, merawat kulit agar cerah, sehat, dan segar.<br/><br/>Ketika Anda menjalani Scrub Massage, jangan lupa juga untuk memakai masker agar kulit kembali ternutrisi dan pori-pori kembali tertutup supaya kebersihan kulit lebih terjaga dan terhindar dari infeksi kulit akibat kotoran luar yang masuk ke dalam lapisan epidermis kulit.'
        ]);

        Treatment::factory()->create([
            'name' => 'Herbal Massage',
            'category_id' => $bodyCategory,
            'price' => 300000,
            'duration' => 90,
            'description' => 'Segarkan badanmu dari masuk angin dengan Herbal Massage! Tahukah Anda kalau Herbal Massage sudah digunakan selama berabad-abad sebagai pengobatan detoksifikasi? Herbal Massage dirancang khusus untuk Anda yang memilih Massage yang lebih kuat. Bagi yang menjalani gaya hidup aktif,  terutama yang merasakan sakit dan pegal, akan benar-benar mendapat manfaat dari kenyamanan aromatik yang dalam dan menengangkan.<br/><br/>Herbal Massage sering digunakan untuk membantu nyeri otot dan jaringan lunak, juga punya banyak manfaat untuk tubuh kita. Apa aja sih manfaatnya?<br/>\n - Badan terlebih dahulu menerima massage agar menjadi relax dan siap menerima rempah alami.<br/>\n - Panas menginduksi relaksasi otot juga meningkatkan aliran oksigen darah, sehingga mengoksidasi organ-organ penting.<br/>\n - Kompres herbal meredakan nyeri otot dan sendi, membantu meningkatkan sirkulasi, dan memberi nutrisi pada kulit.<br/><br/>\nDisarankan untuk tidak mandi sesaat setelah menjalani Herbal Massage, juga tidak dianjurkan bagi untuk wanita yang sedang hamil. Penderita rheumatoid arthritis harus mendapatkan persetujuan dari dokter sebelum menjalani Herbal Massage.'
        ]);

        Treatment::factory()->create([
            'name' => 'Modern Facial',
            'category_id' => $faceCategory,
            'price' => 200000,
            'duration' => 90,
            'description' => 'Nikmati perawatan wajah dari kami yang memiliki banyak manfaat untuk wajah Anda dengan Modern Facial kami! Ada alat baru yang kami gunakan dalam Modern Facial kami, lalu apa saja yang akan terjadi selama Modern Facial?<br/>\r\n - Facial Cleansing kami kini disertakan dengan Facial Cleansing Brush yang akan membersihkan wajah dari permukaan hingga pori-pori wajah.<br/>\r\n - Facial Scrub kami juga kini disertakan dengan Facial Scrubber yang menggunakan getaran frekuensi tinggi untuk membantu melonggarkan kotoran dan minyak yang bersarang di pori-pori Anda, membuat wajah Anda tampak ekstra jernih & halus. <br/>\r\n - Massage & Totok Wajah untuk meredakan ketegangan pada otot wajah agar alat-alat facial berfungsi lebih optimal.<br/>\r\n - Vaccum Komedo mampu menyedot sisa komedo yang belum terangkat oleh Facial Scrubber agar wajah menjadi lebih bersih.<br/>\r\n - Laser Kantong Mata dan Wajah untuk detox wajah agar wajah terbebas dari kantong mata dan wajah kembali kinclong.<br/>\r\n - Masker Wajah untuk menutrisi kulit wajah setelah treatment menggunakan alat, serta Toner Wajah untuk kembali menyegarkan wajah.'
        ]);

        Treatment::factory()->create([
            'name' => 'Totok Wajah',
            'category_id' => $faceCategory,
            'price' => 100000,
            'duration' => 30,
            'description' => 'Ingin wajah terlihat lebih cerah dan kencang supaya awet muda? Cobain Totok & Massage wajah dari kami yang mampu membuat wajah terasa lebih kenyal dan bersinar, serta meredakan keluhan penyakit ringan seperti tegang, migrain, dan sakit kepala.<br/><br/>\nTotok & Massage Wajah juga memiliki banyak manfaat lainnya seperti mencegah keriput, meremajakan kulit, meningkatkan sirkulasi darah pada daerah wajah, membantu penyerapan make up, mengurangi ketegangan otot wajah, membantu meringankan sinusitis dan alergi, juga yang pastinya dapat merubah suasana hati (mood) menjadi lebih baik.<br/><br/>\nTotok dan Massage Wajah sebaiknya dilakukan oleh yang berusia 17 tahun ke atas, dan tidak cocok bagi orang yang memiliki kulit berjerawat karena bisa menimbulkan peradangan pada daerah yang terkena jerawat.'
        ]);

        Treatment::factory()->create([
            'name' => 'Aloe Vera Facial',
            'category_id' => $faceCategory,
            'price' => 150000,
            'duration' => 60,
            'description' => 'Lidah buaya adalah tanaman dari keluarga kaktus, dan sangat populer untuk kosmetik serta khasiat obatnya. Cairan seperti gel transparan, yang ditemukan di bagian dalam daun inilah yang memberi manfaat luar biasa bagi tanaman ini. Selain itu, tanaman lidah buaya juga merupakan sumber yang kaya akan antioksidan dan vitamin A, B, C dan E.<br/><br/>\n\nIzinkan kami untuk mengenalkan Anda semua manfaat luar biasa dari gel lidah buaya untuk wajah dan cara menggunakannya untuk mendapatkan kulit yang mulus tanpa noda satu pun. Berikut adalah manfaat lidah buaya. pada wajah: <br/>\n - Melembabkan dan menyembuhkan kulit kering dan bersisik dengan sifat menghidrasi dan menyerap ke dalam kulit seperti sulap. Bahkan untuk kulit berminyak dan berjerawat, lidah buaya terbukti menjadi pelembab yang sangat baik karena teksturnya yang ringan. <br/>\n - Gel lidah buaya memiliki sifat mendinginkan yang membantu menenangkan kulit yang teriritasi akibat terbakar sinar matahari, ruam, infeksi, kemerahan, dan gatal. Jadi, itu membuat bahan super untuk kulit sensitif. <br/>\n - Gel lidah buaya dapat membantu kulit Anda mempertahankan kelembapannya dan mengembalikan cahayanya. <br/>\n - Berkat sifat antibakteri dan anti-inflamasinya, lidah buaya dapat membantu mencegah penumpukan bakteri yang merupakan penyebab utama jerawat dan jerawat, dan juga mempercepat proses penyembuhan. <br/>\n - Membantu meringankan perubahan warna di sekitar mata dan efek pendinginan membantu dengan bengkak.<br/><br/>\n\nUmumnya dianggap aman bila digunakan secara topikal, gel lidah buaya harus dihindari jika Anda mengalami luka bakar yang parah atau luka yang signifikan. Beberapa bahkan mungkin mengalami rasa terbakar atau gatal setelah mengoleskan gel lidah buaya, jadi itu sangat tergantung pada kulit Anda. Gel lidah buaya juga tidak boleh dioleskan pada kulit yang terinfeksi karena dapat mengganggu proses penyembuhan. Yuk cobain treatment Facial dengan Aloe Vera sekarang!'
        ]);

        Treatment::factory()->create([
            'name' => 'Wellness Reflexology',
            'category_id' => $footCategory,
            'price' => 150000,
            'duration' => 90,
            'description' => 'Treatment untuk menyegarkan badan yang dimulai dari kaki, di mana pusat saraf-saraf badan semuanya terletak. Treatment ini mampu membantu Anda untuk memperlancar peredaran darah, juga memberi energi pada tubuh karena tubuh menjadi terasa ringan dan segar, serta memperkuat daya tahan tubuh. Tahukah Anda pentingnya refleksi kaki bagi kesehatan tubuh?<br/> \n - Membuat lebih cepat tidur.<br/>\n - Meredakan sakit punggung.<br/>\n - Meredakan flu dan hidung mampet.<br/>\n - Mengurangi kecemasan<br/>\nRefleksi Kaki tidak dianjurkan untuk ibu hamil, juga bagi yang memiliki riwayat penyumbatan aliran darah karena pembekuan darah, asam urat, dan sedang dalam masa penyembuhan karena cedera pada bagian kaki.'
        ]);

        Treatment::factory()->create([
            'name' => 'Scrub Reflexology',
            'category_id' => $footCategory,
            'price' => 200000,
            'duration' => 90,
            'description' => 'Setiap harinya kaki Anda mungkin saja mengalami beberapa kondisi yang keras seperti berjalan berlebihan, sepatu ketat, bertelanjang kaki dan faktor lainnya yang dapat menyebabkan kapalan kasar, hingga kaki yang lelah dan sakit. Setiap langkah yang Anda ambil dapat membuat kaki Anda lebih dekat dengan iritasi, ketidaknyamanan, dan rasa sakit.<br/><br/>Apa saja sih manfaat yang bisa Anda dapatkan dari Scrub Reflexology?<br/>\n - Menenangkan ketidaknyamanan dan rasa sakit<br/>\n - Menghilangkan penumpukan untuk kulit yang lebih halus dan lebih sehat<br/>\n - Melindungi kaki dari kerusakan lebih lanjut<br/>\n - Melonggarkan lengkung, jari kaki dan tumit yang kencang<br/>\n - Meningkatkan fleksibilitas dan jangkauan gerak jari kaki, kaki dan pergelangan kaki<br/>\n - Relaksasi seluruh tubuh <br/><br/>\nKini saatnya untuk memanjakan kaki Anda dengan Scrub Reflexology kami, yang dapat menyegarkan dan merilekskan kaki Anda dari ujung kaki hingga tumit. Scrub Reflexology kami menggabungkan teknik yang paling efektif dan produk yang membuat kulit kaki kembali mulus dan berjalan tanpa rasa sakit ataupun gatal.'
        ]);

        Treatment::factory()->create([
            'name' => 'Herbal Reflexology',
            'category_id' => $footCategory,
            'price' => 200000,
            'duration' => 90,
            'description' => 'Penggunaan herbal yang dikompres lalu dipanaskan dengan uap sekarang menjadi tambahan yang sangat populer untuk terapi pijat. Herbal Reflexology memberi Anda perasaan kaki yang halus, santai dan ringan. Pijat kaki ini dikombinasikan dengan herbal Thai hangat yang dikompres untuk mendinamiskan titik-titik tekanan kaki. <br/><br/>\nTeknik Pijat Kaki Herbal ini menawarkan beberapa manfaat kesehatan potensial saat pori-pori terbuka selama Herbal Reflexology, ini memungkinkan herbal untuk :<br/>\n - Memberikan efek pada penyakit seperti nyeri, kaku, sakit atau tertarik pada otot dan ligamen, sakit punggung, migrain dan radang sendi. <br/>\n - Meningkatkan sirkulasi darah dan merangsang organ-organ internal. <br/>\n - Memberikan bantuan dalam rasa sakit dan rileks tubuh dari stres. <br/><br/>\nMari nikmati manfaat dari hangatnya herbal yang dikompres pada kaki, tersedia pilihan durasi antara 1 jam dan 1.5 jam.'
        ]);
    }
}