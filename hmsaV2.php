<?php

// إظهار الأخطاء لأغراض التطوير (يمكن إلغاؤه في الإنتاج)
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

// =============== إعدادات تليجرام ===============
$botToken = 'YOUR_TELEGRAM_BOT_TOKEN'; // ضع هنا توكن البوت
$apiUrl   = "https://api.telegram.org/bot$botToken/";

// =============== إعدادات Supabase ===============
// عنوان المشروع (Project URL) - الموجود ضمن معلوماتك في Supabase
$SUPABASE_URL = 'https://qmfewiavybhlppuircud.supabase.co';

// المفتاح (Key) - يمكنك استخدام الـ service_role أو anon public حسب إعدادك لـ RLS
$SUPABASE_KEY = 'eyJhbGci...'; // ضع هنا المفتاح المناسب

// اسم الجدول الذي سنحفظ فيه الهمسات
$TABLE_NAME = 'whispers';

// ============================================================================
//           دوال مساعدة للتعامل مع Supabase عبر REST API
// ============================================================================
function supabaseTableEndpoint($tableName) {
    global $SUPABASE_URL;
    return $SUPABASE_URL . "/rest/v1/" . $tableName;
}

function supabaseInsert($data) {
    global $TABLE_NAME, $SUPABASE_KEY;
    
    $endpoint = supabaseTableEndpoint($TABLE_NAME);
    $headers = [
        "Content-Type: application/json",
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Prefer: return=representation"
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([$data]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("supabaseInsert() cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $json = json_decode($response, true);
        return $json[0] ?? false;
    } else {
        error_log("supabaseInsert() HTTP Code: $http_code - Response: $response");
        return false;
    }
}

function supabaseSelectById($whisper_id) {
    global $TABLE_NAME, $SUPABASE_KEY;
    
    $endpoint = supabaseTableEndpoint($TABLE_NAME) . "?id=eq.$whisper_id&select=*";
    
    $headers = [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY"
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("supabaseSelectById() cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $json = json_decode($response, true);
        return $json[0] ?? false;
    } else {
        error_log("supabaseSelectById() HTTP Code: $http_code - Response: $response");
        return false;
    }
}

function supabaseUpdateById($whisper_id, $data) {
    global $TABLE_NAME, $SUPABASE_KEY;
    
    $endpoint = supabaseTableEndpoint($TABLE_NAME) . "?id=eq.$whisper_id";
    
    $headers = [
        "Content-Type: application/json",
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Prefer: return=representation"
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("supabaseUpdateById() cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        return true;
    } else {
        error_log("supabaseUpdateById() HTTP Code: $http_code - Response: $response");
        return false;
    }
}

// ============================================================================
//           دوال مساعدة للتعامل مع تليجرام (إرسال/استقبال)
// ============================================================================
function sendTelegramRequest($method, $parameters = []) {
    global $apiUrl;
    $url = $apiUrl . $method;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
    
    $result = curl_exec($ch);
    if ($result === false) {
        error_log("خطأ في cURL مع تليجرام: " . curl_error($ch));
    }
    curl_close($ch);
    return $result;
}

// ============================================================================
//           تحليل الرسائل الواردة
// ============================================================================
function parseWhisper($text) {
    // ضع هنا اسم بوتك الفعلي إن كان مختلفاً عن "@hmoosa_bot"
    $pattern = '/^(?:@hmoosa_bot\s+)?(.+)\s+@(\S+)\s*$/u';
    if (preg_match($pattern, $text, $matches)) {
        return [
            'message'   => trim($matches[1]),
            'recipient' => trim($matches[2])
        ];
    }
    return false;
}

// ============================================================================
//           استقبال التحديثات من تليجرام (Webhook)
// ============================================================================
$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) {
    exit; // لا يوجد بيانات
}

// ============================================================================
//  1) الرسائل العادية (message)
// ============================================================================
if (isset($update['message'])) {

    $message         = $update['message'];
    $chat_id         = $message['chat']['id'];
    $text            = isset($message['text']) ? trim($message['text']) : '';
    $from            = $message['from'];
    $sender_id       = $from['id'];
    $sender_username = isset($from['username']) ? $from['username'] : '';

    // ========== التحقق من أوامر /start أو /help ==========
    if (strcasecmp($text, '/start') === 0) {
        $reply = "مرحباً بك!\n"
               . "أنا بوت الهمسات السرية، أساعدك في إرسال همسات خاصة لأي مستخدم دون أن يراها أحد.\n\n"
               . "اكتب /help لمزيد من التفاصيل حول طريقة الاستخدام.";
        
        sendTelegramRequest("sendMessage", [
            'chat_id' => $chat_id,
            'text'    => $reply
        ]);

        // نوقف هنا حتى لا يُكمل بقية المعالجات
        return;
    }
    elseif (strcasecmp($text, '/help') === 0) {
        $reply = "إليك طريقة استخدامي:\n\n"
               . "1) في أي مجموعة تضمّني فيها، أرسل رسالة بالشكل:\n"
               . "   <code>@bot_username نص_الهمسة @username_المستلم</code>\n"
               . "   وسأقوم بتحويلها إلى همسة لا يراها إلا المستلم عند الضغط عليها.\n\n"
               . "2) أو استخدم الوضع (Inline): اكتب في صندوق الكتابة\n"
               . "   <code>@bot_username نص_الهمسة @username_المستلم</code>\n"
               . "   وسيظهر زر جاهز للإرسال.\n\n"
               . "عند الضغط على زر الهمسة، لا يراها أحد إلا المرسل إليه.\n\n"
               . "استمتع بالتواصل الآمن!";
        
        sendTelegramRequest("sendMessage", [
            'chat_id'    => $chat_id,
            'text'       => $reply,
            'parse_mode' => 'HTML'
        ]);

        // نوقف هنا حتى لا يُكمل بقية المعالجات
        return;
    }

    // ========== بقية الرسائل العادية (محاولة معالجة الهمسة) ==========
    // نفحص إن كانت تبدأ بـ "@hmoosa_bot" مثلاً (عدّل إلى اسم بوتك)
    if (strpos($text, '@hmoosa_bot') === 0) {
        $parsed = parseWhisper($text);
        if ($parsed !== false) {
            $secret_message     = $parsed['message'];
            $recipient_username = $parsed['recipient'];
            $group_id           = $chat_id; // قد يكون في مجموعة أو خاص

            // نحفظ الهمسة في Supabase
            $insertData = [
                'sender_id'          => $sender_id,
                'sender_username'    => $sender_username,
                'recipient_id'       => null,
                'recipient_username' => $recipient_username,
                'group_id'           => $group_id,
                'message'            => $secret_message,
                'status'             => 'unread'
            ];
            
            $insertedRow = supabaseInsert($insertData);

            if ($insertedRow) {
                $reply_text = "✅ تم إرسال الهمسة بنجاح!";
            } else {
                $reply_text = "❌ حدث خطأ أثناء حفظ الهمسة في قاعدة البيانات.";
            }
            sendTelegramRequest("sendMessage", [
                'chat_id' => $chat_id,
                'text'    => $reply_text,
            ]);
        } else {
            $reply_text = "❌ صيغة الهمسة غير صحيحة.\n"
                        . "الصيغة الصحيحة:\n"
                        . "@bot_username نص_الهمسة @username_المستلم";
            sendTelegramRequest("sendMessage", [
                'chat_id' => $chat_id,
                'text'    => $reply_text,
            ]);
        }
    }
}
// ============================================================================
//  2) استعلامات الـ Inline (inline_query)
// ============================================================================
elseif (isset($update['inline_query'])) {

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
        
        // إدخال الهمسة في Supabase
        $insertData = [
            'sender_id'          => $sender_id,
            'sender_username'    => $sender_username,
            'recipient_id'       => null,
            'recipient_username' => $recipient_username,
            'group_id'           => null,
            'message'            => $secret_message,
            'status'             => 'unread'
        ];
        $insertedRow = supabaseInsert($insertData);
        
        if ($insertedRow) {
            $whisper_id = $insertedRow['id'] ?? null;
            
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
            
            // إعداد نص الرسالة في نتائج الـ Inline
            $message_text = "🔒 هذه همسة سرية إلى @$recipient_username";
            
            $result = [
                'type' => 'article',
                'id'   => bin2hex(random_bytes(5)),
                'title'=> 'همسة صحيحة ✅',
                'input_message_content' => [
                    'message_text' => $message_text
                ],
                'reply_markup' => $inline_keyboard,
                'description' => "اضغط للإرسال همسة سرية إلى @$recipient_username"
            ];
            $results = [$result];
            
            $parameters = [
                'inline_query_id' => $query_id,
                'results'         => json_encode($results),
                'cache_time'      => 0
            ];
            sendTelegramRequest("answerInlineQuery", $parameters);
        } else {
            // فشل الإدخال في Supabase
            $errorResult = [
                'type' => 'article',
                'id'   => bin2hex(random_bytes(5)),
                'title'=> 'خطأ في الإدخال ⚠️',
                'input_message_content' => [
                    'message_text' => "❌ فشل في تخزين الهمسة في قاعدة البيانات."
                ],
                'description' => "لا يمكن تخزين الهمسة في الوقت الحالي."
            ];
            $results = [$errorResult];
            $parameters = [
                'inline_query_id' => $query_id,
                'results'         => json_encode($results),
                'cache_time'      => 0
            ];
            sendTelegramRequest("answerInlineQuery", $parameters);
        }
        
    } else {
        // الصيغة غير صحيحة
        $result = [
            'type' => 'article',
            'id'   => bin2hex(random_bytes(5)),
            'title'=> 'خطأ في الصيغة ⚠️',
            'input_message_content' => [
                'message_text' => "❌ صيغة الهمسة غير صحيحة.\nالصيغة الصحيحة:\n@bot_username نص_الهمسة @username_المستلم"
            ],
            'description' => "❌ الصيغة: @bot_username نص_الهمسة @username_المستلم"
        ];
        $results = [$result];
        $parameters = [
            'inline_query_id' => $query_id,
            'results'         => json_encode($results),
            'cache_time'      => 0
        ];
        sendTelegramRequest("answerInlineQuery", $parameters);
    }
}
// ============================================================================
//  3) رد أزرار الـ Inline (callback_query)
// ============================================================================
elseif (isset($update['callback_query'])) {

    $callback_query = $update['callback_query'];
    $callback_data  = $callback_query['data'];

    // نتوقع صيغة مثل: show_whisper:123
    if (strpos($callback_data, 'show_whisper:') === 0) {
        $whisper_id = str_replace('show_whisper:', '', $callback_data);
        
        // جلب محتوى الهمسة من Supabase
        $whisper = supabaseSelectById($whisper_id);
        if ($whisper) {
            $recipient_username = $whisper['recipient_username'];
            $whisper_message    = $whisper['message'];
            
            // التحقق من أن المستخدم الذي ضغط هو المستلم
            $caller_username = isset($callback_query['from']['username']) 
                               ? strtolower($callback_query['from']['username']) 
                               : '';
            if ($caller_username !== strtolower($recipient_username)) {
                sendTelegramRequest("answerCallbackQuery", [
                    'callback_query_id' => $callback_query['id'],
                    'text'              => "❌ ليس لديك صلاحية لعرض هذه الهمسة.",
                    'show_alert'        => true
                ]);
                return;
            }
            
            // تحديث حالة الهمسة إلى 'read'
            supabaseUpdateById($whisper_id, ['status' => 'read']);
            
            // إرسال محتوى الهمسة بشكل Alert
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
