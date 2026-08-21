<?php
/**
 * 🌦️ Weather SLB - Health Dashboard
 * URL: https://weather.mt-ruts.com/health.php
 * Shows: status of all services + recent logs + uptime stats
 */

$results_file = '/home/wasusuk/.openclaw/workspace/memory/weather-monitor-results.json';
$log_file = '/home/wasusuk/.openclaw/workspace/memory/weather-monitor.log';

$results = file_exists($results_file) ? json_decode(file_get_contents($results_file), true) : null;
$log_lines = file_exists($log_file) ? array_slice(explode("\n", file_get_contents($log_file)), -30) : [];

// Calculate uptime stats from logs
$uptime_total = 0;
$uptime_ok = 0;
$uptime_warns = [];
$first_log = null;
$last_log = null;

if (file_exists($log_file)) {
  $all_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!empty($all_lines)) {
    $first_log = $all_lines[0];
    $last_log = end($all_lines);
    $uptime_total = count($all_lines);
    foreach ($all_lines as $line) {
      if (strpos($line, 'fail_count=0') !== false) {
        $uptime_ok++;
      }
    }
  }
}

$uptime_pct = $uptime_total > 0 ? round(($uptime_ok / $uptime_total) * 100, 2) : 0;
$service_emojis = [
  'page' => '🌐',
  'sw' => '🛠️',
  'manifest' => '📄',
  'open_meteo' => '📡',
  'marine' => '🌊',
  'tmd' => '🇹🇭'
];
$service_names = [
  'page' => 'Weather SLB',
  'sw' => 'Service Worker',
  'manifest' => 'PWA Manifest',
  'open_meteo' => 'Open-Meteo Weather',
  'marine' => 'Open-Meteo Marine',
  'tmd' => 'TMD Website'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Weather SLB - Health Dashboard</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Inter', 'Sarabun', sans-serif; background: linear-gradient(135deg, #f0f4ff 0%, #f8fafc 100%); color: #1e293b; margin: 0; padding: 20px; min-height: 100vh; }
  .container { max-width: 1100px; margin: 0 auto; }
  h1 { color: #1e3a8a; margin: 0 0 8px 0; font-size: 1.8rem; }
  .subtitle { color: #64748b; margin: 0 0 24px 0; font-size: 0.95rem; }
  .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
  .stat-card { background: white; border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
  .stat-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
  .stat-value { font-size: 1.8rem; font-weight: 800; color: #1e3a8a; margin-top: 4px; font-family: 'JetBrains Mono', monospace; }
  .stat-value.ok { color: #059669; }
  .stat-value.warn { color: #d97706; }
  .stat-value.fail { color: #dc2626; }
  .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; margin-bottom: 20px; }
  .card h2 { margin: 0 0 12px 0; color: #1e3a8a; font-size: 1.1rem; }
  .service-row { display: flex; align-items: center; padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
  .service-row:last-child { border-bottom: none; }
  .service-emoji { font-size: 1.3rem; margin-right: 12px; }
  .service-name { flex: 1; font-weight: 500; }
  .service-status { padding: 4px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
  .status-ok { background: #d1fae5; color: #065f46; }
  .status-fail { background: #fee2e2; color: #7f1d1d; }
  .log-box { background: #0f172a; color: #e2e8f0; padding: 14px; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 11px; line-height: 1.6; overflow-x: auto; max-height: 280px; overflow-y: auto; }
  .log-box .ok { color: #4ade80; }
  .log-box .fail { color: #f87171; }
  .meta { font-size: 12px; color: #64748b; }
  .footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 30px; }
</style>
</head>
<body>
<div class="container">
  <h1>🌦️ Weather SLB - Health Dashboard</h1>
  <p class="subtitle">📊 ตรวจสอบสถานะ Phase 1-6 อัตโนมัติทุก 5 นาที</p>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Uptime (7d)</div>
      <div class="stat-value <?= $uptime_pct >= 99 ? 'ok' : ($uptime_pct >= 95 ? 'warn' : 'fail') ?>"><?= $uptime_pct ?>%</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Checks</div>
      <div class="stat-value"><?= $uptime_total ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Failures</div>
      <div class="stat-value <?= ($uptime_total - $uptime_ok) > 0 ? 'warn' : 'ok' ?>"><?= $uptime_total - $uptime_ok ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Current Status</div>
      <div class="stat-value <?= $results && $results['fail_count'] == 0 ? 'ok' : 'fail' ?>">
        <?= $results && $results['fail_count'] == 0 ? '✅ OK' : '⚠️ ' . ($results['fail_count'] ?? '?') ?>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>📡 Service Status (Latest Check)</h2>
    <?php if ($results): ?>
      <div class="meta">⏰ Last check: <b><?= htmlspecialchars($results['timestamp']) ?></b></div>
      <?php foreach ($results as $key => $value): 
        if ($key === 'timestamp' || $key === 'fail_count' || $key === 'failed_services') continue;
        $is_ok = strpos($value, 'OK') === 0;
        $emoji = $service_emojis[$key] ?? '🔹';
        $name = $service_names[$key] ?? $key;
      ?>
      <div class="service-row">
        <span class="service-emoji"><?= $emoji ?></span>
        <span class="service-name"><?= htmlspecialchars($name) ?></span>
        <span class="service-status <?= $is_ok ? 'status-ok' : 'status-fail' ?>"><?= htmlspecialchars($value) ?></span>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="meta">⏳ ยังไม่มีข้อมูล — รอ cron run ครั้งแรก (ภายใน 5 นาที)</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>📜 Recent Logs (last 30 lines)</h2>
    <div class="log-box">
      <?php if (!empty($log_lines)): ?>
        <?php foreach ($log_lines as $line): 
          $cls = (strpos($line, 'fail_count=0') !== false) ? 'ok' : 'fail';
          $line_clean = htmlspecialchars($line);
          echo "<span class=\"$cls\">$line_clean</span>\n";
        endforeach; ?>
      <?php else: ?>
        <span class="meta">⏳ ยังไม่มี logs</span>
      <?php endif; ?>
    </div>
  </div>

  <p class="footer">🌦️ Weather SLB Health Monitor · Cron */5 * * * * · Phase 7 Deploy + Monitor</p>
</div>
</body>
</html>
