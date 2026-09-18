<?php
declare(strict_types=1);
require __DIR__ . '/../admin/commerce.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['enabled'=>payment_ready()],JSON_THROW_ON_ERROR);
