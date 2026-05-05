<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Admin\Models\SmtpConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
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

        $failedJobs = collect();
        try {
            $failedJobs = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(50)
                ->get()
                ->map(function (object $job): object {
                    $payload = json_decode($job->payload, true);
                    $job->job_name = $payload['displayName'] ?? $job->payload;

                    return $job;
                });
        } catch (\Exception) {
        }

        $pendingJobs = 0;
        $pendingByType = collect();
        try {
            $pendingJobs = DB::table('jobs')->count();
            $pendingByType = DB::table('jobs')
                ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.displayName')) as job_name, queue, COUNT(*) as count")
                ->groupBy('job_name', 'queue')
                ->orderByDesc('count')
                ->get();
        } catch (\Exception) {
        }

        $smtpStatus = $this->getSmtpStatus();

        $diskTotal = disk_total_space(base_path());
        $diskFree = disk_free_space(base_path());
        $diskUsed = $diskTotal - $diskFree;
        $diskUsagePercent = round(($diskUsed / $diskTotal) * 100, 1);

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
            'pendingByType',
            'diskUsagePercent',
            'diskUsed',
            'diskTotal',
            'recentErrors',
            'smtpStatus',
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

    public function retryAllFailed(): RedirectResponse
    {
        try {
            $count = DB::table('failed_jobs')->count();
            Artisan::call('queue:retry', ['id' => ['all']]);

            return redirect()->route('admin.system.index')
                ->with('success', "Retried {$count} failed job(s).");
        } catch (\Exception $e) {
            return redirect()->route('admin.system.index')
                ->with('error', "Failed to retry all jobs: {$e->getMessage()}");
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

    public function deleteAllFailed(): RedirectResponse
    {
        try {
            $count = DB::table('failed_jobs')->count();
            DB::table('failed_jobs')->truncate();

            return redirect()->route('admin.system.index')
                ->with('success', "Deleted {$count} failed job(s).");
        } catch (\Exception $e) {
            return redirect()->route('admin.system.index')
                ->with('error', "Failed to delete all failed jobs: {$e->getMessage()}");
        }
    }

    public function clearPendingJobs(): RedirectResponse
    {
        try {
            $count = DB::table('jobs')->count();
            DB::table('jobs')->truncate();

            return redirect()->route('admin.system.index')
                ->with('success', "Cleared {$count} pending job(s) from the queue.");
        } catch (\Exception $e) {
            return redirect()->route('admin.system.index')
                ->with('error', "Failed to clear pending jobs: {$e->getMessage()}");
        }
    }

    /** @return array<string, mixed> */
    private function getSmtpStatus(): array
    {
        try {
            $global = SmtpConfiguration::query()->whereNull('organization_id')->first();
            $orgCount = SmtpConfiguration::query()->whereNotNull('organization_id')->where('is_active', true)->count();

            return [
                'global_configured' => (bool) $global,
                'global_active' => $global?->is_active ?? false,
                'global_host' => $global?->host,
                'global_from' => $global?->from_address,
                'org_custom_count' => $orgCount,
            ];
        } catch (\Exception) {
            return ['global_configured' => false, 'global_active' => false, 'global_host' => null, 'global_from' => null, 'org_custom_count' => 0];
        }
    }

    public function exportErrorsJson(): Response
    {
        $failedJobs = collect();
        try {
            $failedJobs = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(50)
                ->get()
                ->map(function (object $job): array {
                    $payload = json_decode($job->payload, true);

                    return [
                        'id' => $job->id,
                        'job' => $payload['displayName'] ?? 'Unknown',
                        'queue' => $job->queue,
                        'failed_at' => $job->failed_at,
                        'exception' => $job->exception,
                    ];
                });
        } catch (\Exception) {
        }

        $logErrors = collect();
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $lines = array_slice(file($logFile), -500);
            $logErrors = collect($lines)
                ->filter(fn ($line) => preg_match('/\.(ERROR|CRITICAL)/', $line))
                ->values()
                ->take(20)
                ->map(fn ($line) => trim($line));
        }

        $export = [
            'exported_at' => now()->toIso8601String(),
            'app_url' => config('app.url'),
            'failed_jobs' => $failedJobs->values(),
            'recent_log_errors' => $logErrors->values(),
        ];

        $filename = 'antara-errors-'.now()->format('Y-m-d-His').'.json';

        return response(json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportErrorsText(): Response
    {
        $lines = [];
        $lines[] = '=== ANTARA FLOW — ERROR EXPORT FOR CLAUDE CODE ===';
        $lines[] = 'Exported: '.now()->toDateTimeString();
        $lines[] = 'App: '.config('app.url');
        $lines[] = '';

        try {
            $failedJobs = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(50)->get();

            $lines[] = '=== FAILED JOBS ('.count($failedJobs).') ===';
            foreach ($failedJobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobName = $payload['displayName'] ?? 'Unknown';
                $lines[] = '';
                $lines[] = "--- Job #{$job->id} ---";
                $lines[] = "Job:      {$jobName}";
                $lines[] = "Queue:    {$job->queue}";
                $lines[] = "Failed:   {$job->failed_at}";
                $lines[] = 'Exception:';
                $lines[] = $job->exception;
            }
        } catch (\Exception) {
            $lines[] = '(Could not read failed_jobs table)';
        }

        $lines[] = '';
        $lines[] = '=== RECENT LOG ERRORS ===';
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logLines = array_slice(file($logFile), -500);
            $errors = collect($logLines)
                ->filter(fn ($line) => preg_match('/\.(ERROR|CRITICAL)/', $line))
                ->values()
                ->take(20);

            foreach ($errors as $error) {
                $lines[] = trim($error);
            }
        } else {
            $lines[] = '(Log file not found)';
        }

        $content = implode("\n", $lines);
        $filename = 'antara-errors-'.now()->format('Y-m-d-His').'.txt';

        return response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
