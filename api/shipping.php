<?php
declare(strict_types=1);
require __DIR__ . '/../admin/commerce.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Método inválido.']); exit; }
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== STORE_URL) { http_response_code(403); echo json_encode(['error'=>'Origem inválida.']); exit; }
if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')), 'application/json')) { http_response_code(415); echo json_encode(['error'=>'Envie JSON.']); exit; }
try {
    $request=json_decode((string)file_get_contents('php://input',false,null,0,15000),true,32,JSON_THROW_ON_ERROR);
    if (!is_array($request) || !is_array($request['items']??null) || count($request['items'])<1 || count($request['items'])>30) throw new RuntimeException('Carrinho inválido.');
    echo json_encode(shipping_quote((string)($request['cep']??''),$request['items']),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
    http_response_code($e instanceof RuntimeException?400:500);
    echo json_encode(['error'=>$e instanceof RuntimeException?$e->getMessage():'Não foi possível calcular o frete.'],JSON_UNESCAPED_UNICODE);
}
