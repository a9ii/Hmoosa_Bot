<?php


// ضبط إعدادات التقرير عن الأخطاء وتحديد المنطقة الزمنية
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

// إعدادات التوكن وواجهة API الخاصة بتليجرام
$botToken = 'Your_BotToken';
$apiUrl   = "https://api.telegram.org/bot$botToken/";

// إعدادات قاعدة البيانات
$dbHost = 'Your_DB_Host';
$dbName = 'Your_DB_Name';
$dbUser = 'Your_DB_User';
$dbPass = 'Your_DB_Password';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
    exit;
}

// دالة لإرسال الطلبات باستخدام cURL إلى API تليجرام
function sendTelegramRequest($method, $parameters = []) {
    global $apiUrl;
    $url = $apiUrl . $method;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // إرسال البيانات عبر POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
    
    $result = curl_exec($ch);
    if ($result === false) {
        error_log("خطأ في cURL: " . curl_error($ch));
    }
    curl_close($ch);
    
    return $result;
}

// استقبال التحديثات من تليجرام (Webhook)
$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) {
    exit;
}

// دالة لتحليل النص واستخلاص محتوى الهمسة واسم المستخدم للمستلم
// الصيغة المطلوبة: "@hmoosa_bot الرسالة @username"
// ويمكن أن تُكتب بدون ذكر معرف البوت في وضع الـ inline.
function parseWhisper($text) {
    $pattern = '/^(?:@hmoosa_bot\s+)?(.+)\s+@(\S+)\s*$/u';
    if (preg_match($pattern, $text, $matches)) {
        return [
            'message'   => trim($matches[1]),
            'recipient' => trim($matches[2])
        ];
    }
    return false;
}

// معالجة التحديثات الواردة
if (isset($update['message'])) {
    // معالجة الرسائل العادية (في الخاص أو المجموعات)
    $message    = $update['message'];
    $chat_id    = $message['chat']['id'];
    $text       = isset($message['text']) ? trim($message['text']) : '';
    $from       = $message['from'];
    $sender_id  = $from['id'];
    $sender_username = isset($from['username']) ? $from['username'] : '';
    
    // التحقق من أن الرسالة تبدأ بـ "@hmoosa_bot"
    if (strpos($text, '@hmoosa_bot') === 0) {
        $parsed = parseWhisper($text);
        if ($parsed !== false) {
            $secret_message     = $parsed['message'];
            $recipient_username = $parsed['recipient'];
            // في حالة الرسائل داخل المجموعات يتم استخدام معرف المجموعة
            $group_id = $message['chat']['id'];
            
            // تخزين الهمسة في قاعدة البيانات
            // هنا نعتمد فقط على recipient_username، ويتم تخزين recipient_id كـ NULL.
            $stmt = $pdo->prepare("INSERT INTO whispers 
                (sender_id, sender_username, recipient_id, recipient_username, group_id, message) 
                VALUES (:sender_id, :sender_username, :recipient_id, :recipient_username, :group_id, :message)");
            $stmt->execute([
                ':sender_id'         => $sender_id,
                ':sender_username'   => $sender_username,
                ':recipient_id'      => null,
                ':recipient_username'=> $recipient_username,
                ':group_id'          => $group_id,
                ':message'           => $secret_message,
            ]);
            
            $reply_text = "✅ تم إرسال الهمسة بنجاح!";
            sendTelegramRequest("sendMessage", [
                'chat_id' => $chat_id,
                'text'    => $reply_text,
            ]);
        } else {
            $reply_text = "❌ صيغة الهمسة غير صحيحة.\nالصيغة الصحيحة:\n@hmoosa_bot الرسالة @username";
            sendTelegramRequest("sendMessage", [
                'chat_id' => $chat_id,
                'text'    => $reply_text,
            ]);
        }
    }
} elseif (isset($update['inline_query'])) {
    // معالجة استعلامات الـ inline
    $inline_query    = $update['inline_query'];
    $query_id        = $inline_query['id'];
    $query_text      = isset($inline_query['query']) ? trim($inline_query['query']) : '';
    $from            = $inline_query['from'];
    $sender_id       = $from['id'];
    $sender_username = isset($from['username']) ? $from['username'] : '';
    
    $parsed = parseWhisper($query_text);
    if ($parsed !== false) {
        $secret_message     = $parsed['message'];
        $recipient_username = $parsed['recipient'];
        $group_id = null;
        
        // تخزين الهمسة في قاعدة البيانات مع الاعتماد فقط على recipient_username
        $stmt = $pdo->prepare("INSERT INTO whispers 
            (sender_id, sender_username, recipient_id, recipient_username, group_id, message) 
            VALUES (:sender_id, :sender_username, :recipient_id, :recipient_username, :group_id, :message)");
        $stmt->execute([
            ':sender_id'         => $sender_id,
            ':sender_username'   => $sender_username,
            ':recipient_id'      => null,
            ':recipient_username'=> $recipient_username,
            ':group_id'          => $group_id,
            ':message'           => $secret_message,
        ]);
        $whisper_id = $pdo->lastInsertId();
        
        // إعداد زر inline keyboard لإظهار محتوى الهمسة عند الضغط عليه
        $inline_keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => 'عرض الهمسة | 🔓',
                        'callback_data' => 'show_whisper:' . $whisper_id
                    ]
                ]
            ]
        ];
        
        // إعداد نص رسالة الـ inline بالنص المطلوب
        $message_text = "🔒 | هذه همسة سرية الى @$recipient_username ";
        
        $result = [
            'type' => 'article',
            'id'   => bin2hex(random_bytes(5)),
            'title'=> 'همسة صحيحة ✅',
            'input_message_content' => [
                'message_text' => $message_text
            ],
            'reply_markup' => $inline_keyboard,
            'description' => "اضغط هنا لارسال الهمسة إلى @$recipient_username"
        ];
        $results = [$result];
        
        $parameters = [
            'inline_query_id' => $query_id,
            'results'         => json_encode($results),
            'cache_time'      => 0
        ];
        sendTelegramRequest("answerInlineQuery", $parameters);
    } else {
        // إذا كانت الصيغة غير صحيحة، إرسال نتيجة inline توضح الخطأ
        $result = [
            'type' => 'article',
            'id'   => bin2hex(random_bytes(5)),
            'title'=> 'خطأ في الصيغة ⚠️',
            'input_message_content' => [
                'message_text' => "❌ صيغة الهمسة غير صحيحة.\nالصيغة الصحيحة:\n@hmoosa_bot الرسالة @username"
            ],
            'description' => "❌ صيغة الهمسة غير صحيحة.\nالصيغة الصحيحة:\n@hmoosa_bot الرسالة @username"
        ];
        $results = [$result];
        $parameters = [
            'inline_query_id' => $query_id,
            'results'         => json_encode($results),
            'cache_time'      => 0
        ];
        sendTelegramRequest("answerInlineQuery", $parameters);
    }
} elseif (isset($update['callback_query'])) {
    // معالجة استجابة زر الـ inline keyboard
    $callback_query = $update['callback_query'];
    $callback_data  = $callback_query['data'];
    
    if (strpos($callback_data, 'show_whisper:') === 0) {
        $whisper_id = str_replace('show_whisper:', '', $callback_data);
        
        // استرجاع محتوى الهمسة واسم المستخدم للمستلم من قاعدة البيانات
        $stmt = $pdo->prepare("SELECT message, recipient_username FROM whispers WHERE id = :id");
        $stmt->execute([':id' => $whisper_id]);
        $whisper = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($whisper) {
            // التحقق من أن المستخدم الذي ضغط على الزر هو المستلم الصحيح (اعتمادًا على الـ username)
            if (!isset($callback_query['from']['username']) || strtolower($callback_query['from']['username']) !== strtolower($whisper['recipient_username'])) {
                sendTelegramRequest("answerCallbackQuery", [
                    'callback_query_id' => $callback_query['id'],
                    'text'              => "❌ ليس لديك صلاحية لعرض هذه الهمسة.",
                    'show_alert'        => true
                ]);
                exit;
            }
            
            // تحديث حالة الهمسة إلى 'read'
            $stmtUpdate = $pdo->prepare("UPDATE whispers SET status = 'read' WHERE id = :id");
            $stmtUpdate->execute([':id' => $whisper_id]);
            
            $whisper_message = $whisper['message'];
            // إرسال تنبيه للمستخدم يحتوي على محتوى الهمسة
            sendTelegramRequest("answerCallbackQuery", [
                'callback_query_id' => $callback_query['id'],
                'text'              => $whisper_message,
                'show_alert'        => true
            ]);
        } else {
            sendTelegramRequest("answerCallbackQuery", [
                'callback_query_id' => $callback_query['id'],
                'text'              => "❌ لم يتم العثور على الهمسة.",
                'show_alert'        => true
            ]);
        }
    }
}
?>
