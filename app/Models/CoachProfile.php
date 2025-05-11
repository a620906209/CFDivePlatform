<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="CoachProfile",
 *     title="教練個人資料",
 *     description="教練的詳細個人資料",
 *     @OA\Property(property="id", type="integer", format="int64", example=1, description="資料ID"),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1, description="關聯的使用者ID"),
 *     @OA\Property(property="bio", type="string", example="專業潛水教練，擁有10年教學經驗", description="個人簡介"),
 *     @OA\Property(property="expertise", type="string", example="自由潛水,水肺潛水", description="專長領域"),
 *     @OA\Property(property="certification", type="string", example="PADI專業潛水教練", description="證照資訊"),
 *     @OA\Property(property="experience", type="string", example="10年教學經驗，帶領超過500名學員", description="教學經驗"),
 *     @OA\Property(property="rating", type="number", format="float", example=4.8, description="評分"),
 *     @OA\Property(property="availability", type="string", example="週一至週五 09:00-18:00", description="可授課時間"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z", description="創建時間"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z", description="更新時間")
 * )
 */
class CoachProfile extends Model
{
    /**
     * 可以批量分配的屬性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bio',
        'expertise',
        'certification',
        'experience',
        'rating',
        'availability'
    ];

    /**
     * 與用戶的關聯
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
