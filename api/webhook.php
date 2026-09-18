<?php
declare(strict_types=1);
require __DIR__ . '/../admin/commerce.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
try {
    $body=json_decode((string)file_get_contents('php://input',false,null,0,25000),true,32,JSON_THROW_ON_ERROR);
    if (!is_array($body)) throw new RuntimeException('Notificação inválida.');
    $order=confirm_payment((string)($body['order_nsu']??''),(string)($body['transaction_nsu']??''),(string)($body['invoice_slug']??''));
    http_response_code(200); echo json_encode(['success'=>true,'message'=>null],JSON_THROW_ON_ERROR);
} catch(Throwable $e) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Pagamento não confirmado.'],JSON_THROW_ON_ERROR); }
