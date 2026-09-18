<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// O painel é executado sobre uma cópia isolada, sem credenciais ou pedidos reais.
$fixture = sys_get_temp_dir() . '/formagi-regression-' . bin2hex(random_bytes(8));
mkdir($fixture . '/admin', 0700, true);
mkdir($fixture . '/.private', 0700);
foreach (['commerce.php', 'lib.php'] as $file) copy(__DIR__ . '/../admin/' . $file, $fixture . '/admin/' . $file);
require $fixture . '/admin/lib.php';
$checks = 0;
function same(mixed $actual, mixed $expected, string $label): void {
    global $checks;
    if ($actual !== $expected) throw new RuntimeException('Falhou: ' . $label);
    $checks++;
}
function rejects(callable $action, string $label): void {
    try { $action(); } catch (RuntimeException $e) { same(true, true, $label); return; }
    throw new RuntimeException('Não rejeitou: ' . $label);
}
try {
    same(normalize_checkout_phone('(11) 99999-9999'), '+5511999999999', 'celular com máscara');
    same(normalize_checkout_phone('11999999999'), '+5511999999999', 'celular sem máscara');
    same(normalize_checkout_phone('+55 (11) 99999-9999'), '+5511999999999', 'DDI já informado');
    same(normalize_checkout_phone('5511999999999'), '+5511999999999', 'DDI sem sinal');
    same(normalize_checkout_phone('(55) 99999-9999'), '+5555999999999', 'DDD 55 não é confundido com DDI');
    same(normalize_checkout_phone('(11) 3333-4444'), '+551133334444', 'telefone fixo');
    same(normalize_checkout_phone(''), '', 'telefone opcional');
    foreach (['1199', 'abc11999999999', '+111999999999', '00000000000', str_repeat('9', 40)] as $invalid) rejects(fn()=>normalize_checkout_phone($invalid), 'telefone inválido');

    $longToken = 'eyJ' . str_repeat('a', 1300) . '.' . str_repeat('b', 600) . '.' . str_repeat('c', 300);
    same(normalize_shipping_token($longToken), $longToken, 'token maior que 1000 preservado');
    same(normalize_shipping_token(' Bearer ' . $longToken . ' '), $longToken, 'prefixo Bearer removido uma vez');
    same(normalize_shipping_token(''), '', 'campo vazio preserva configuração');
    rejects(fn()=>normalize_shipping_token("abc\r\nAuthorization: outro"), 'quebra de linha rejeitada');
    rejects(fn()=>normalize_shipping_token(str_repeat('a', 16385)), 'token excessivo rejeitado sem truncar');
    rejects(fn()=>normalize_shipping_token(['token']), 'tipo inválido rejeitado');

    $message = infinitepay_error_message(422, '{"errors":{"customer.phone_number":["Invalid sensitive value 11999999999"],"items.0.price":["invalid"]}}');
    same(str_contains($message, 'telefone'), true, 'campo telefone identificado');
    same(str_contains($message, 'valor dos itens'), true, 'campo valor identificado');
    same(str_contains($message, '11999999999'), false, 'dados do comprador não aparecem na mensagem');
    same(str_contains(infinitepay_error_message(422, '{"errors":[{"field":"customer.email","message":"invalid"}]}'), 'e-mail'), true, 'lista de campos identificada');
    same(str_contains(infinitepay_error_message(422, '<html>invalid</html>'), 'não aceitou'), true, 'erro sem JSON tem mensagem segura');

    // Respostas simuladas: valida o diagnóstico sem atribuir essas causas à loja.
    same(str_contains(infinitepay_error_message(422, '{"error":"Invalid handle"}'), 'Invalid handle'), true, 'erro textual simples da InfinitePay');
    same(str_contains(infinitepay_error_message(422, '{"message":"Order amount is below the minimum"}'), 'below the minimum'), true, 'motivo de valor preservado');
    $shippingMessage = shipping_error_message(422, '{"message":"The given data was invalid.","errors":{"from.postal_code":["Invalid postal code 65000001"],"products.0.insurance_value":["Value must be at least 1"]}}', ['from'=>['postal_code'=>'65000001']]);
    same(str_contains($shippingMessage, 'from.postal_code'), true, 'campo de CEP indicado');
    same(str_contains($shippingMessage, 'products.0.insurance_value'), true, 'campo de valor segurado indicado');
    same(str_contains($shippingMessage, 'at least 1'), true, 'restrição numérica preservada');
    same(str_contains($shippingMessage, '65000001'), false, 'CEP não exposto');
    same(str_contains(shipping_error_message(401, '{"message":"secret"}'), 'autenticação'), true, '401 continua distinto de validação');
    same(str_contains(shipping_error_message(403, ''), 'permissões'), true, '403 continua distinto');
    $detail = provider_validation_details(json_encode(['errors'=>['message'=>'Invalid Maria Exemplo maria@example.com +5511999999999 at 65000-001', 'input'=>'unrelated personal input', 'access_token'=>'private-token', 'client_secret'=>'private-secret']], JSON_THROW_ON_ERROR), ['name'=>'Maria Exemplo','phone'=>'+5511999999999','cep'=>'65000001']);
    foreach (['Maria Exemplo','maria@example.com','5511999999999','65000-001','unrelated personal input','private-token','private-secret'] as $sensitiveValue) same(str_contains($detail, $sensitiveValue), false, 'informação privada removida');
    same(provider_validation_details('<html>Proxy failure</html>'), '', 'HTML externo ignorado');
    same(str_contains(provider_validation_details('{"error":"<script>alert(1)</script>Invalid value"}'), '<script>'), false, 'tags removidas');
    same(mb_strlen(provider_validation_details(json_encode(['errors'=>array_fill(0, 20, str_repeat('x', 1000))]))) <= 1600, true, 'diagnóstico limitado');

    save_private_json(PAYMENT_FILE, ['freeShippingRanges'=>[['from'=>65000001,'to'=>65099999]]]);
    foreach (['65000-001','65050-000','65099-999'] as $cep) {
        $quote = shipping_quote($cep, [['id'=>1,'quantity'=>1]]);
        same($quote['options'][0]['id'], 'local_free', 'faixa grátis');
        same($quote['options'][0]['shippingCents'], 0, 'frete zero sem chamar Melhor Envio');
    }
    foreach (['65000000','65100000','01001000'] as $cep) rejects(fn()=>shipping_quote($cep, [['id'=>1,'quantity'=>1]]), 'fora da faixa exige configuração de frete');

    save_private_json(USERS_FILE, [['id'=>'admin','username'=>'admin','role'=>'admin','active'=>true]]);
    file_put_contents($fixture . '/save.php', <<<'PHP'
<?php
require __DIR__ . '/admin/lib.php';
$_SESSION = ['csrf'=>'regression', 'user_id'=>'admin'];
$_POST = ['csrf'=>'regression', 'action'=>'save_payment', 'handle'=>'test-store', 'enabled'=>'1', 'free_shipping_ranges'=>'65000-001 a 65099-999', 'origin_cep'=>'65000001', 'shipping_email'=>'test@example.com', 'melhor_envio_token'=>($argv[1] ?? '') === 'blank' ? '' : 'Bearer eyJ' . str_repeat('a', 1300) . '.' . str_repeat('b', 600) . '.' . str_repeat('c', 300)];
$content = [];
handle_action($content);
PHP);
    foreach (['long', 'blank'] as $mode) {
        $process = proc_open([PHP_BINARY, $fixture . '/save.php', $mode], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Não foi possível executar o teste do painel.');
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        same(proc_close($process), 0, 'ação do painel conclui');
        same($stderr, '', 'painel sem erros PHP');
        same(private_json(PAYMENT_FILE, [])['melhorEnvioToken'], $longToken, 'painel salva token completo e preserva campo vazio');
    }
    echo $checks . " verificações passaram. Nenhuma chamada externa ou cobrança foi realizada.\n";
} finally {
    foreach (['admin/commerce.php','admin/lib.php','.private/payment.json','.private/users.json','save.php'] as $file) {
        if (is_file($fixture . '/' . $file)) unlink($fixture . '/' . $file);
    }
    rmdir($fixture . '/admin'); rmdir($fixture . '/.private'); rmdir($fixture);
}
