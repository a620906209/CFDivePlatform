<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateStorageToS3 extends Command
{
    protected $signature = 'storage:migrate-to-s3
                            {--dry-run : 只列出將上傳的檔案與總數，不實際執行}';

    protected $description = '將本機 storage/app/public 下既有上傳檔案搬遷至 S3 相容物件儲存（public disk 切換前的一次性遷移，不刪除本機檔案）';

    public function handle(): int
    {
        // 直接指定 local 磁碟為來源，不透過會依 FILESYSTEM_DISK 變動的 'public' disk，
        // 避免這支指令在 FILESYSTEM_DISK 已切成 s3 後誤把來源當成目的地。
        $source = Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/public'),
        ]);

        $files = $source->allFiles();

        if (empty($files)) {
            $this->info('storage/app/public 底下沒有檔案，無需遷移。');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(sprintf('[dry-run] 將上傳 %d 個檔案：', count($files)));

            foreach ($files as $file) {
                $this->line("  - {$file}");
            }

            return self::SUCCESS;
        }

        $destination = Storage::disk('s3');
        $failed = [];

        $this->info(sprintf('開始上傳 %d 個檔案至 S3...', count($files)));
        $bar = $this->output->createProgressBar(count($files));

        foreach ($files as $file) {
            try {
                $destination->put($file, $source->get($file));

                if (! $destination->exists($file)) {
                    $failed[] = $file;
                }
            } catch (Throwable $e) {
                $failed[] = $file;
                $this->newLine();
                $this->warn("上傳失敗：{$file}（{$e->getMessage()}）");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $succeeded = count($files) - count($failed);
        $this->info(sprintf('完成：%d/%d 個檔案上傳成功。', $succeeded, count($files)));

        if (! empty($failed)) {
            $this->error('以下檔案上傳失敗，本機檔案未刪除，可重新執行本指令補上傳：');

            foreach ($failed as $file) {
                $this->line("  - {$file}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
