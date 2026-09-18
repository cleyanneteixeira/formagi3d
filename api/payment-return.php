<?php
declare(strict_types=1);
require __DIR__ . '/../admin/commerce.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$state='Aguardando confirmação do pagamento.';
$id=(string)($_GET['order_nsu']??'');
try {
    $order=confirm_payment($id,(string)($_GET['transaction_nsu']??''),(string)($_GET['slug']??''));
    $state='Pagamento confirmado! Seu pedido entrou na fila de preparo.';
} catch(Throwable $e) {
    try { if (($existing=read_order($id)) && ($existing['paymentStatus']??'')==='paid') $state='Pagamento confirmado! Seu pedido entrou na fila de preparo.'; } catch(Throwable $ignored) {}
}
$short=preg_match('/^[a-f0-9]{32}$/',$id)?strtoupper(substr($id,0,8)):'';
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pedido | Formagi3D</title><link rel="stylesheet" href="../styles.css"></head><body><main class="wrap" style="min-height:70vh;padding-block:90px"><h1>Pedido #<?=htmlspecialchars($short,ENT_QUOTES,'UTF-8')?></h1><p><?=htmlspecialchars($state,ENT_QUOTES,'UTF-8')?></p><p>Guarde o número do pedido para falar com nossa equipe.</p><a class="button" href="../index.html">Voltar à loja</a></main><?php if(str_starts_with($state,'Pagamento confirmado')): ?><script>localStorage.removeItem('formagi3d_cart')</script><?php endif; ?></body></html>
