<?php
// ดึงค่า Config จาก Railway Variables
$token = getenv('BOT_TOKEN');
$api_url = "https://api.telegram.org/bot" . $token;

// รับข้อมูลจาก Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update || !isset($update["message"])) exit;

$chat_id = $update["message"]["chat"]["id"];
$text = $update["message"]["text"];

// Logic แยกข้อความและตัวเลข (เช่น "ค่าข้าว 60")
preg_match('/(\d+(\.\d+)?)/', $text, $matches);
$amount = isset($matches[1]) ? $matches[1] : null;
$item_name = trim(str_replace($amount, '', $text));

if ($amount) {
    // เชื่อมต่อ Database ของ Railway
    $conn = new mysqli(
        getenv('MYSQLHOST'),
        getenv('MYSQLUSER'),
        getenv('MYSQLPASSWORD'),
        getenv('MYSQLDATABASE'),
        getenv('MYSQLPORT')
    );

    if ($conn->connect_error) {
        $reply = "❌ เชื่อมต่อฐานข้อมูลล้มเหลว";
    } else {
        $stmt = $conn->prepare("INSERT INTO finance_logs (user_id, item_name, amount, type) VALUES (?, ?, ?, 'expense')");
        $stmt->bind_param("isd", $chat_id, $item_name, $amount);
        
        if ($stmt->execute()) {
            $reply = "✅ บันทึก: $item_name\n💰 ยอด: " . number_format($amount, 2) . " บาท";
        } else {
            $reply = "❌ บันทึกไม่ได้: " . $conn->error;
        }
        $conn->close();
    }
} else {
    $reply = "💡 พิมพ์รายการตามด้วยตัวเลข เช่น 'ส้มตำ 80'";
}

file_get_contents($api_url . "/sendMessage?chat_id=$chat_id&text=" . urlencode($reply));
