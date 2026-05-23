<?php
/**
 * Prescription Order API
 * Actions: place, cancel (patient) | approve, reject, dispatch, deliver (doctor)
 */
require_once __DIR__ . '/../config/config.php';
requireRole(['patient','doctor','pharmacy']);
header('Content-Type: application/json');

$uid  = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? $_GET['action'] ?? '';

// ── Auto-create table ────────────────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS prescription_orders (
    id                INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id   INT NOT NULL,
    patient_id        INT NOT NULL,
    doctor_id         INT NOT NULL,
    status            ENUM('pending','approved','preparing','dispatched','delivered','rejected','cancelled') DEFAULT 'pending',
    delivery_method   ENUM('collection','delivery') DEFAULT 'collection',
    delivery_address  TEXT,
    pharmacy_name     VARCHAR(150),
    patient_notes     TEXT,
    doctor_notes      TEXT,
    estimated_ready   DATETIME NULL,
    ordered_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
)");

// ── PATIENT: Place order ─────────────────────────────────────────────
if ($action === 'place' && $role === 'patient') {
    $rxId            = (int)($body['prescription_id'] ?? 0);
    $method          = in_array($body['delivery_method'] ?? '', ['collection','delivery']) ? $body['delivery_method'] : 'collection';
    $address         = trim($body['delivery_address'] ?? '');
    $notes           = trim($body['patient_notes'] ?? '');
    $paymentIntentId = trim($body['payment_intent_id'] ?? '');

    if (!$paymentIntentId) { echo json_encode(['success'=>false,'error'=>'Payment required to place order']); exit; }

    // Verify prescription belongs to patient
    $rx = $pdo->prepare("SELECT * FROM prescriptions WHERE id=? AND patient_id=? AND is_active=1");
    $rx->execute([$rxId, $uid]);
    $rx = $rx->fetch();
    if (!$rx) { echo json_encode(['success'=>false,'error'=>'Prescription not found or inactive']); exit; }

    // Check no pending order already exists
    $existing = $pdo->prepare("SELECT id FROM prescription_orders WHERE prescription_id=? AND patient_id=? AND status IN ('pending','approved','preparing','dispatched')");
    $existing->execute([$rxId, $uid]);
    if ($existing->fetch()) { echo json_encode(['success'=>false,'error'=>'You already have an active order for this prescription']); exit; }

    // Verify payment with Stripe
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../config/stripe.php';
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    try {
        $pi = \Stripe\PaymentIntent::retrieve($paymentIntentId);
        if ($pi->status !== 'succeeded') { echo json_encode(['success'=>false,'error'=>'Payment not completed']); exit; }
        // Record payment
        $pdo->exec("CREATE TABLE IF NOT EXISTS payments (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, payment_type VARCHAR(20) NOT NULL, stripe_payment_intent_id VARCHAR(100) UNIQUE NOT NULL, amount INT NOT NULL, currency VARCHAR(3) DEFAULT 'gbp', status VARCHAR(30) DEFAULT 'succeeded', description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->prepare("INSERT IGNORE INTO payments (user_id,payment_type,stripe_payment_intent_id,amount,currency,status,description) VALUES (?,?,?,?,?,?,?)")
            ->execute([$uid,'prescription',$paymentIntentId,$pi->amount,$pi->currency,$pi->status,$pi->description]);
    } catch (\Exception $e) {
        echo json_encode(['success'=>false,'error'=>'Payment verification failed: '.$e->getMessage()]); exit;
    }

    // Add column if needed
    try { $pdo->exec('ALTER TABLE prescription_orders ADD COLUMN payment_intent_id VARCHAR(100) NULL'); } catch(\PDOException $e) {}

    $pdo->prepare("INSERT INTO prescription_orders (prescription_id,patient_id,doctor_id,delivery_method,delivery_address,patient_notes,payment_intent_id) VALUES (?,?,?,?,?,?,?)")
        ->execute([$rxId, $uid, $rx['doctor_id'], $method, $address, $notes, $paymentIntentId]);

    // Notify doctor
    $pdo->prepare("INSERT INTO notifications (user_id,title,message,notification_type) VALUES (?,?,?,'system')")
        ->execute([$rx['doctor_id'], 'Prescription Order Request', "A patient has requested an order for {$rx['medication_name']}."]);

    echo json_encode(['success'=>true,'message'=>'Prescription order placed successfully']);
    exit;
}

// ── PATIENT: Cancel order ────────────────────────────────────────────
if ($action === 'cancel' && $role === 'patient') {
    $orderId = (int)($body['order_id'] ?? 0);
    $row = $pdo->prepare("SELECT * FROM prescription_orders WHERE id=? AND patient_id=? AND status='pending'");
    $row->execute([$orderId, $uid]);
    if (!$row->fetch()) { echo json_encode(['success'=>false,'error'=>'Order not found or cannot be cancelled']); exit; }
    $pdo->prepare("UPDATE prescription_orders SET status='cancelled' WHERE id=?")->execute([$orderId]);
    echo json_encode(['success'=>true,'message'=>'Order cancelled']);
    exit;
}

// ── DOCTOR / PHARMACY: Update order status ───────────────────────────
if (in_array($action, ['approve','reject','preparing','dispatch','deliver']) && in_array($role, ['doctor','pharmacy'])) {
    $orderId    = (int)($body['order_id'] ?? 0);
    $doctorNote = trim($body['doctor_notes'] ?? '');
    $pharmacy   = trim($body['pharmacy_name'] ?? '');
    $estReady   = $body['estimated_ready'] ?? null;

    // Pharmacy can only do preparing/dispatch/deliver on approved orders
    if ($role === 'pharmacy' && in_array($action, ['approve','reject'])) {
        echo json_encode(['success'=>false,'error'=>'Medical team cannot approve or reject orders']); exit;
    }

    if ($role === 'doctor') {
        $row = $pdo->prepare("SELECT po.*, p.medication_name, u.first_name, u.last_name FROM prescription_orders po JOIN prescriptions p ON po.prescription_id=p.id JOIN users u ON po.patient_id=u.id WHERE po.id=? AND po.doctor_id=?");
        $row->execute([$orderId, $uid]);
    } else {
        $row = $pdo->prepare("SELECT po.*, p.medication_name, u.first_name, u.last_name FROM prescription_orders po JOIN prescriptions p ON po.prescription_id=p.id JOIN users u ON po.patient_id=u.id WHERE po.id=?");
        $row->execute([$orderId]);
    }
    $order = $row->fetch();
    if (!$order) { echo json_encode(['success'=>false,'error'=>'Order not found']); exit; }

    $statusMap = ['approve'=>'approved','reject'=>'rejected','preparing'=>'preparing','dispatch'=>'dispatched','deliver'=>'delivered'];
    $newStatus = $statusMap[$action];

    $pdo->prepare("UPDATE prescription_orders SET status=?, doctor_notes=?, pharmacy_name=COALESCE(NULLIF(?,''),pharmacy_name), estimated_ready=COALESCE(?,estimated_ready) WHERE id=?")
        ->execute([$newStatus, $doctorNote, $pharmacy, $estReady, $orderId]);

    // Notify patient
    $messages = [
        'approved'   => "Your prescription order for {$order['medication_name']} has been approved.",
        'rejected'   => "Your prescription order for {$order['medication_name']} was not approved. {$doctorNote}",
        'preparing'  => "Your prescription for {$order['medication_name']} is being prepared at the pharmacy.",
        'dispatched' => "Your prescription for {$order['medication_name']} has been dispatched.",
        'delivered'  => "Your prescription for {$order['medication_name']} has been marked as delivered/ready for collection.",
    ];
    $pdo->prepare("INSERT INTO notifications (user_id,title,message,notification_type) VALUES (?,?,?,'medication')")
        ->execute([$order['patient_id'], 'Prescription Update', $messages[$newStatus] ?? 'Your prescription order has been updated.']);

    echo json_encode(['success'=>true,'message'=>"Order marked as {$newStatus}"]);
    exit;
}

echo json_encode(['success'=>false,'error'=>'Invalid action']);
