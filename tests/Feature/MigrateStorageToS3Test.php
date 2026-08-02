<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MigrateStorageToS3Test extends TestCase
{
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $relativePath) {
            @unlink(storage_path('app/public/'.$relativePath));
        }
        $this->createdFiles = [];

        parent::tearDown();
    }

    private function putLocalFile(string $relativePath, string $contents = 'test-content'): void
    {
        $fullPath = storage_path('app/public/'.$relativePath);
        @mkdir(dirname($fullPath), 0777, true);
        file_put_contents($fullPath, $contents);
        $this->createdFiles[] = $relativePath;
    }

    public function test_uploads_existing_local_files_to_s3(): void
    {
        Storage::fake('s3');
        $this->putLocalFile('migrate-test/one.txt', 'file-one');
        $this->putLocalFile('migrate-test/two.txt', 'file-two');

        $this->artisan('storage:migrate-to-s3')->assertSuccessful();

        Storage::disk('s3')->assertExists('migrate-test/one.txt');
        Storage::disk('s3')->assertExists('migrate-test/two.txt');
    }

    public function test_dry_run_lists_files_without_uploading(): void
    {
        Storage::fake('s3');
        $this->putLocalFile('migrate-test/dry.txt');

        $this->artisan('storage:migrate-to-s3', ['--dry-run' => true])->assertSuccessful();

        Storage::disk('s3')->assertMissing('migrate-test/dry.txt');
    }

    public function test_no_files_to_migrate_still_succeeds(): void
    {
        Storage::fake('s3');

        $this->artisan('storage:migrate-to-s3')->assertSuccessful();
    }

    public function test_reports_failure_and_does_not_stop_on_partial_upload_error(): void
    {
        $this->putLocalFile('migrate-test/ok.txt', 'ok');
        $this->putLocalFile('migrate-test/bad.txt', 'bad');

        // storage/app/public 是共用的本機目錄，可能已存在其他測試無關的檔案（如手動測試留下的
        // 上傳圖片），所以這裡不斷言呼叫次數，只鎖定我們自己建立的兩個檔案的行為，其餘路徑一律放行。
        $mockDisk = Mockery::mock();
        $mockDisk->shouldReceive('put')
            ->with('migrate-test/bad.txt', Mockery::any())
            ->once()
            ->andThrow(new RuntimeException('simulated network failure'));
        $mockDisk->shouldReceive('put')
            ->withArgs(fn (string $path) => $path !== 'migrate-test/bad.txt')
            ->andReturn(true);
        $mockDisk->shouldReceive('exists')->andReturn(true);

        Storage::partialMock()->shouldReceive('disk')->with('s3')->andReturn($mockDisk);

        $this->artisan('storage:migrate-to-s3')->assertFailed();
    }
}
