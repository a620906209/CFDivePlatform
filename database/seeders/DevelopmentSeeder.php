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
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::firstOrCreate(['email' => 'admin@cfdive.com'], [
            'name' => '平台管理員', 'password' => Hash::make('password123'),
            'role' => 'admin', 'is_active' => true,
        ]);
        AdminProfile::firstOrCreate(['user_id' => $admin->id], ['department' => '營運']);

        // Coach
        $coach = User::firstOrCreate(['email' => 'coach@cfdive.com'], [
            'name' => '蔡教練', 'password' => Hash::make('password123'),
            'role' => 'provider', 'is_active' => true,
        ]);
        ProviderProfile::firstOrCreate(['user_id' => $coach->id], [
            'business_name' => '藍海潛水工作室',
            'description'   => '專業 PADI 認證教練，10 年教學經驗',
            'contact_phone' => '0912345678',
            'contact_email' => 'coach@cfdive.com',
            'is_verified'   => true,
        ]);

        // Member
        $member = User::firstOrCreate(['email' => 'member@cfdive.com'], [
            'name' => '測試會員', 'password' => Hash::make('password123'),
            'role' => 'member', 'is_active' => true,
        ]);
        MemberProfile::firstOrCreate(['user_id' => $member->id], ['gender' => 'male']);

        // Offers
        $offer = DivingOffer::firstOrCreate(
            ['title' => '潛入海底 — 入門體驗', 'provider_id' => $coach->id],
            [
                'location' => '墾丁', 'spot' => '南灣', 'price' => 6000,
                'region' => '南部', 'tag' => '初學者',
                'badges' => ['PADI認證', '含裝備'],
                'description' => '適合零基礎的水肺潛水入門課程，由專業教練全程陪同。',
                'rating' => 0, 'reviews' => 0,
            ]
        );

        DivingOffer::firstOrCreate(
            ['title' => '進階深潛探索', 'provider_id' => $coach->id],
            [
                'location' => '小琉球', 'spot' => '美人洞', 'price' => 9800,
                'region' => '南部', 'tag' => '進階',
                'badges' => ['AOW認證', '含住宿'],
                'description' => '探索 30 米深海，適合已有 OW 認證的潛水愛好者。',
                'rating' => 0, 'reviews' => 0,
            ]
        );

        // 未來時段（開放預約）
        $futureSchedule = CourseSchedule::firstOrCreate(
            ['diving_offer_id' => $offer->id, 'scheduled_date' => now()->addDays(14)->toDateString()],
            [
                'provider_id' => $coach->id, 'start_time' => '09:00',
                'max_participants' => 5, 'current_participants' => 0,
                'status' => ScheduleStatus::Open,
            ]
        );

        // 過去時段（供測試 completed booking）
        $pastSchedule = CourseSchedule::firstOrCreate(
            ['diving_offer_id' => $offer->id, 'scheduled_date' => now()->subDays(7)->toDateString()],
            [
                'provider_id' => $coach->id, 'start_time' => '09:00',
                'max_participants' => 5, 'current_participants' => 1,
                'status' => ScheduleStatus::Open,
            ]
        );

        // Pending booking（未來）
        Booking::firstOrCreate(
            ['schedule_id' => $futureSchedule->id, 'member_id' => $member->id],
            ['participants' => 1, 'total_price' => $offer->price, 'status' => BookingStatus::Pending]
        );

        // Completed booking（可評價）
        Booking::firstOrCreate(
            ['schedule_id' => $pastSchedule->id, 'member_id' => $member->id],
            ['participants' => 1, 'total_price' => $offer->price, 'status' => BookingStatus::Completed]
        );

        $this->command->info('✅ Seed 完成');
        $this->command->info('   Admin:  admin@cfdive.com  / password123');
        $this->command->info('   Coach:  coach@cfdive.com  / password123');
        $this->command->info('   Member: member@cfdive.com / password123');
    }
}
