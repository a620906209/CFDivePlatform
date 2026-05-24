<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\ScheduleStatus;
use App\Models\AdminProfile;
use App\Models\Booking;
use App\Models\CourseSchedule;
use App\Models\DivingOffer;
use App\Models\MemberProfile;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'bluedive@cfdive.com')->exists()) {
            $this->command->info('Demo 資料已存在，略過。');
            return;
        }

        $this->createAdmin();
        $providers = $this->createProviders();
        $members   = $this->createMembers();
        $offers    = $this->createOffers($providers);

        [$pastSchedules, $futureSchedules] = $this->createSchedules($offers);

        $completedBookings = $this->createCompletedBookings($pastSchedules, $members, $offers);
        $this->createPendingBookings($futureSchedules, $members, $offers);
        $this->createReviews($completedBookings);
        $this->refreshOfferStats($offers);
        $this->createNotifications($completedBookings);

        $this->command->info('✅ Demo 資料建立完成');
        $this->printSummary($providers, $members);
    }

    private function createAdmin(): void
    {
        $admin = User::firstOrCreate(['email' => 'admin@cfdive.com'], [
            'name' => '平台管理員', 'password' => Hash::make('password123'),
            'role' => 'admin', 'is_active' => true,
        ]);
        AdminProfile::firstOrCreate(['user_id' => $admin->id], ['department' => '營運']);
    }

    private function createProviders(): array
    {
        $providerData = [
            [
                'email' => 'bluedive@cfdive.com', 'name' => '林志遠',
                'business_name' => '墾丁藍海潛水',
                'description'   => '屹立墾丁十五年，PADI 五星級授權中心，專業教學團隊由 8 名持照教練組成，累計培訓超過三千名學員。',
                'contact_phone' => '08-8881234', 'contact_email' => 'bluedive@cfdive.com',
                'contact_person' => '林志遠', 'address' => '屏東縣恆春鎮南灣路 88 號',
                'dive_sites'    => '南灣,萬里桐,核三出水口,後壁湖',
                'services'      => '體驗潛水,OW認證課程,AOW認證課程,夜潛,水下攝影',
                'certifications' => 'PADI 五星級潛水中心,PADI Course Director',
                'facilities'    => '空氣填充站,氧氣填充站,裝備租借,沖洗區,更衣室,停車場',
                'business_hours' => '每日 07:30–18:00', 'is_verified' => true,
            ],
            [
                'email' => 'greendive@cfdive.com', 'name' => '陳美玲',
                'business_name' => '綠島深潛體驗',
                'description'   => '深耕綠島潛水生態導覽，主打小班教學（每班最多 4 人），讓每位學員獲得充足的水下指導時間。',
                'contact_phone' => '089-671234', 'contact_email' => 'greendive@cfdive.com',
                'contact_person' => '陳美玲', 'address' => '台東縣綠島鄉南寮村 15 號',
                'dive_sites'    => '大香菇,石朗,柴口,哈巴狗,雞仔礁',
                'services'      => '體驗潛水,OW認證課程,AOW認證課程,水下生態導覽,浮潛',
                'certifications' => 'PADI 潛水中心,SSI 認證教練',
                'facilities'    => '空氣填充站,裝備租借,防寒衣洗滌區,水下攝影記錄',
                'business_hours' => '每日 07:00–17:30', 'is_verified' => true,
            ],
            [
                'email' => 'islet@cfdive.com', 'name' => '張大偉',
                'business_name' => '小琉球海洋探索',
                'description'   => '小琉球在地老字號潛水店，最熟悉當地珊瑚礁地形與海龜族群，提供最道地的水下生態體驗。',
                'contact_phone' => '08-8613456', 'contact_email' => 'islet@cfdive.com',
                'contact_person' => '張大偉', 'address' => '屏東縣琉球鄉中山路 25 號',
                'dive_sites'    => '花瓶石,美人洞,厚石裙礁,杉福生態廊道,烏鬼洞',
                'services'      => '浮潛,體驗潛水,OW認證課程,海龜觀察導覽',
                'certifications' => 'PADI 授權潛水中心',
                'facilities'    => '裝備租借,沖洗區,更衣室,代訂民宿服務',
                'business_hours' => '每日 08:00–17:00', 'is_verified' => true,
            ],
            [
                'email' => 'northdive@cfdive.com', 'name' => '王建國',
                'business_name' => '北海岸潛水俱樂部',
                'description'   => '以龍洞、和平島為主場地，北部最大的技術潛水訓練基地，提供 Tec 課程與洞穴潛水入門訓練。',
                'contact_phone' => '02-24912345', 'contact_email' => 'northdive@cfdive.com',
                'contact_person' => '王建國', 'address' => '新北市貢寮區龍洞街 56 號',
                'dive_sites'    => '龍洞灣,龍洞南口,鼻頭角,和平島,望海巷',
                'services'      => '體驗潛水,OW認證課程,進階課程,Tec潛水,洞穴入門',
                'certifications' => 'PADI 課程總監,PADI TecRec 教練',
                'facilities'    => '空氣填充站,混氣填充站,裝備租借,技術潛水裝備維修',
                'business_hours' => '週一公休，週二至週日 08:00–18:00', 'is_verified' => false,
            ],
        ];

        $providers = [];
        foreach ($providerData as $data) {
            $user = User::firstOrCreate(['email' => $data['email']], [
                'name' => $data['name'], 'password' => Hash::make('password123'),
                'role' => 'provider', 'is_active' => true,
            ]);
            ProviderProfile::firstOrCreate(['user_id' => $user->id], [
                'business_name'  => $data['business_name'],
                'description'    => $data['description'],
                'contact_phone'  => $data['contact_phone'],
                'contact_email'  => $data['contact_email'],
                'contact_person' => $data['contact_person'],
                'address'        => $data['address'],
                'dive_sites'     => $data['dive_sites'],
                'services'       => $data['services'],
                'certifications' => $data['certifications'],
                'facilities'     => $data['facilities'],
                'business_hours' => $data['business_hours'],
                'is_verified'    => $data['is_verified'],
                'rating'         => 0,
            ]);
            $providers[] = $user;
        }
        return $providers;
    }

    private function createMembers(): array
    {
        $memberData = [
            ['email' => 'alice@demo.com',  'name' => '李小雨', 'gender' => 'female', 'birthday' => '1995-03-12', 'address' => '台北市大安區仁愛路四段 100 號', 'emergency_contact' => '李大明', 'emergency_phone' => '0912001001'],
            ['email' => 'bob@demo.com',    'name' => '王柏翰', 'gender' => 'male',   'birthday' => '1990-07-24', 'address' => '新北市板橋區文化路二段 55 號',  'emergency_contact' => '王美惠', 'emergency_phone' => '0912001002'],
            ['email' => 'carol@demo.com',  'name' => '張雅婷', 'gender' => 'female', 'birthday' => '1998-11-05', 'address' => '台中市西屯區台灣大道三段 210 號', 'emergency_contact' => '張志明', 'emergency_phone' => '0912001003'],
            ['email' => 'david@demo.com',  'name' => '陳俊宏', 'gender' => 'male',   'birthday' => '1988-02-18', 'address' => '高雄市苓雅區中正一路 88 號',   'emergency_contact' => '陳淑芬', 'emergency_phone' => '0912001004'],
            ['email' => 'eva@demo.com',    'name' => '林芷萱', 'gender' => 'female', 'birthday' => '2000-06-30', 'address' => '台南市東區東門路一段 33 號',   'emergency_contact' => '林國雄', 'emergency_phone' => '0912001005'],
            ['email' => 'frank@demo.com',  'name' => '黃建豪', 'gender' => 'male',   'birthday' => '1993-09-15', 'address' => '桃園市中壢區中央路 120 號',    'emergency_contact' => '黃淑娟', 'emergency_phone' => '0912001006'],
            ['email' => 'grace@demo.com',  'name' => '吳怡君', 'gender' => 'female', 'birthday' => '1996-04-22', 'address' => '新竹市東區光復路二段 101 號',  'emergency_contact' => '吳明德', 'emergency_phone' => '0912001007'],
            ['email' => 'henry@demo.com',  'name' => '劉家豪', 'gender' => 'male',   'birthday' => '1985-12-08', 'address' => '基隆市中正區中正路 200 號',    'emergency_contact' => '劉淑英', 'emergency_phone' => '0912001008'],
        ];

        $members = [];
        foreach ($memberData as $data) {
            $user = User::firstOrCreate(['email' => $data['email']], [
                'name' => $data['name'], 'password' => Hash::make('password123'),
                'role' => 'member', 'is_active' => true,
            ]);
            MemberProfile::firstOrCreate(['user_id' => $user->id], [
                'birthday'          => $data['birthday'],
                'gender'            => $data['gender'],
                'address'           => $data['address'],
                'emergency_contact' => $data['emergency_contact'],
                'emergency_phone'   => $data['emergency_phone'],
            ]);
            $members[] = $user;
        }
        return $members;
    }

    private function createOffers(array $providers): array
    {
        $offersData = [
            // 墾丁藍海潛水
            ['title' => '墾丁南灣體驗潛水',         'location' => '墾丁',   'spot' => '南灣',      'price' => 3800,  'region' => '南部', 'tag' => '初學者',   'badges' => ['含裝備', '教練全程陪同', '保險'],                     'description' => '適合零基礎的海水體驗課程，由持照教練一對一陪同入水，南灣能見度佳，適合拍攝水下照片。課程含教學、裝備與保險，歡迎親友組團報名。'],
            ['title' => '墾丁 PADI OW 開放水域認證', 'location' => '墾丁',   'spot' => '核三出水口', 'price' => 14800, 'region' => '南部', 'tag' => '認證課程', 'badges' => ['PADI認證', '含裝備', '教材費含', '保險'],             'description' => '4 天完整 OW 課程，含學科、泳池練習與 4 支開放水域訓練潛水。核三出水口水溫穩定，能見度超過 15 米，是取得人生第一張 PADI 證書的最佳地點。'],
            ['title' => '墾丁萬里桐夜潛探秘',        'location' => '墾丁',   'spot' => '萬里桐',    'price' => 5500,  'region' => '南部', 'tag' => '進階',     'badges' => ['AOW認證', '含裝備', '保險', '夜間燈光設備'],          'description' => '僅開放給持有 OW 以上證書的潛水員。夜晚的萬里桐章魚出沒、螢光珊瑚、夜行魚類——這是白天潛水感受不到的神秘體驗。'],
            // 綠島深潛體驗
            ['title' => '綠島大香菇體驗潛水',        'location' => '綠島',   'spot' => '大香菇',    'price' => 4500,  'region' => '東部', 'tag' => '初學者',   'badges' => ['含裝備', '教練全程陪同', '保險', '水下攝影服務'],     'description' => '大香菇是綠島最著名的潛點，獨特的菇形珊瑚礁聳立在 20 米深處，四周魚群聚集。小班教學讓每位學員都獲得充分指導，視覺震撼無與倫比。'],
            ['title' => '綠島 PADI AOW 進階認證',    'location' => '綠島',   'spot' => '石朗',      'price' => 16800, 'region' => '東部', 'tag' => '認證課程', 'badges' => ['PADI認證', '含裝備', '教材費含', '保險'],             'description' => '5 支多元進階潛水（深潛、導航、水下攝影、夜潛、自然生態），全程在石朗保護區完成。石朗是台灣保育最完善的珊瑚礁區之一，生態豐富多樣。'],
            ['title' => '綠島柴口海龜共游體驗',      'location' => '綠島',   'spot' => '柴口',      'price' => 6800,  'region' => '東部', 'tag' => '生態',     'badges' => ['含裝備', '生態解說', '保險', '小班4人'],              'description' => '柴口是台灣最容易近距離觀察綠蠵龜的潛點，我們與海龜共存超過 20 年，熟知牠們的作息。每梯次限 4 人，確保對環境最低干擾。'],
            // 小琉球海洋探索
            ['title' => '小琉球花瓶石浮潛',          'location' => '小琉球', 'spot' => '花瓶石',    'price' => 1800,  'region' => '南部', 'tag' => '浮潛',     'badges' => ['含裝備', '教練帶領', '適合全家', '保險'],             'description' => '不需要任何潛水經驗，只要會游泳或穿著救生衣就能參加！花瓶石淺灘珊瑚礁完整，是小朋友和第一次接觸海洋的旅客最佳選擇。'],
            ['title' => '小琉球美人洞海龜體驗潛水',  'location' => '小琉球', 'spot' => '美人洞',    'price' => 5200,  'region' => '南部', 'tag' => '初學者',   'badges' => ['含裝備', '教練全程陪同', '保險', '海龜保證'],         'description' => '小琉球海龜密度全台第一，美人洞水下可同時觀察到 3–8 隻海龜悠游。體驗課程不需事先考照，教練確保每位學員安全觀察到海龜。'],
            ['title' => '小琉球 PADI OW 認證課程',   'location' => '小琉球', 'spot' => '厚石裙礁',  'price' => 12800, 'region' => '南部', 'tag' => '認證課程', 'badges' => ['PADI認證', '含裝備', '教材費含', '保險', '海龜同行'], 'description' => '結合 PADI OW 課程與小琉球生態，4 天課程讓你在取得認證的同時深度認識台灣海龜生態。厚石裙礁水況穩定，是絕佳的訓練環境。'],
            // 北海岸潛水俱樂部
            ['title' => '龍洞灣體驗潛水',            'location' => '龍洞',   'spot' => '龍洞灣',    'price' => 3200,  'region' => '北部', 'tag' => '初學者',   'badges' => ['含裝備', '教練全程陪同', '保險'],                     'description' => '龍洞是北台灣能見度最穩定的潛點，清澈海水與豐富的溫帶魚種。北部居民不用遠赴南部，週末就能享受高品質的潛水體驗。'],
            ['title' => '東北角鼻頭角生態導覽潛水',  'location' => '東北角', 'spot' => '鼻頭角',    'price' => 4800,  'region' => '北部', 'tag' => '生態',     'badges' => ['含裝備', '生態解說', '保險', 'OW以上'],               'description' => '東北角海域受黑潮支流影響，生物多樣性極高，鼻頭角是章魚、河豚、獅子魚的熱區。需持 OW 以上證書，適合進階潛水員。'],
            ['title' => '龍洞技術潛水入門',          'location' => '龍洞',   'spot' => '龍洞南口',  'price' => 9800,  'region' => '北部', 'tag' => '進階',     'badges' => ['AOW認證', '含裝備', '保險', 'Tec入門', '小班2人'],   'description' => '學習雙瓶氣瓶配置、Backplate 裝具與減壓程序基礎。龍洞南口地形複雜，是訓練水下導航與定位的絕佳環境，每班限 2 人。'],
        ];

        $offers = [];
        foreach ($offersData as $index => $data) {
            $providerIndex = (int) floor($index / 3);
            $offer = DivingOffer::firstOrCreate(
                ['title' => $data['title'], 'provider_id' => $providers[$providerIndex]->id],
                array_merge($data, [
                    'provider_id' => $providers[$providerIndex]->id,
                    'rating'      => 0,
                    'reviews'     => 0,
                ])
            );
            $offers[] = $offer;
        }
        return $offers;
    }

    private function createSchedules(array $offers): array
    {
        $pastSchedules   = [];
        $futureSchedules = [];

        foreach ($offers as $index => $offer) {
            $pastDate   = now()->subDays(90 + $index * 5)->toDateString();
            $futureDate = now()->addDays(30 + $index * 7)->toDateString();

            $pastSchedules[] = CourseSchedule::firstOrCreate(
                ['diving_offer_id' => $offer->id, 'scheduled_date' => $pastDate],
                [
                    'provider_id'          => $offer->provider_id,
                    'start_time'           => '09:00',
                    'max_participants'     => 6,
                    'current_participants' => 4,
                    'status'               => ScheduleStatus::Open,
                ]
            );

            $futureSchedules[] = CourseSchedule::firstOrCreate(
                ['diving_offer_id' => $offer->id, 'scheduled_date' => $futureDate],
                [
                    'provider_id'          => $offer->provider_id,
                    'start_time'           => '09:00',
                    'max_participants'     => 6,
                    'current_participants' => 1,
                    'status'               => ScheduleStatus::Open,
                ]
            );
        }

        return [$pastSchedules, $futureSchedules];
    }

    private function createCompletedBookings(array $pastSchedules, array $members, array $offers): array
    {
        $completedBookings = [];
        foreach ($pastSchedules as $index => $schedule) {
            $member  = $members[$index % count($members)];
            $offer   = $offers[$index];
            $booking = Booking::firstOrCreate(
                ['schedule_id' => $schedule->id, 'member_id' => $member->id],
                ['participants' => 1, 'total_price' => $offer->price, 'status' => BookingStatus::Completed]
            );
            $completedBookings[] = [
                'booking'  => $booking,
                'member'   => $member,
                'offer'    => $offer,
                'schedule' => $schedule,
            ];
        }
        return $completedBookings;
    }

    private function createPendingBookings(array $futureSchedules, array $members, array $offers): void
    {
        $statusCycle = [
            BookingStatus::Pending, BookingStatus::Confirmed,
            BookingStatus::Pending, BookingStatus::Confirmed,
            BookingStatus::Pending, BookingStatus::Confirmed,
        ];

        foreach ($futureSchedules as $index => $schedule) {
            $member = $members[($index + 4) % count($members)];
            $offer  = $offers[$index];
            Booking::firstOrCreate(
                ['schedule_id' => $schedule->id, 'member_id' => $member->id],
                [
                    'participants' => 1,
                    'total_price'  => $offer->price,
                    'status'       => $statusCycle[$index % count($statusCycle)],
                ]
            );
        }
    }

    private function createReviews(array $completedBookings): void
    {
        $reviewTexts = [
            5 => [
                '教練超級專業！水下說明非常清楚，全程讓我感到安心。第一次潛水就看到這麼多魚，感動到想哭，絕對會再來！',
                '課程安排超完善，從學科到實際下水都循序漸進。教練英文也很好，外國朋友一起來完全沒問題。強力推薦！',
                '海龜真的出現了！三隻海龜就在我旁邊游過，那一刻我整個人都愣住了，這輩子難忘的體驗。',
                '認證課程物超所值，教練非常有耐心，每個步驟都解釋得很清楚，讓我順利考到 OW 證書！',
                '夜潛太刺激了！螢光珊瑚、章魚、獅子魚，白天完全看不到的生物都出來了，裝備品質很好，非常安全。',
            ],
            4 => [
                '整體體驗很棒，珊瑚保育做得很完善，能見度超過 20 米。等待入水時間稍長，希望未來能改善行程安排。',
                '教練很專業，課程內容扎實。裝備保養良好，建議加入更多生態解說，讓學員更了解看到的生物。',
                '潛點選擇很棒，教練熟悉地形。課程說明文件清楚，行前準備資訊充足，讓第一次潛水的我很放心。',
            ],
            3 => [
                '潛水本身體驗還不錯，但課程安排感覺有點趕，希望每個環節能多一點時間。教練很專業，人手稍嫌不足。',
                '裝備稍舊但堪用，整體體驗中規中矩。適合想嘗試的初學者，進階潛水員可能會覺得行程太簡單。',
            ],
        ];

        $skipIndexes = [3, 9];

        foreach ($completedBookings as $index => $data) {
            if (in_array($index, $skipIndexes)) {
                continue;
            }

            $rating = match (true) {
                $index < 5 => 5,
                $index < 8 => 4,
                default    => 3,
            };

            $textPool = $reviewTexts[$rating];
            Review::firstOrCreate(
                ['diving_offer_id' => $data['offer']->id, 'member_id' => $data['member']->id],
                [
                    'rating'        => $rating,
                    'comment'       => $textPool[$index % count($textPool)],
                    'helpful_count' => rand(0, 20),
                    'is_edited'     => false,
                ]
            );
        }
    }

    private function refreshOfferStats(array $offers): void
    {
        foreach ($offers as $offer) {
            $reviews = Review::where('diving_offer_id', $offer->id)->get();
            if ($reviews->isEmpty()) {
                continue;
            }
            $offer->update([
                'rating'  => round($reviews->avg('rating'), 1),
                'reviews' => $reviews->count(),
            ]);
        }
    }

    private function createNotifications(array $completedBookings): void
    {
        $rows = [];

        foreach (array_slice($completedBookings, 0, 6) as $data) {
            ['booking' => $booking, 'offer' => $offer, 'member' => $member, 'schedule' => $schedule] = $data;
            $date    = $schedule->scheduled_date->toDateString();
            $baseUrl = config('app.frontend_url');

            $rows[] = [
                'id'              => Str::uuid()->toString(),
                'type'            => 'App\Notifications\BookingConfirmedNotification',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $member->id,
                'data'            => json_encode([
                    'type'         => 'booking_confirmed',
                    'title'        => '預約已確認',
                    'body'         => "你的《{$offer->title}》課程預約已由教練確認（時段：{$date}）",
                    'action_url'   => "{$baseUrl}/my-bookings",
                    'related_id'   => $booking->id,
                    'related_type' => 'booking',
                ]),
                'read_at'    => now()->subDays(rand(20, 60))->toDateTimeString(),
                'created_at' => now()->subDays(rand(61, 90))->toDateTimeString(),
                'updated_at' => now()->subDays(rand(20, 60))->toDateTimeString(),
            ];

            $rows[] = [
                'id'              => Str::uuid()->toString(),
                'type'            => 'App\Notifications\BookingCompletedNotification',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $member->id,
                'data'            => json_encode([
                    'type'         => 'booking_completed',
                    'title'        => '課程已完成，歡迎留下評價',
                    'body'         => "你的《{$offer->title}》課程已完成，分享你的潛水心得讓更多人看到！",
                    'action_url'   => "{$baseUrl}/my-bookings",
                    'related_id'   => $booking->id,
                    'related_type' => 'booking',
                ]),
                'read_at'    => null,
                'created_at' => now()->subDays(rand(5, 19))->toDateTimeString(),
                'updated_at' => now()->subDays(rand(5, 19))->toDateTimeString(),
            ];
        }

        if (!empty($rows)) {
            DB::table('notifications')->insert($rows);
        }
    }

    private function printSummary(array $providers, array $members): void
    {
        $this->command->info('');
        $this->command->info('   Admin:  admin@cfdive.com / password123');
        $this->command->info('');
        $this->command->info('   教練帳號（密碼均為 password123）：');
        foreach ($providers as $provider) {
            $profile = ProviderProfile::where('user_id', $provider->id)->value('business_name');
            $this->command->info("   {$provider->email}  ─  {$profile}");
        }
        $this->command->info('');
        $this->command->info('   會員帳號（密碼均為 password123）：');
        foreach ($members as $member) {
            $this->command->info("   {$member->email}  ({$member->name})");
        }
    }
}
