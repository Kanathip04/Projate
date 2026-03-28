<?php
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
    exit;
}

// Auto-create tables
$conn->query("CREATE TABLE IF NOT EXISTS chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_name VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  role VARCHAR(20) DEFAULT 'user',
  message TEXT NOT NULL,
  reactions JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS chat_online (
  user_id INT PRIMARY KEY,
  user_name VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  role VARCHAR(20) DEFAULT 'user',
  last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS chat_typing (
  user_id INT PRIMARY KEY,
  user_name VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$uid   = (int)$_SESSION['user_id'];
$uname = $_SESSION['user_name'] ?? 'User';
$role  = $_SESSION['user_role'] ?? 'user';

// Fetch avatar from DB
$avatarRow = $conn->query("SELECT avatar FROM users WHERE id=$uid LIMIT 1")->fetch_assoc();
$avatar = $avatarRow['avatar'] ?? '';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

// ── Heartbeat / online ──────────────────────────────────────
if ($action === 'heartbeat') {
    $avEsc = $conn->real_escape_string($avatar);
    $unEsc = $conn->real_escape_string($uname);
    $roEsc = $conn->real_escape_string($role);
    $conn->query("INSERT INTO chat_online (user_id,user_name,avatar,role,last_seen)
                  VALUES ($uid,'$unEsc','$avEsc','$roEsc',NOW())
                  ON DUPLICATE KEY UPDATE user_name='$unEsc',avatar='$avEsc',role='$roEsc',last_seen=NOW()");
    echo json_encode(['ok' => true]);
    exit;
}

// ── Fetch messages ──────────────────────────────────────────
if ($action === 'fetch') {
    $since = isset($_GET['since']) ? (int)$_GET['since'] : 0;

    // Update online
    $avEsc = $conn->real_escape_string($avatar);
    $unEsc = $conn->real_escape_string($uname);
    $roEsc = $conn->real_escape_string($role);
    $conn->query("INSERT INTO chat_online (user_id,user_name,avatar,role,last_seen)
                  VALUES ($uid,'$unEsc','$avEsc','$roEsc',NOW())
                  ON DUPLICATE KEY UPDATE user_name='$unEsc',avatar='$avEsc',role='$roEsc',last_seen=NOW()");

    // Messages
    $res = $conn->query("SELECT id,user_id,user_name,avatar,role,message,reactions,
                         UNIX_TIMESTAMP(created_at) AS ts
                         FROM chat_messages
                         WHERE id > $since
                         ORDER BY id ASC LIMIT 60");
    $msgs = [];
    while ($r = $res->fetch_assoc()) {
        $r['reactions'] = $r['reactions'] ? json_decode($r['reactions'], true) : (object)[];
        $r['is_me']     = ((int)$r['user_id'] === $uid);
        $msgs[] = $r;
    }

    // Online users (last 20s)
    $online = [];
    $res2 = $conn->query("SELECT user_id,user_name,avatar,role FROM chat_online WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 20 SECOND)");
    while ($r = $res2->fetch_assoc()) $online[] = $r;

    // Typing (last 4s, not self)
    $typing = [];
    $res3 = $conn->query("SELECT user_name FROM chat_typing WHERE user_id != $uid AND updated_at >= DATE_SUB(NOW(), INTERVAL 4 SECOND)");
    while ($r = $res3->fetch_row()) $typing[] = $r[0];

    // Latest id
    $latestRow = $conn->query("SELECT MAX(id) AS mx FROM chat_messages")->fetch_assoc();
    $latest = (int)($latestRow['mx'] ?? 0);

    echo json_encode(['messages' => $msgs, 'online' => $online, 'typing' => $typing, 'latest' => $latest]);
    exit;
}

// ── Send message ────────────────────────────────────────────
if ($action === 'send') {
    $msg = trim($_POST['message'] ?? '');
    if ($msg === '' || mb_strlen($msg) > 500) {
        echo json_encode(['error' => 'invalid']); exit;
    }
    $msgEsc = $conn->real_escape_string($msg);
    $avEsc  = $conn->real_escape_string($avatar);
    $unEsc  = $conn->real_escape_string($uname);
    $roEsc  = $conn->real_escape_string($role);
    $conn->query("INSERT INTO chat_messages (user_id,user_name,avatar,role,message)
                  VALUES ($uid,'$unEsc','$avEsc','$roEsc','$msgEsc')");
    $newId = (int)$conn->insert_id;
    // Clear typing
    $conn->query("DELETE FROM chat_typing WHERE user_id=$uid");
    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

// ── Reaction ────────────────────────────────────────────────
if ($action === 'react') {
    $msgId = (int)($_POST['msg_id'] ?? 0);
    $emoji = trim($_POST['emoji'] ?? '');
    $allowed = ['👍','❤️','😂','😮','😢','🔥'];
    if (!$msgId || !in_array($emoji, $allowed)) {
        echo json_encode(['error' => 'invalid']); exit;
    }
    $row = $conn->query("SELECT reactions FROM chat_messages WHERE id=$msgId")->fetch_assoc();
    $reactions = $row && $row['reactions'] ? json_decode($row['reactions'], true) : [];
    if (!isset($reactions[$emoji])) $reactions[$emoji] = [];
    $key = array_search($uid, $reactions[$emoji]);
    if ($key !== false) {
        array_splice($reactions[$emoji], $key, 1);
        if (empty($reactions[$emoji])) unset($reactions[$emoji]);
    } else {
        $reactions[$emoji][] = $uid;
    }
    $jsonReact = $conn->real_escape_string(json_encode($reactions));
    $conn->query("UPDATE chat_messages SET reactions='$jsonReact' WHERE id=$msgId");
    echo json_encode(['ok' => true, 'reactions' => $reactions]);
    exit;
}

// ── Typing ──────────────────────────────────────────────────
if ($action === 'typing') {
    $unEsc = $conn->real_escape_string($uname);
    $conn->query("INSERT INTO chat_typing (user_id,user_name,updated_at)
                  VALUES ($uid,'$unEsc',NOW())
                  ON DUPLICATE KEY UPDATE user_name='$unEsc',updated_at=NOW()");
    echo json_encode(['ok' => true]);
    exit;
}

// ── Delete (own message or admin) ───────────────────────────
if ($action === 'delete') {
    $msgId = (int)($_POST['msg_id'] ?? 0);
    if (!$msgId) { echo json_encode(['error' => 'invalid']); exit; }
    if ($role === 'admin') {
        $conn->query("DELETE FROM chat_messages WHERE id=$msgId");
    } else {
        $conn->query("DELETE FROM chat_messages WHERE id=$msgId AND user_id=$uid");
    }
    echo json_encode(['ok' => true, 'deleted' => (bool)$conn->affected_rows]);
    exit;
}

// ── View profile ────────────────────────────────────────────
if ($action === 'profile') {
    $targetId = (int)($_GET['user_id'] ?? 0);
    if (!$targetId) { echo json_encode(['error' => 'invalid']); exit; }
    $row = $conn->query("SELECT id, fullname, avatar, role, bio, created_at FROM users WHERE id=$targetId LIMIT 1")->fetch_assoc();
    if (!$row) { echo json_encode(['error' => 'not_found']); exit; }
    echo json_encode(['ok' => true, 'user' => $row]);
    exit;
}

echo json_encode(['error' => 'unknown_action']);
