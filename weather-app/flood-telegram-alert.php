<?php
/**
 * 🚨 D3: Telegram Push Notification
 * แจ้งเตือนเมื่อ flood risk สูงขึ้น (status >= watch)
 * 
 * Setup:
 * 1. สร้าง Telegram Bot: @BotFather
 * 2. ตั้งค่าใน .env:
 *    TELEGRAM_BOT_TOKEN=xxx
 *    TELEGRAM_CHAT_ID=xxx
 * 3. เรียก: php flood-telegram-alert.php
 *    หรือตั้ง cron ทุก 30 นาที
 */

header('Content-Type: application/json; charset=utf-8');

$ENV_FILE = __DIR__ . '/../.env';
$BOT_TOKEN = '';
$CHAT_ID = '';
$STATUS = 'unknown';

if (file_exists($ENV_FILE)) {
    foreach (file($ENV_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, 'TELEGRAM_BOT_TOKEN=') === 0) $BOT_TOKEN = trim(substr($line, 19));
        if (strpos($line, 'TELEGRAM_CHAT_ID=') === 0) $CHAT_ID = trim(substr($line, 17));
    }
}

if (!$BOT_TOKEN || !$CHAT_ID) {
    echo json_encode(['error' => 'TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID not set in .env', 'status' => $STATUS], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.telegram.org/bot' . $BOT_TOKEN . '/sendMessage',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);

$msg = "🚨 <b>แจ้งเตือนน้ำท่วม (ทดสอบ)</b>\n\n📍 โซน: ควนมะพร้าว\n⚠️ ระดับ: เฝ้าระวัง\n🕐 เวลา: " . date('Y-m-d H:i:s') . "\n\nนี่คือข้อความทดสอบจากระบบติดตาม";

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'chat_id' => $CHAT_ID,
    'text' => $msg,
    'parse_mode' => 'HTML',
]));

$result = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo $result ?: json_encode(['error' => 'No response from Telegram', 'http_code' => $code], JSON_UNESCAPED_UNICODE);
