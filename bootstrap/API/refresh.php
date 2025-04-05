<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/jwt.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $refreshToken = $_POST['refresh_token'];
    $hashedToken = hash('sha256', $refreshToken);
    
    // البحث عن التوكن
    $stmt = $pdo->prepare("SELECT * FROM refresh_tokens WHERE token_hash = ? AND expires_at > NOW()");
    $stmt->execute([$hashedToken]);
    $tokenData = $stmt->fetch();
    
    if ($tokenData) {
        // إنشاء access token جديد
        $newAccessToken = JWT::encode([
            'user_id' => $tokenData['user_id'],
            'exp' => time() + 3600
        ]);
        
        echo json_encode(['access_token' => $newAccessToken]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Refresh Token غير صالح']);
    }
}