<?php
require_once __DIR__ . '/core.php';
$user   = requireAuth();
$uid    = (int)$user['id'];
$action = $_GET['action'] ?? 'get';

switch ($action) {

    case 'get': {
        $stmt = $pdo->prepare("SELECT id,nhs_id,first_name,last_name,email,phone,date_of_birth,gender,blood_type,role,is_active,created_at FROM users WHERE id=?");
        $stmt->execute([$uid]);
        $profile = $stmt->fetch();
        if ($user['role'] === 'doctor') {
            $d = $pdo->prepare("SELECT * FROM doctors WHERE user_id=?");
            $d->execute([$uid]);
            $profile['doctor_profile'] = $d->fetch() ?: null;
        }
        // Allergies count
        $profile['allergy_count'] = (int)$pdo->query("SELECT COUNT(*) FROM allergies WHERE patient_id=$uid")->fetchColumn();
        ok($profile);
        break;
    }

    case 'update': {
        $b = body();
        $allowed = ['first_name','last_name','phone','gender','blood_type','date_of_birth'];
        $sets = []; $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $b)) {
                $sets[] = "$col=?";
                $params[] = $b[$col];
            }
        }
        if (empty($sets)) err('No valid fields to update');
        $params[] = $uid;
        $pdo->prepare("UPDATE users SET ".implode(',',$sets)." WHERE id=?")->execute($params);
        ok(['message' => 'Profile updated']);
        break;
    }

    case 'change_password': {
        $b = body();
        $old = $b['old_password'] ?? '';
        $new = $b['new_password'] ?? '';
        if (!$old || !$new) err('old_password and new_password required');
        if (strlen($new) < 8) err('Password must be at least 8 characters');

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!password_verify($old, $row['password'])) err('Current password incorrect', 401);

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
        ok(['message' => 'Password changed successfully']);
        break;
    }

    case 'allergies': {
        $stmt = $pdo->prepare("SELECT * FROM allergies WHERE patient_id=? ORDER BY created_at DESC");
        $stmt->execute([$uid]);
        ok($stmt->fetchAll());
        break;
    }

    case 'prescriptions': {
        $active = $_GET['active'] ?? null;
        $where = "p.patient_id=?";
        if ($active !== null) $where .= " AND p.is_active=".($active ? 1 : 0);
        $stmt = $pdo->prepare("SELECT p.*, u.first_name AS doctor_first, u.last_name AS doctor_last FROM prescriptions p JOIN users u ON p.doctor_id=u.id WHERE $where ORDER BY p.created_at DESC LIMIT 20");
        $stmt->execute([$uid]);
        ok($stmt->fetchAll());
        break;
    }

    default: err('Unknown action. Use: get | update | change_password | allergies | prescriptions');
}
