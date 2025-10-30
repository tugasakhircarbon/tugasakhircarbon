<?php

namespace App\Console\Commands;

use App\Services\EmissionTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorEmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emissions:monitor 
                            {--update-all : Update semua carbon credits dengan data emisi terbaru}
                            {--generate-report : Generate laporan emisi harian}
                            {--cleanup-days=90 : Jumlah hari data yang akan dipertahankan}
                            {--device= : Monitor device tertentu saja}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor dan update data emisi untuk semua carbon credits';

    protected $emissionTrackingService;

    public function __construct(EmissionTrackingService $emissionTrackingService)
    {
        parent::__construct();
        $this->emissionTrackingService = $emissionTrackingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🌱 Memulai monitoring emisi...');
        
        try {
            // Update semua data emisi
            if ($this->option('update-all')) {
                $this->updateAllEmissions();
            }

            // Monitor dan kirim alert
            $this->monitorAndAlert();

            // Generate laporan harian
            if ($this->option('generate-report')) {
                $this->generateDailyReport();
            }

            // Cleanup data lama
            $cleanupDays = (int) $this->option('cleanup-days');
            if ($cleanupDays > 0) {
                $this->cleanupOldData($cleanupDays);
            }

            $this->info('✅ Monitoring emisi selesai!');
            
        } catch (\Exception $e) {
            $this->error('❌ Error dalam monitoring emisi: ' . $e->getMessage());
            Log::error('Emission monitoring failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Update semua data emisi
     */
    private function updateAllEmissions()
    {
        $this->info('📊 Mengupdate data emisi untuk semua carbon credits...');
        
        $deviceId = $this->option('device');
        
        if ($deviceId) {
            $carbonCredit = \App\Models\CarbonCredit::where('device_id', $deviceId)->first();
            if ($carbonCredit) {
                $updated = $this->emissionTrackingService->updateCarbonCreditEmissions($carbonCredit);
                $this->info($updated ? "✅ Device {$deviceId} berhasil diupdate" : "⚠️ Device {$deviceId} gagal diupdate");
            } else {
                $this->warn("⚠️ Device {$deviceId} tidak ditemukan");
            }
        } else {
            $updatedCount = $this->emissionTrackingService->updateAllEmissionData();
            $this->info("✅ Berhasil mengupdate {$updatedCount} carbon credits");
        }
    }

    /**
     * Monitor emisi dan kirim alert
     */
    private function monitorAndAlert()
    {
        $this->info('🚨 Memonitor emisi dan mengirim alert...');
        
        $alerts = $this->emissionTrackingService->monitorEmissions();
        
        if (empty($alerts)) {
            $this->info('✅ Tidak ada alert emisi');
            return;
        }

        $this->warn("⚠️ Ditemukan " . count($alerts) . " alert emisi:");
        
        foreach ($alerts as $alertGroup) {
            $carbonCredit = $alertGroup['carbon_credit'];
            $this->line("📍 Device: {$carbonCredit->device_id} (NRKB: {$carbonCredit->nrkb})");
            
            foreach ($alertGroup['alerts'] as $alert) {
                $icon = match($alert['severity']) {
                    'critical' => '🔴',
                    'warning' => '🟡',
                    'error' => '🟠',
                    default => '🔵'
                };
                
                $this->line("  {$icon} {$alert['type']}: {$alert['message']}");
            }
            $this->line('');
        }
    }

    /**
     * Generate laporan emisi harian
     */
    private function generateDailyReport()
    {
        $this->info('📋 Generating laporan emisi harian...');
        
        $report = $this->emissionTrackingService->generateDailyEmissionReport();
        
        $this->info("📊 Laporan Emisi - {$report['date']}");
        $this->line("Total devices: {$report['total_devices']}");
        $this->line("Active devices: {$report['active_devices']}");
        $this->line("Total emisi: " . number_format($report['total_emissions_kg'], 2) . " kg");
        $this->line("Rata-rata emisi: " . number_format($report['average_emissions_kg'], 2) . " kg");
        $this->line("High emission devices: " . count($report['high_emission_devices']));
        
        if (!empty($report['high_emission_devices'])) {
            $this->warn("\n🔥 Top emitters hari ini:");
            foreach (array_slice($report['high_emission_devices'], 0, 5) as $device) {
                $this->line("  • {$device['nrkb']} ({$device['vehicle_type']}): " . 
                           number_format($device['emissions_kg'], 2) . " kg");
            }
        }

        // Simpan laporan ke log
        Log::channel('emission_reports')->info('Daily emission report', $report);
    }

    /**
     * Cleanup data lama
     */
    private function cleanupOldData($days)
    {
        $this->info("🧹 Membersihkan data lebih dari {$days} hari...");
        
        $deleted = $this->emissionTrackingService->cleanupOldData($days);
        
        $this->info("✅ Berhasil menghapus {$deleted['total']} records:");
        $this->line("  • Sensor data: {$deleted['sensor_data']}");
        $this->line("  • CO2e data: {$deleted['co2e_data']}");
        $this->line("  • GPS data: {$deleted['gps_data']}");
        $this->line("  • Status logs: {$deleted['status_logs']}");
    }

    /**
     * Display dashboard statistics
     */
    private function showDashboardStats()
    {
        $stats = $this->emissionTrackingService->getDashboardStats();
        
        $this->info("\n📊 Dashboard Statistics:");
        $this->line("Total devices: {$stats['total_devices']}");
        $this->line("Active devices: {$stats['active_devices']}");
        $this->line("Inactive devices: {$stats['inactive_devices']}");
        $this->line("Today records: {$stats['today_records']}");
        $this->line("Today emissions: " . number_format($stats['today_total_emissions_kg'], 2) . " kg");
        $this->line("Alert count: {$stats['alert_count']}");
        
        if (!empty($stats['top_emitters'])) {
            $this->line("\nTop emitters:");
            foreach ($stats['top_emitters'] as $emitter) {
                $this->line("  • {$emitter['nrkb']}: " . number_format($emitter['daily_emissions_kg'], 2) . " kg");
            }
        }
    }
}
