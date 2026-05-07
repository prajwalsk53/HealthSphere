<?php
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/../../includes/mailer.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // POST /api/v1/auth.php?action=login
    case 'login': {
        $b = body();
        $email    = trim($b['email']    ?? '');
        $password = $b['password']      ?? '';
        if (!$email || !$password) err('Email and password required');

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) err('Invalid credentials', 401);
        if (($user['approval_status'] ?? 'approved') === 'pending')
            err('Account pending admin approval. You will be notified by email.', 403);
        if (($user['approval_status'] ?? 'approved') === 'rejected')
            err('Account application was not approved. Contact admin@healthsphere.nhs.uk', 403);
        if (!$user['is_active']) err('Account suspended. Contact support.', 403);
        if (!password_verify($password, $user['password'])) err('Invalid credentials', 401);

        $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);

        $token = jwtCreate((int)$user['id'], $user['role']);
        ok([
            'token'      => $token,
            'expires_in' => JWT_EXPIRE,
            'user'       => [
                'id'         => (int)$user['id'],
                'nhs_id'     => $user['nhs_id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'blood_type' => $user['blood_type'],
                'phone'      => $user['phone'],
            ],
        ]);
        break;
    }

    // POST /api/v1/auth.php?action=register
    case 'register': {
        $b    = body();
        $role = $b['role'] ?? 'patient';
        $first= trim($b['first_name'] ?? '');
        $last = trim($b['last_name']  ?? '');
        $email= trim($b['email']      ?? '');
        $pass = $b['password']        ?? '';

        if (!$first||!$last||!$email||!$pass) err('All fields required');
        if (strlen($pass) < 8) err('Password must be at least 8 characters');

        $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $check->execute([$email]);
        if ($check->fetch()) err('Email already registered', 409);

        $nhsId  = strtoupper(substr($role,0,2)).strtoupper(substr($first,0,2)).rand(100000,999999);
        $hash   = password_hash($pass, PASSWORD_DEFAULT);
        $active = $role==='patient' ? 1 : 0;
        $status = $role==='patient' ? 'approved' : 'pending';

        $stmt = $pdo->prepare("INSERT INTO users (nhs_id,first_name,last_name,email,password,role,phone,date_of_birth,gender,blood_type,is_active,approval_status,applied_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([$nhsId,$first,$last,$email,$hash,$role,$b['phone']??null,$b['dob']??null,$b['gender']??null,$b['blood_type']??null,$active,$status]);
        $newId = (int)$pdo->lastInsertId();

        if ($role==='doctor') {
            $pdo->prepare("INSERT INTO doctors (user_id,hcpc_number,specialization,hospital_name,experience_years,consultation_fee,bio,is_verified) VALUES (?,?,?,?,?,?,?,0)")
                ->execute([$newId,$b['hcpc_number']??'',$b['specialization']??'General Practice',$b['hospital_name']??'',$b['experience_years']??0,$b['consultation_fee']??0,$b['bio']??'']);
        }

        // Send emails
        if ($role==='patient') {
            @mailPatientWelcome($email,"$first $last",$nhsId);
        } else {
            @mailApplicationReceived($email,"$first $last",$role,$nhsId);
            @mailAdminNewApplication("$first $last",$email,$role,$nhsId);
        }

        ok(['message'=>$role==='patient'?'Account created! You can sign in now.':'Application submitted! Pending admin approval.','nhs_id'=>$nhsId,'status'=>$status], 201);
        break;
    }

    // GET /api/v1/auth.php?action=me
    case 'me': {
        $user = requireAuth();
        // Doctor extra info
        $doc = null;
        if ($user['role']==='doctor') {
            $d = $pdo->prepare("SELECT * FROM doctors WHERE user_id=?"); $d->execute([$user['id']]); $doc=$d->fetch();
        }
        ok(array_merge($user, ['doctor_profile'=>$doc]));
        break;
    }

    // POST /api/v1/auth.php?action=refresh
    case 'refresh': {
        $user = requireAuth();
        $token = jwtCreate((int)$user['id'], $user['role']);
        ok(['token'=>$token,'expires_in'=>JWT_EXPIRE]);
        break;
    }

    default: err('Unknown action. Use: login | register | me | refresh');
}
