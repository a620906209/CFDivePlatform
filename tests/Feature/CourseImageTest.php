<?php

namespace Tests\Feature;

use App\Models\CourseImage;
use App\Models\DivingOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseImageTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(): User
    {
        return User::factory()->create(['role' => 'provider']);
    }

    private function makeOffer(User $provider): DivingOffer
    {
        return DivingOffer::create([
            'provider_id' => $provider->id,
            'title'       => 'Test Course',
            'location'    => 'Test',
            'spot'        => 'Test Spot',
            'price'       => 1000,
            'region'      => '南部',
            'rating'      => 0,
            'reviews'     => 0,
        ]);
    }

    private function fakeImage(string $name = 'test.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 100, 100)->size(500);
    }

    public function test_upload_cover_success(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->postJson("/api/provider/offers/{$offer->id}/cover", ['image' => $this->fakeImage()])
            ->assertOk()->assertJsonPath('status', true);

        $offer->refresh();
        $this->assertNotNull($offer->cover_image);
        Storage::disk('public')->assertExists($offer->cover_image);
    }

    public function test_upload_cover_wrong_mime(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->postJson("/api/provider/offers/{$offer->id}/cover", [
                'image' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])->assertStatus(422);
    }

    public function test_upload_cover_too_large(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->postJson("/api/provider/offers/{$offer->id}/cover", [
                'image' => UploadedFile::fake()->image('big.png')->size(11000),
            ])->assertStatus(422);
    }

    public function test_upload_cover_accepts_phone_sized_original(): void
    {
        // 原上限 2MB 會擋掉手機原圖；放寬至 10MB 後 5MB 應通過（伺服器端會壓縮）
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->postJson("/api/provider/offers/{$offer->id}/cover", [
                'image' => UploadedFile::fake()->image('phone.jpg', 800, 600)->size(5000),
            ])->assertOk();
    }

    public function test_uploaded_cover_is_stored_as_jpeg(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->postJson("/api/provider/offers/{$offer->id}/cover", [
                'image' => UploadedFile::fake()->image('photo.png', 400, 300),
            ])->assertOk();

        $path = $offer->fresh()->cover_image;
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_oversized_cover_is_scaled_down_to_2048(): void
    {
        // 2048px 上限保護的是課程列表頁的載入重量：原圖直存會讓手機照片
        // （數 MB）直接進列表，壓縮管線是 O3.1 體感優化的核心
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->postJson("/api/provider/offers/{$offer->id}/cover", [
                'image' => UploadedFile::fake()->image('huge.jpg', 3000, 2500),
            ])->assertOk();

        $stored = Storage::disk('public')->get($offer->fresh()->cover_image);
        [$width, $height] = getimagesizefromstring($stored);
        $this->assertLessThanOrEqual(2048, $width);
        $this->assertLessThanOrEqual(2048, $height);
    }

    public function test_small_image_is_not_upscaled(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->postJson("/api/provider/offers/{$offer->id}/cover", [
                'image' => UploadedFile::fake()->image('small.jpg', 640, 480),
            ])->assertOk();

        $stored = Storage::disk('public')->get($offer->fresh()->cover_image);
        [$width, $height] = getimagesizefromstring($stored);
        $this->assertSame(640, $width);
        $this->assertSame(480, $height);
    }

    public function test_upload_cover_forbidden_for_other_provider(): void
    {
        Storage::fake('public');
        $offer = $this->makeOffer($this->makeProvider());

        $this->actingAs($this->makeProvider())
            ->postJson("/api/provider/offers/{$offer->id}/cover", ['image' => $this->fakeImage()])
            ->assertStatus(403);
    }

    public function test_delete_cover_removes_file(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->postJson("/api/provider/offers/{$offer->id}/cover", ['image' => $this->fakeImage()]);
        $offer->refresh();
        $oldPath = $offer->cover_image;

        $this->actingAs($provider)
            ->deleteJson("/api/provider/offers/{$offer->id}/cover")
            ->assertOk();

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertNull($offer->fresh()->cover_image);
    }

    public function test_delete_cover_when_no_cover_is_ok(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->deleteJson("/api/provider/offers/{$offer->id}/cover")
            ->assertOk();
    }

    public function test_delete_cover_forbidden_for_other_provider(): void
    {
        Storage::fake('public');
        $offer = $this->makeOffer($this->makeProvider());

        $this->actingAs($this->makeProvider())
            ->deleteJson("/api/provider/offers/{$offer->id}/cover")
            ->assertStatus(403);
    }

    public function test_upload_gallery_image_success(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)
            ->postJson("/api/provider/offers/{$offer->id}/images", ['image' => $this->fakeImage()])
            ->assertStatus(201);

        $this->assertDatabaseCount('course_images', 1);
    }

    public function test_gallery_max_3_images(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($provider)
                ->postJson("/api/provider/offers/{$offer->id}/images", ['image' => $this->fakeImage()]);
        }

        $this->actingAs($provider)
            ->postJson("/api/provider/offers/{$offer->id}/images", ['image' => $this->fakeImage()])
            ->assertStatus(422);
    }

    public function test_gallery_sort_order_increments(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $r1 = $this->actingAs($provider)->postJson("/api/provider/offers/{$offer->id}/images", ['image' => $this->fakeImage()]);
        $r2 = $this->actingAs($provider)->postJson("/api/provider/offers/{$offer->id}/images", ['image' => $this->fakeImage()]);

        $this->assertEquals(1, $r1->json('data.sort_order'));
        $this->assertEquals(2, $r2->json('data.sort_order'));
    }

    public function test_upload_image_forbidden_for_other_provider(): void
    {
        Storage::fake('public');
        $offer = $this->makeOffer($this->makeProvider());

        $this->actingAs($this->makeProvider())
            ->postJson("/api/provider/offers/{$offer->id}/images", ['image' => $this->fakeImage()])
            ->assertStatus(403);
    }

    public function test_delete_gallery_image_removes_file(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $res   = $this->actingAs($provider)->postJson("/api/provider/offers/{$offer->id}/images", ['image' => $this->fakeImage()]);
        $imgId = $res->json('data.id');
        $path  = CourseImage::find($imgId)->image_path;

        $this->actingAs($provider)->deleteJson("/api/provider/images/{$imgId}")->assertOk();

        $this->assertDatabaseMissing('course_images', ['id' => $imgId]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_image_forbidden_for_other_provider(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $res   = $this->actingAs($provider)->postJson("/api/provider/offers/{$offer->id}/images", ['image' => $this->fakeImage()]);
        $imgId = $res->json('data.id');

        $this->actingAs($this->makeProvider())
            ->deleteJson("/api/provider/images/{$imgId}")
            ->assertStatus(403);
    }

    public function test_deleting_offer_removes_storage_directory(): void
    {
        Storage::fake('public');
        $provider = $this->makeProvider();
        $offer    = $this->makeOffer($provider);

        $this->actingAs($provider)->postJson("/api/provider/offers/{$offer->id}/cover", ['image' => $this->fakeImage()]);
        $this->actingAs($provider)->postJson("/api/provider/offers/{$offer->id}/images", ['image' => $this->fakeImage()]);

        $offerId = $offer->id;
        $offer->delete();

        Storage::disk('public')->assertDirectoryEmpty("offers/{$offerId}");
    }
}

