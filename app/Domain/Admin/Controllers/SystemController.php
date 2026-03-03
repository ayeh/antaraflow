<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function index(): View
    {
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => config('database.default'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
        ];

        // Failed jobs
        $failedJobs = collect();
        try {
            $failedJobs = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(50)
                ->get();
        } catch (\Exception) {
            // failed_jobs table may not exist
        }

        // Queue stats
        $pendingJobs = 0;
        try {
            $pendingJobs = DB::table('jobs')->count();
        } catch (\Exception) {
            // jobs table may not exist
        }

        // Disk usage
        $diskTotal = disk_total_space(base_path());
        $diskFree = disk_free_space(base_path());
        $diskUsed = $diskTotal - $diskFree;
        $diskUsagePercent = round(($diskUsed / $diskTotal) * 100, 1);

        // Recent log errors (last 20 lines containing ERROR or CRITICAL)
        $recentErrors = collect();
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $lines = array_slice(file($logFile), -500);
            $recentErrors = collect($lines)
                ->filter(fn ($line) => preg_match('/\.(ERROR|CRITICAL)/', $line))
                ->values()
                ->take(20);
        }

        return view('admin.system.index', compact(
            'systemInfo',
            'failedJobs',
            'pendingJobs',
            'diskUsagePercent',
            'diskUsed',
            'diskTotal',
            'recentErrors',
        ));
    }

    public function retryJob(int $id): RedirectResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => [$id]]);

            return redirect()->route('admin.system.index')
                ->with('success', "Job #{$id} queued for retry.");
        } catch (\Exception $e) {
            return redirect()->route('admin.system.index')
                ->with('error', "Failed to retry job: {$e->getMessage()}");
        }
    }

    public function deleteJob(int $id): RedirectResponse
    {
        try {
            DB::table('failed_jobs')->where('id', $id)->delete();

            return redirect()->route('admin.system.index')
                ->with('success', "Failed job #{$id} deleted.");
        } catch (\Exception $e) {
            return redirect()->route('admin.system.index')
                ->with('error', "Failed to delete job: {$e->getMessage()}");
        }
    }
}
