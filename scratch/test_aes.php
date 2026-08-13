<?php
require_once __DIR__ . '/../config/security.php';

$empCode = "EMP001";
$encrypted = encrypt_qr_payload($empCode, 12);
echo "Encrypted payload:\n" . $encrypted . "\n\n";

$decrypted = decrypt_qr_payload($encrypted);
echo "Decrypted payload:\n";
print_r($decrypted);

$isExpired = strtotime($decrypted['expires_at']) < time();
echo "\nIs Expired: " . ($isExpired ? "YES" : "NO") . "\n";
