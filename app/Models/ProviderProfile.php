<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="ProviderProfile",
 *     title="潛水業者資料",
 *     description="潛水業者的詳細資料",
 *     @OA\Property(property="id", type="integer", format="int64", example=1, description="資料ID"),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1, description="關聯的使用者ID"),
 *     @OA\Property(property="business_name", type="string", example="藍海潛水中心", description="業者名稱"),
 *     @OA\Property(property="business_license", type="string", example="A123456789", description="營業執照號碼"),
 *     @OA\Property(property="description", type="string", example="專業潛水中心，提供各種潛水課程和裝備租賃服務", description="業者描述"),
 *     @OA\Property(property="contact_person", type="string", example="張三", description="聯絡人"),
 *     @OA\Property(property="contact_phone", type="string", example="0912345678", description="聯絡電話"),
 *     @OA\Property(property="contact_email", type="string", example="contact@bluedive.com", description="聯絡電子郵件"),
 *     @OA\Property(property="address", type="string", example="台灣屏東縣恆春鎮XXX路123號", description="營業地址"),
 *     @OA\Property(property="dive_sites", type="string", example="墾丁,綠島,蘭嶼", description="提供的潛點"),
 *     @OA\Property(property="services", type="string", example="體驗潛水,初級潛水課程,進階潛水課程,裝備租賃", description="提供的服務"),
 *     @OA\Property(property="certifications", type="string", example="PADI五星級潛水中心,SSI認證中心", description="業者相關認證"),
 *     @OA\Property(property="facilities", type="string", example="空氣填充站,沖洗區,更衣室,休息區", description="設施"),
 *     @OA\Property(property="business_hours", type="string", example="週一至週五 09:00-18:00，週六日 08:00-19:00", description="營業時間"),
 *     @OA\Property(property="is_verified", type="boolean", example=true, description="是否通過平台驗證"),
 *     @OA\Property(property="rating", type="number", format="float", example=4.8, description="評分"),
 *     @OA\Property(property="website", type="string", example="https://www.bluedive.com", description="官方網站"),
 *     @OA\Property(property="social_media", type="string", example="https://www.facebook.com/bluedive", description="社群媒體連結"),
 *     @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png", description="業者標誌URL"),
 *     @OA\Property(property="banner_url", type="string", example="https://example.com/banner.png", description="業者橫幅URL"),
 *     @OA\Property(property="is_active", type="boolean", example=true, description="是否啟用"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z", description="創建時間"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z", description="更新時間")
 * )
 */
class ProviderProfile extends Model
{
    /**
     * 可以批量分配的屬性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'business_name',
        'business_license',
        'description',
        'contact_person',
        'contact_phone',
        'contact_email',
        'address',
        'dive_sites',
        'services',
        'certifications',
        'facilities',
        'business_hours',
        'is_verified',
        'rating',
        'website',
        'social_media',
        'logo_url',
        'banner_url',
        'is_active'
    ];

    /**
     * 與用戶的關聯
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
