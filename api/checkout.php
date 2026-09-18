<?php
declare(strict_types=1);
require __DIR__ . '/../admin/commerce.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function checkout_error(int $status,string $message): never { http_response_code($status); echo json_encode(['error'=>$message],JSON_THROW_ON_ERROR); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST') checkout_error(405,'Método inválido.');
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN']!==STORE_URL) checkout_error(403,'Origem inválida.');
if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json')) checkout_error(415,'Envie JSON.');
if (!payment_ready()) checkout_error(503,'Pagamento ainda não está configurado.');
try {
    $raw=file_get_contents('php://input',false,null,0,25000);
    $request=json_decode((string)$raw,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($request)) throw new RuntimeException('Pedido inválido.');
    $customer=(array)($request['customer']??[]);
    $shipping=(array)($request['shipping']??[]);
    foreach(['name','email'] as $key) if (trim((string)($customer[$key]??''))==='') throw new RuntimeException('Informe seus dados de contato.');
    if (!filter_var($customer['email'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('E-mail inválido.');
    foreach(['street','number','city','state','cep'] as $key) if(trim((string)($shipping[$key]??''))==='') throw new RuntimeException('Informe o endereço completo.');
    $customer=['name'=>mb_substr(trim((string)$customer['name']),0,120),'email'=>mb_substr(trim((string)$customer['email']),0,180),'phone'=>mb_substr(trim((string)($customer['phone']??'')),0,30)];
    $shipping=array_intersect_key($shipping,array_flip(['street','number','complement','city','state','cep']));
    foreach($shipping as &$v) $v=mb_substr(trim((string)$v),0,180); unset($v);
    if (!preg_match('/^[0-9]{8}$/',preg_replace('/\D/','',$shipping['cep']))) throw new RuntimeException('CEP inválido.');
    $shipping['cep']=preg_replace('/\D/','',$shipping['cep']);
    $incoming=$request['items']??[];
    if (!is_array($incoming)||count($incoming)<1||count($incoming)>30) throw new RuntimeException('Carrinho inválido.');
    $catalog=json_decode((string)file_get_contents(__DIR__.'/../data/content.json'),true,32,JSON_THROW_ON_ERROR);
    $byId=[]; foreach($catalog['products']??[] as $product) if(!empty($product['active'])) $byId[(int)$product['id']]=$product;
    $lines=[]; $total=0; $seen=[];
    foreach($incoming as $row) {
        if (!is_array($row)) throw new RuntimeException('Item inválido.');
        $id=filter_var($row['id']??null,FILTER_VALIDATE_INT); $quantity=filter_var($row['quantity']??null,FILTER_VALIDATE_INT);
        if (!$id||!$quantity||$quantity<1||$quantity>99||!isset($byId[$id])||isset($seen[$id])) throw new RuntimeException('Carrinho inválido ou produto indisponível.');
        $seen[$id]=true; $p=$byId[$id]; $cents=(int)round((float)$p['price']*100);
        if ($cents<1) throw new RuntimeException('Preço do produto inválido.');
        $lines[]=['id'=>$id,'name'=>(string)$p['name'],'quantity'=>$quantity,'priceCents'=>$cents];
        $total+=$quantity*$cents;
    }
    $quote=shipping_quote($shipping['cep'],$incoming);
    $selectedId=(string)($request['shippingOption']??'');
    $option=null;
    foreach($quote['options'] as $candidate) if(hash_equals((string)$candidate['id'],$selectedId)) { $option=$candidate; break; }
    if ($option===null) throw new RuntimeException('Selecione uma opção de frete válida.');
    $settings=payment_settings(); $shippingCents=(int)$option['shippingCents']; $total+=$shippingCents;
    if ($total>100000000) throw new RuntimeException('Valor do pedido acima do limite.');
    if (!is_dir(ORDERS_DIR) && !mkdir(ORDERS_DIR,0770,true) && !is_dir(ORDERS_DIR)) throw new RuntimeException('Não foi possível criar pedidos.');
    $id=bin2hex(random_bytes(16));
    $order=['id'=>$id,'createdAt'=>gmdate('c'),'updatedAt'=>gmdate('c'),'customer'=>$customer,'shipping'=>$shipping,'items'=>$lines,'shippingOption'=>$option,'shippingCents'=>$shippingCents,'totalCents'=>$total,'paymentHandle'=>$settings['handle'],'paymentStatus'=>'pending','fulfillmentStatus'=>'awaiting_payment','history'=>[['at'=>gmdate('c'),'by'=>'Loja','event'=>'Pedido criado']]];
    $fp=fopen(order_path($id),'x'); if(!$fp) throw new RuntimeException('Não foi possível registrar pedido.');
    fwrite($fp,json_encode($order,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); fclose($fp);
    $items=array_map(fn($x)=>['quantity'=>$x['quantity'],'price'=>$x['priceCents'],'description'=>$x['name']],$lines);
    if ($shippingCents>0) $items[]=['quantity'=>1,'price'=>$shippingCents,'description'=>'Frete'];
    $payload=['handle'=>$settings['handle'],'order_nsu'=>$id,'redirect_url'=>STORE_URL.'/api/payment-return.php','webhook_url'=>STORE_URL.'/api/webhook.php','items'=>$items,'customer'=>['name'=>$customer['name'],'email'=>$customer['email']]];
    if($customer['phone']!=='') $payload['customer']['phone_number']=$customer['phone'];
    try { $link=infinitepay_post('links',$payload); }
    catch(Throwable $error) {
        mutate_order($id,function (&$o) use ($error) { $o['paymentError']=$error->getMessage(); });
        throw $error;
    }
    $url=(string)($link['url']??'');
    $parts=parse_url($url);
    if (!$parts || ($parts['scheme']??'')!=='https' || !in_array(strtolower((string)($parts['host']??'')),['checkout.infinitepay.com.br','checkout.infinitepay.io'],true)) throw new RuntimeException('Link de pagamento inválido.');
    mutate_order($id,function (&$o) use ($url) { $o['paymentUrl']=$url; });
    echo json_encode(['orderId'=>$id,'url'=>$url],JSON_THROW_ON_ERROR);
} catch(Throwable $e) { checkout_error(400,$e instanceof RuntimeException?$e->getMessage():'Não foi possível iniciar o pagamento.'); }
