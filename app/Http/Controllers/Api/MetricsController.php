<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    /**
     * The metrics service instance.
     *
     * @var \App\Services\Monitoring\MetricsService
     */
    protected MetricsService $metricsService;

    /**
     * Create a new controller instance.
     *
     * @param \App\Services\Monitoring\MetricsService $metricsService
     * @return void
     */
    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    /**
     * Export metrics in Prometheus format.
     *
     * @param Request $request
     * @return Response
     */
    public function export(Request $request): Response
    {
        // Generate metrics in Prometheus format
        $output = $this->generatePrometheusMetrics();
        
        return response($output, 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Generate metrics in Prometheus format.
     *
     * @return string
     */
    protected function generatePrometheusMetrics(): string
    {
        $metrics = [];

        // API metrics
        $apiMetrics = $this->metricsService->getApiMetrics();
        foreach ($apiMetrics as $metric) {
            $endpoint = str_replace(['/', '.', '-'], '_', $metric->endpoint);
            $sportType = $metric->sport_type;
            $success = $metric->success ? 'true' : 'false';
            
            $metrics[] = "# HELP bts_api_requests_total Total number of API requests";
            $metrics[] = "# TYPE bts_api_requests_total counter";
            $metrics[] = "bts_api_requests_total{endpoint=\"$endpoint\",sport_type=\"$sportType\",success=\"$success\"} $metric->total_requests";
            
            if ($metric->avg_response_time) {
                $metrics[] = "# HELP bts_api_response_time_ms Average API response time in milliseconds";
                $metrics[] = "# TYPE bts_api_response_time_ms gauge";
                $metrics[] = "bts_api_response_time_ms{endpoint=\"$endpoint\",sport_type=\"$sportType\"} $metric->avg_response_time";
            }
            
            if ($metric->max_response_time) {
                $metrics[] = "# HELP bts_api_max_response_time_ms Maximum API response time in milliseconds";
                $metrics[] = "# TYPE bts_api_max_response_time_ms gauge";
                $metrics[] = "bts_api_max_response_time_ms{endpoint=\"$endpoint\",sport_type=\"$sportType\"} $metric->max_response_time";
            }
        }

        // Queue metrics
        $queueMetrics = $this->metricsService->getQueueMetrics();
        $metrics[] = "# HELP bts_queue_pending_jobs Number of pending jobs in the queue";
        $metrics[] = "# TYPE bts_queue_pending_jobs gauge";
        $metrics[] = "bts_queue_pending_jobs " . $queueMetrics['pending_jobs'];
        
        $metrics[] = "# HELP bts_queue_failed_jobs Number of failed jobs";
        $metrics[] = "# TYPE bts_queue_failed_jobs gauge";
        $metrics[] = "bts_queue_failed_jobs " . $queueMetrics['failed_jobs'];

        // Cache metrics
        $cacheMetrics = $this->metricsService->getCacheMetrics();
        $metrics[] = "# HELP bts_cache_hits Number of cache hits";
        $metrics[] = "# TYPE bts_cache_hits counter";
        $metrics[] = "bts_cache_hits " . $cacheMetrics['cache_hits'];
        
        $metrics[] = "# HELP bts_cache_misses Number of cache misses";
        $metrics[] = "# TYPE bts_cache_misses counter";
        $metrics[] = "bts_cache_misses " . $cacheMetrics['cache_misses'];
        
        $metrics[] = "# HELP bts_cache_hit_rate Cache hit rate percentage";
        $metrics[] = "# TYPE bts_cache_hit_rate gauge";
        $metrics[] = "bts_cache_hit_rate " . $cacheMetrics['hit_rate'];

        // Database metrics
        $dbMetrics = $this->metricsService->getDatabaseMetrics();
        foreach ($dbMetrics as $key => $value) {
            $metricName = "bts_db_" . $key;
            $metrics[] = "# HELP $metricName Number of $key in the database";
            $metrics[] = "# TYPE $metricName gauge";
            $metrics[] = "$metricName $value";
        }

        // System metrics
        $systemMetrics = $this->metricsService->getSystemMetrics();
        $metrics[] = "# HELP bts_memory_usage Memory usage in bytes";
        $metrics[] = "# TYPE bts_memory_usage gauge";
        $metrics[] = "bts_memory_usage " . $systemMetrics['memory_usage'];
        
        $metrics[] = "# HELP bts_memory_peak Peak memory usage in bytes";
        $metrics[] = "# TYPE bts_memory_peak gauge";
        $metrics[] = "bts_memory_peak " . $systemMetrics['memory_peak'];

        return implode("\n", $metrics) . "\n";
    }

    /**
     * Return metrics in JSON format for the dashboard API.
     *
     * @param Request $request
     * @return Response
     */
    public function dashboard(Request $request): Response
    {
        $metrics = [
            'api' => $this->metricsService->getApiMetrics(),
            'queue' => $this->metricsService->getQueueMetrics(),
            'cache' => $this->metricsService->getCacheMetrics(),
            'database' => $this->metricsService->getDatabaseMetrics(),
            'system' => $this->metricsService->getSystemMetrics(),
        ];

        return response($metrics, 200, ['Content-Type' => 'application/json']);
    }
}
