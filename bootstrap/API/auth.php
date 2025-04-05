<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/jwt.php';

// 🔐 تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // البحث عن المستخدم
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // التحقق من كلمة المرور
    if ($user && password_verify($password, $user['password'])) {
        // إنشاء التوكنات
        $accessToken = JWT::encode([
            'user_id' => $user['id'],
            'exp' => time() + 3600 // 1 ساعة
        ]);
        
        $refreshToken = bin2hex(random_bytes(32));
        $hashedRefreshToken = hash('sha256', $refreshToken);
        
        // حفظ الـ Refresh Token في قاعدة البيانات
        $stmt = $pdo->prepare("INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([
            $user['id'],
            $hashedRefreshToken,
            date('Y-m-d H:i:s', time() + 86400 * 7) // 7 أيام
        ]);
        
        echo json_encode([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'user_type' => $user['user_type']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'بيانات الدخول غير صحيحة']);
    }
}