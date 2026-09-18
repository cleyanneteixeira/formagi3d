<?php
declare(strict_types=1);

const PRIVATE_DIR = __DIR__ . '/../.private';
const USERS_FILE = PRIVATE_DIR . '/users.json';
const PAYMENT_FILE = PRIVATE_DIR . '/payment.json';
const ORDERS_DIR = PRIVATE_DIR . '/orders';
const STORE_URL = 'https://formagi3d.trincadev.com.br';

function private_json(string $path, array $default): array {
    if (!is_file($path)) return $default;
    $value = json_decode((string)file_get_contents($path), true);
    if (!is_array($value)) throw new RuntimeException('Arquivo de dados inválido: ' . basename($path));
    return $value;
}

function save_private_json(string $path, array $value): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta de dados.');
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $tmp = tempnam($dir, '.write-');
    if ($tmp === false) throw new RuntimeException('Não foi possível criar arquivo temporário.');
    try {
        if (file_put_contents($tmp, $json, LOCK_EX) === false) throw new RuntimeException('Não foi possível salvar dados.');
        chmod($tmp, 0600);
        if (!rename($tmp, $path)) throw new RuntimeException('Não foi possível concluir gravação.');
    } finally { if (is_file($tmp)) unlink($tmp); }
}

function staff_users(): array {
    if (is_file(USERS_FILE)) return private_json(USERS_FILE, []);
    $legacy = is_file(PRIVATE_DIR . '/auth.php') ? require PRIVATE_DIR . '/auth.php' : null;
    if (!is_array($legacy) || empty($legacy['passwordHash'])) throw new RuntimeException('Senha inicial não encontrada.');
    return [['id'=>'admin','name'=>'Administrador','username'=>'admin','role'=>'admin','active'=>true,'passwordHash'=>$legacy['passwordHash']]];
}

function find_staff(string $username): ?array {
    foreach (staff_users() as $user) if (strtolower($user['username']) === strtolower($username)) return $user;
    return null;
}

function current_staff(): ?array {
    $id = (string)($_SESSION['user_id'] ?? (!empty($_SESSION['admin']) ? 'admin' : ''));
    foreach (staff_users() as $user) if ($user['id'] === $id && !empty($user['active'])) return $user;
    return null;
}

function is_owner(): bool { return (current_staff()['role'] ?? '') === 'admin'; }
function require_owner(): void { if (!is_owner()) throw new RuntimeException('Apenas administradores podem realizar esta ação.'); }

function payment_settings(): array {
    return array_merge(['handle'=>'','shippingCents'=>null,'enabled'=>false,'freeShippingRanges'=>[['from'=>65000001,'to'=>65099999]],'originCep'=>'','shippingEmail'=>'','melhorEnvioToken'=>''], private_json(PAYMENT_FILE, []));
}
function payment_ready(): bool {
    $p = payment_settings();
    return !empty($p['enabled']) && preg_match('/^[A-Za-z0-9_.-]{2,80}$/', (string)($p['handle'] ?? ''));
}

function normalize_shipping_token(mixed $value): string {
    if (!is_string($value)) throw new RuntimeException('Token do Melhor Envio inválido.');
    $token = preg_replace('/^Bearer\s+/i', '', trim($value));
    // Tokens não podem ser truncados: qualquer alteração invalida a assinatura.
    if (strlen($token) > 16384) throw new RuntimeException('Token do Melhor Envio muito longo. Cole apenas o token de acesso.');
    if ($token !== '' && !preg_match('/^[A-Za-z0-9._~+\/-]+=*$/D', $token)) throw new RuntimeException('Token do Melhor Envio inválido. Cole o token completo, sem aspas ou quebras de linha.');
    return $token;
}

function normalize_checkout_phone(string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    if (!preg_match('/^\+?[0-9\s().-]+$/D', $value)) throw new RuntimeException('Informe um telefone válido com DDD.');
    $digits = preg_replace('/\D/', '', $value);
    if (!str_starts_with($value, '+') && in_array(strlen($digits), [10, 11], true)) $digits = '55' . $digits;
    if (!preg_match('/^55[1-9][0-9](?:[2-5][0-9]{7}|9[0-9]{8})$/D', $digits)) throw new RuntimeException('Informe um telefone brasileiro válido com DDD, como (11) 99999-9999.');
    return '+' . $digits;
}

function infinitepay_error_message(int $status, string $body): string {
    $message = 'InfinitePay recusou a solicitação (HTTP ' . $status . ').';
    if ($status !== 422) return $message;
    // Só identifica nomes de campos conhecidos; nunca devolve dados do comprador
    // ou o corpo integral da resposta externa para o navegador ou os registros.
    $labels = ['phone_number'=>'telefone', 'email'=>'e-mail', 'handle'=>'InfiniteTag da loja', 'price'=>'valor dos itens', 'quantity'=>'quantidade dos itens', 'redirect_url'=>'URL de retorno', 'webhook_url'=>'URL de notificação'];
    $fields = [];
    $response = json_decode($body, true);
    $walk = function ($node) use (&$walk, &$fields, $labels): void {
        if (!is_array($node)) return;
        foreach ($node as $key=>$value) {
            foreach ($labels as $field=>$label) {
                if (preg_match('/(?:^|[.\[\]])' . preg_quote($field, '/') . '(?:$|[.\[\]])/', (string)$key)
                    || (in_array($key, ['field','param','path'], true) && is_string($value) && preg_match('/(?:^|[.\[\]])' . preg_quote($field, '/') . '(?:$|[.\[\]])/', $value))) $fields[$field] = $label;
            }
            $walk($value);
        }
    };
    $walk($response);
    return $message . ($fields ? ' Confira: ' . implode(', ', $fields) . '.' : ' O provedor não aceitou os dados do pedido; confira os dados do comprador, o valor e a configuração da loja.');
}

function shipping_quote(string $cep, array $items): array {
    $digits = preg_replace('/\D/', '', $cep);
    if (strlen($digits) !== 8) throw new RuntimeException('Informe um CEP válido com 8 dígitos.');
    $payment = payment_settings();
    $number = (int)$digits;
    foreach ($payment['freeShippingRanges'] as $range) {
        if ($number >= (int)$range['from'] && $number <= (int)$range['to']) return ['cep'=>$digits,'options'=>[['id'=>'local_free','shippingCents'=>0,'label'=>'Entrega grátis em São Luís','deliveryDays'=>null]]];
    }
    if (!preg_match('/^\d{8}$/',(string)$payment['originCep']) || empty($payment['melhorEnvioToken']) || !filter_var($payment['shippingEmail'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Cotação fora de São Luís ainda não configurada. Informe CEP de origem, e-mail e token do Melhor Envio no painel.');
    if (!$items) throw new RuntimeException('Carrinho vazio.');
    $catalog=json_decode((string)file_get_contents(__DIR__.'/../data/content.json'),true,32,JSON_THROW_ON_ERROR);
    $byId=[]; foreach($catalog['products']??[] as $p) if(!empty($p['active'])) $byId[(int)$p['id']]=$p;
    $products=[];
    foreach($items as $item) {
        if (!is_array($item)) throw new RuntimeException('Carrinho inválido.');
        $id=filter_var($item['id']??null,FILTER_VALIDATE_INT); $quantity=filter_var($item['quantity']??null,FILTER_VALIDATE_INT);
        if (!$id || !$quantity || $quantity<1 || $quantity>99 || !isset($byId[$id])) throw new RuntimeException('Produto indisponível para cotação.');
        $p=$byId[$id];
        foreach(['weightKg','widthCm','heightCm','lengthCm'] as $field) if((float)($p[$field]??0)<=0) throw new RuntimeException('Informe peso e dimensões do produto ' . $p['name'] . ' no gerenciador.');
        $products[]=['id'=>(string)$id,'width'=>(float)$p['widthCm'],'height'=>(float)$p['heightCm'],'length'=>(float)$p['lengthCm'],'weight'=>(float)$p['weightKg'],'insurance_value'=>(float)$p['price'],'quantity'=>$quantity];
    }
    if (!function_exists('curl_init')) throw new RuntimeException('Extensão cURL indisponível no servidor.');
    $token = normalize_shipping_token($payment['melhorEnvioToken']);
    $ch=curl_init('https://www.melhorenvio.com.br/api/v2/me/shipment/calculate');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['from'=>['postal_code'=>$payment['originCep']],'to'=>['postal_code'=>$digits],'products'=>$products,'options'=>['receipt'=>false,'own_hand'=>false]],JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json','Accept: application/json','User-Agent: FormaGi3D ('.$payment['shippingEmail'].')'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8]);
    $body=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); $curlNumber=curl_errno($ch); curl_close($ch);
    if(!is_string($body)) throw new RuntimeException('Sem conexão com Melhor Envio (cURL '.$curlNumber.').');
    if($status===401) throw new RuntimeException('Frete indisponível: o Melhor Envio recusou a autenticação (HTTP 401). A loja precisa salvar novamente um token completo e válido do ambiente de produção.');
    if($status===403) throw new RuntimeException('Frete indisponível: o token do Melhor Envio não tem acesso à cotação (HTTP 403). A loja precisa revisar as permissões da integração.');
    if($status<200||$status>=300) throw new RuntimeException('Melhor Envio recusou a cotação (HTTP '.$status.').');
    $response=json_decode($body,true);
    if(!is_array($response)) throw new RuntimeException('Resposta inválida do Melhor Envio.');
    $options=[];
    foreach($response as $row) {
        if(!is_array($row)||!empty($row['error'])||!isset($row['id'])) continue;
        $price=(float)($row['custom_price']??$row['price']??0);
        if($price<=0) continue;
        $options[]=['id'=>(string)$row['id'],'shippingCents'=>(int)round($price*100),'label'=>trim((string)($row['company']['name']??'Transportadora').' · '.(string)($row['name']??'Entrega')),'deliveryDays'=>(int)($row['custom_delivery_time']??$row['delivery_time']??0)];
    }
    usort($options,fn($a,$b)=>$a['shippingCents']<=>$b['shippingCents']);
    if(!$options) throw new RuntimeException('Nenhuma transportadora disponível para este CEP e carrinho.');
    return ['cep'=>$digits,'options'=>array_slice($options,0,8)];
}

function parse_shipping_ranges(string $text): array {
    $ranges=[];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line=trim($line);
        if ($line==='') continue;
        if (!preg_match('/^(\d{5}-?\d{3})\s+a\s+(\d{5}-?\d{3})$/iu',$line,$m)) throw new RuntimeException('Use uma faixa por linha, como 65000-001 a 65099-999.');
        $from=(int)str_replace('-','',$m[1]); $to=(int)str_replace('-','',$m[2]);
        if ($from>$to) throw new RuntimeException('Início da faixa maior que o fim.');
        $ranges[]=['from'=>$from,'to'=>$to];
        if (count($ranges)>20) throw new RuntimeException('Limite de 20 faixas de frete grátis.');
    }
    return $ranges;
}

function format_shipping_ranges(array $ranges): string {
    $format=fn($n)=>substr(str_pad((string)$n,8,'0',STR_PAD_LEFT),0,5).'-'.substr(str_pad((string)$n,8,'0',STR_PAD_LEFT),5);
    return implode("\n",array_map(fn($r)=>$format($r['from']).' a '.$format($r['to']),$ranges));
}

function order_path(string $id): string {
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) throw new RuntimeException('Código do pedido inválido.');
    return ORDERS_DIR . '/' . $id . '.json';
}
function read_order(string $id): ?array {
    $path = order_path($id);
    return is_file($path) ? private_json($path, []) : null;
}
function list_orders(): array {
    if (!is_dir(ORDERS_DIR)) return [];
    $orders = [];
    foreach (glob(ORDERS_DIR . '/*.json') ?: [] as $path) {
        $o = private_json($path, []);
        if (!empty($o['id'])) $orders[] = $o;
    }
    usort($orders, fn($a,$b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    return $orders;
}
function mutate_order(string $id, callable $change): array {
    $path = order_path($id);
    if (!is_file($path)) throw new RuntimeException('Pedido não encontrado.');
    $fp = fopen($path, 'c+');
    if (!$fp) throw new RuntimeException('Não foi possível abrir o pedido.');
    try {
        if (!flock($fp, LOCK_EX)) throw new RuntimeException('Não foi possível bloquear o pedido.');
        $order = json_decode((string)stream_get_contents($fp), true);
        if (!is_array($order)) throw new RuntimeException('Pedido inválido.');
        $change($order);
        $order['updatedAt'] = gmdate('c');
        $json = json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        rewind($fp); ftruncate($fp, 0);
        if (fwrite($fp, $json) === false) throw new RuntimeException('Não foi possível salvar pedido.');
        fflush($fp);
        return $order;
    } finally { flock($fp, LOCK_UN); fclose($fp); }
}

function infinitepay_post(string $route, array $payload): array {
    if (!in_array($route, ['links','payment_check'], true)) throw new RuntimeException('Rota de pagamento inválida.');
    if (!function_exists('curl_init')) throw new RuntimeException('A extensão cURL do PHP precisa estar ativa.');
    $ch = curl_init('https://api.checkout.infinitepay.io/' . $route);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload, JSON_THROW_ON_ERROR), CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20, CURLOPT_CONNECTTIMEOUT=>8]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlNumber = curl_errno($ch);
    curl_close($ch);
    if (!is_string($body)) throw new RuntimeException('Sem conexão com a InfinitePay (cURL ' . $curlNumber . ').');
    if ($status < 200 || $status >= 300) throw new RuntimeException(infinitepay_error_message($status, $body));
    $response = json_decode($body, true);
    if (!is_array($response)) throw new RuntimeException('Resposta inválida da InfinitePay.');
    return $response;
}

function confirm_payment(string $id, string $transaction, string $slug): array {
    $order = read_order($id);
    if (!$order) throw new RuntimeException('Pedido não encontrado.');
    if ($order['paymentStatus'] === 'paid') return $order;
    if ($transaction === '' || $slug === '') throw new RuntimeException('Dados de pagamento incompletos.');
    $handle = (string)($order['paymentHandle'] ?? '');
    if ($handle === '') throw new RuntimeException('Pagamento não configurado para este pedido.');
    $check = infinitepay_post('payment_check', ['handle'=>$handle,'order_nsu'=>$id,'transaction_nsu'=>$transaction,'slug'=>$slug]);
    if (empty($check['success']) || empty($check['paid']) || (int)($check['amount'] ?? -1) !== (int)$order['totalCents']) throw new RuntimeException('Pagamento ainda não confirmado.');
    return mutate_order($id, function (&$o) use ($transaction,$slug,$check) {
        if ($o['paymentStatus'] === 'paid') return;
        $o['paymentStatus'] = 'paid';
        $o['fulfillmentStatus'] = 'new';
        $o['paidAt'] = gmdate('c');
        $o['transactionNsu'] = $transaction;
        $o['invoiceSlug'] = $slug;
        $o['captureMethod'] = (string)($check['capture_method'] ?? '');
        $o['history'][] = ['at'=>gmdate('c'),'by'=>'InfinitePay','event'=>'Pagamento confirmado'];
    });
}
