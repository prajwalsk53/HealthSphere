<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../vendor/autoload.php';

requireRole('patient');
header('Content-Type: application/json');

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$uid  = (int)$_SESSION['user_id'];
$type = $_GET['type'] ?? 'appointment';

// Ensure payments table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    payment_type VARCHAR(20) NOT NULL,
    stripe_payment_intent_id VARCHAR(100) UNIQUE NOT NULL,
    amount INT NOT NULL,
    currency VARCHAR(3) DEFAULT 'gbp',
    status VARCHAR(30) DEFAULT 'succeeded',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if ($type === 'appointment') {
    $doctorId = (int)($_GET['doctor_id'] ?? 0);
    if (!$doctorId) { echo json_encode(['error' => 'Doctor ID required']); exit; }

    $doc = $pdo->prepare("
        SELECT d.consultation_fee, u.first_name, u.last_name
        FROM doctors d JOIN users u ON d.user_id=u.id
        WHERE u.id=? AND u.is_active=1 AND d.is_verified=1
    ");
    $doc->execute([$doctorId]);
    $doc = $doc->fetch();
    if (!$doc) { echo json_encode(['error' => 'Doctor not found']); exit; }

    $amount      = max(100, (int)round(($doc['consultation_fee'] ?? 50) * 100)); // min £1
    $description = "Consultation with Dr. {$doc['first_name']} {$doc['last_name']}";

} elseif ($type === 'prescription') {
    $amount      = 990; // £9.90 NHS prescription charge
    $description = 'NHS Prescription Fee';
} else {
    echo json_encode(['error' => 'Invalid payment type']); exit;
}

try {
    $intent = \Stripe\PaymentIntent::create([
        'amount'   => $amount,
        'currency' => 'gbp',
        'description' => $description,
        'metadata' => ['user_id' => $uid, 'type' => $type],
        'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
    ]);

    echo json_encode([
        'client_secret'  => $intent->client_secret,
        'amount'         => $amount,
        'amount_display' => '£' . number_format($amount / 100, 2),
        'description'    => $description,
    ]);
} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
