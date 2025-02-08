# 🤖 Telegram Whisper Bot

🚀 **Telegram Whisper Bot** is a Telegram bot that allows users to send secret whispers between each other within groups or via inline mode. The whispers are stored securely in a database and can only be viewed by the intended recipient.

---

## 📌 **Features**
✅ Send secret whispers to users in groups or via inline queries
✅ Store encrypted messages in the database
✅ Display the whisper only to the correct recipient
✅ Support for both group messages and inline queries
✅ Uses MySQL for data storage
✅ Effective error logging and management

---

## 🛠 **Requirements**

- **PHP 7.4+** 🐘
- **MySQL Database** 🗄
- **Web Server (Apache/Nginx)** 🌐
- **Enabled Webhook on Telegram** 📡

---

## 🚀 **Installation & Setup**

### 1️⃣ **Clone the Repository**
```bash
 git clone https://github.com/a9ii/Hmoosa_Bot.git
 cd Hmoosa_Bot
```

### 2️⃣ **Configure Settings**
Edit `hmsa.php` or update the values directly in the code:
```php
$botToken = 'Your_BotToken';
$apiUrl   = "https://api.telegram.org/bot$botToken/";

$dbHost = 'Your_DB_Host';
$dbName = 'Your_DB_Name';
$dbUser = 'Your_DB_User';
$dbPass = 'Your_DB_Password';
```

### 3️⃣ **Create Database and Tables**
Run the following SQL commands to create the database and necessary tables:
```sql
CREATE DATABASE whisper_db;
USE whisper_db;

CREATE TABLE whispers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id BIGINT NOT NULL,
    sender_username VARCHAR(255),
    recipient_id BIGINT,
    recipient_username VARCHAR(255),
    group_id BIGINT,
    message TEXT NOT NULL,
    status ENUM('unread', 'read') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 4️⃣ **Set Up Webhook**
Activate the bot's Webhook by executing the following command:
```bash
curl -X POST "https://api.telegram.org/botYour_BotToken/setWebhook?url=https://yourdomain.com/webhook.php"
```

### 5️⃣ **Run the Bot**
Upload the code to your server and run it using Apache/Nginx.

---

## 📜 **Usage**

### 📩 **Send a Whisper in a Group**

### 🔍 **Use Inline Query**
🔹 Users can send whispers via inline query without mentioning the bot:
```
@hmoosa_bot message @username
```

### 🔓 **View the Whisper**
✅ Only the intended recipient can view the whisper by clicking the **"View Whisper"** button.

---

## 🛠 **Debugging Issues**
- Ensure that the **bot token** is correctly entered.
- Verify **database credentials** are correct.
- Check the **Webhook status** using:
```bash
curl -X GET "https://api.telegram.org/botYour_BotToken/getWebhookInfo"
```

---

## 📞 **Support & Contact**
💡 If you encounter any issues, feel free to open an **Issue** on GitHub .



---

🚀 **Enjoy using Telegram Whisper Bot! 🎉**

