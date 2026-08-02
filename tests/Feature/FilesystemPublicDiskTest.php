<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FilesystemPublicDiskTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(storage_path('app/public/filesystem-public-disk-test.txt'));

        parent::tearDown();
    }

    public function test_public_disk_works_on_local_driver_without_aws_credentials(): void
    {
        Config::set('filesystems.disks.public.driver', 'local');
        Config::set('filesystems.disks.public.key', null);
        Config::set('filesystems.disks.public.secret', null);
        Config::set('filesystems.disks.public.region', null);
        Config::set('filesystems.disks.public.bucket', null);
        Config::set('filesystems.disks.public.endpoint', null);
        Storage::forgetDisk('public');

        Storage::disk('public')->put('filesystem-public-disk-test.txt', 'ok');

        $this->assertTrue(Storage::disk('public')->exists('filesystem-public-disk-test.txt'));

        Storage::disk('public')->delete('filesystem-public-disk-test.txt');
    }

    public function test_public_disk_driver_follows_filesystem_disk_env(): void
    {
        $this->assertSame(
            env('FILESYSTEM_DISK', 'local'),
            config('filesystems.disks.public.driver'),
            'public disk 的 driver 應完全跟隨 FILESYSTEM_DISK env（未設定時預設 local）'
        );
    }
}
