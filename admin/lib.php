<?php
declare(strict_types=1);

const CONTENT_FILE = __DIR__ . '/../data/content.json';
const PUBLIC_JS_FILE = __DIR__ . '/../content.js';
const AUTH_FILE = __DIR__ . '/../.private/auth.php';
const UPLOAD_DIR = __DIR__ . '/../uploads';
require_once __DIR__ . '/commerce.php';

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function load_content(): array {
    $content = json_decode((string)file_get_contents(CONTENT_FILE), true);
    if (!is_array($content)) throw new RuntimeException('Não foi possível ler o catálogo.');
    return $content;
}

function publish_content(array $content): void {
    $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    $privateBackup = dirname(AUTH_FILE) . '/content-backup.json';
    if (is_file(CONTENT_FILE)) copy(CONTENT_FILE, $privateBackup);
    if (file_put_contents(CONTENT_FILE, $json . "\n", LOCK_EX) === false) throw new RuntimeException('Não foi possível salvar data/content.json. Verifique as permissões.');
    if (file_put_contents(PUBLIC_JS_FILE, 'window.FORMAGI_CONTENT=' . $json . ";\n", LOCK_EX) === false) throw new RuntimeException('Não foi possível publicar content.js. Verifique as permissões.');
}

function auth_hash(): string {
    $auth = is_file(AUTH_FILE) ? require AUTH_FILE : null;
    if (!is_array($auth) || empty($auth['passwordHash'])) throw new RuntimeException('Configure a senha do gerenciador antes de publicar.');
    return (string)$auth['passwordHash'];
}

function require_auth(): void {
    if (empty($_SESSION['admin'])) {
        header('Location: index.php');
        exit;
    }
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function verify_csrf(): void {
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
    }
}

function redirect_tab(string $tab, string $message = ''): never {
    $query = http_build_query(['tab' => $tab, 'saved' => $message]);
    header('Location: index.php?' . $query);
    exit;
}

function input(string $name, int $limit = 500): string {
    return mb_substr(trim((string)($_POST[$name] ?? '')), 0, $limit);
}

function slug(string $text): string {
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii));
    return trim((string)$slug, '-') ?: bin2hex(random_bytes(4));
}

function upload_image(string $field, bool $multiple = false): array {
    if (empty($_FILES[$field])) return [];
    $upload = $_FILES[$field];
    $items = $multiple ? array_map(null, $upload['name'], $upload['tmp_name'], $upload['error'], $upload['size']) : [[$upload['name'], $upload['tmp_name'], $upload['error'], $upload['size']]];
    $paths = [];
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0775, true) && !is_dir(UPLOAD_DIR)) throw new RuntimeException('Não foi possível criar uploads/.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($items as [$name, $tmp, $error, $size]) {
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('Falha ao enviar imagem: ' . e($name));
        if ($size > 8 * 1024 * 1024) throw new RuntimeException('Cada imagem deve ter no máximo 8 MB.');
        if (!is_uploaded_file($tmp)) throw new RuntimeException('Upload inválido.');
        $mime = $finfo->file($tmp);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || getimagesize($tmp) === false) throw new RuntimeException('Use JPG, PNG ou WebP.');
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($tmp, UPLOAD_DIR . '/' . $filename)) throw new RuntimeException('Não foi possível guardar a imagem.');
        $paths[] = 'uploads/' . $filename;
    }
    return $paths;
}

function parse_specs(string $value): array {
    $specs = [];
    foreach (preg_split('/\r?\n/', $value) as $line) {
        if (!str_contains($line, ':')) continue;
        [$key, $detail] = array_map('trim', explode(':', $line, 2));
        if ($key !== '' && $detail !== '') $specs[mb_substr($key, 0, 60)] = mb_substr($detail, 0, 180);
    }
    return $specs;
}

function handle_action(array &$content): void {
    verify_csrf();
    $action = input('action', 40);
    if ($action === 'update_order') {
        $id = input('id', 32);
        $next = input('fulfillment', 30);
        $allowed = ['new','preparing','ready','shipped','delivered','cancelled'];
        if (!in_array($next, $allowed, true)) throw new RuntimeException('Etapa inválida.');
        mutate_order($id, function (&$o) use ($next) {
            if ($o['paymentStatus'] !== 'paid') throw new RuntimeException('Confirme o pagamento antes de preparar o pedido.');
            $o['fulfillmentStatus'] = $next;
            $o['tracking'] = input('tracking', 120);
            $o['internalNote'] = input('internal_note', 1000);
            $o['history'][] = ['at'=>gmdate('c'),'by'=>(current_staff()['name'] ?? 'Equipe'),'event'=>'Etapa: ' . $next];
        });
        redirect_tab('orders', 'Pedido atualizado.');
    }
    if ($action === 'save_user') {
        require_owner();
        $users = staff_users();
        $id = input('id', 32);
        $username = strtolower(input('username', 50));
        $name = input('name', 100);
        $role = input('role', 20);
        $password = (string)($_POST['password'] ?? '');
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username) || $name === '' || !in_array($role, ['admin','operator'], true)) throw new RuntimeException('Preencha nome, usuário e função válidos.');
        foreach ($users as $u) if ($u['username'] === $username && $u['id'] !== $id) throw new RuntimeException('Esse usuário já existe.');
        $found = false;
        foreach ($users as &$u) if ($u['id'] === $id && $id !== '') {
            if ($id === 'admin' && ($role !== 'admin' || !isset($_POST['active']))) throw new RuntimeException('A conta principal precisa continuar administradora e ativa.');
            if ($id === (current_staff()['id'] ?? '') && !isset($_POST['active'])) throw new RuntimeException('Você não pode desativar sua própria conta.');
            $u['name']=$name; $u['username']=$username; $u['role']=$role; $u['active']=isset($_POST['active']);
            if ($password !== '') { if (strlen($password)<12) throw new RuntimeException('A senha precisa ter pelo menos 12 caracteres.'); $u['passwordHash']=password_hash($password,PASSWORD_DEFAULT); }
            $found=true; break;
        }
        unset($u);
        if (!$found) {
            if (strlen($password)<12) throw new RuntimeException('Informe uma senha de pelo menos 12 caracteres.');
            $users[]=['id'=>bin2hex(random_bytes(8)),'name'=>$name,'username'=>$username,'role'=>$role,'active'=>isset($_POST['active']),'passwordHash'=>password_hash($password,PASSWORD_DEFAULT)];
        }
        if (!array_filter($users, fn($u)=>$u['active'] && $u['role']==='admin')) throw new RuntimeException('É necessário manter um administrador ativo.');
        save_private_json(USERS_FILE,$users);
        redirect_tab('users','Usuário salvo.');
    }
    if ($action === 'save_payment') {
        require_owner();
        $handle = ltrim(input('handle', 80), '$');
        if ($handle !== '' && !preg_match('/^[A-Za-z0-9_.-]{2,80}$/',$handle)) throw new RuntimeException('InfiniteTag inválida.');
        if (isset($_POST['enabled']) && $handle === '') throw new RuntimeException('Informe a InfiniteTag antes de ativar pagamentos.');
        $existing=payment_settings();
        $existing['handle']=$handle;
        $existing['enabled']=isset($_POST['enabled']);
        $existing['freeShippingRanges']=parse_shipping_ranges(input('free_shipping_ranges', 2000));
        $origin=preg_replace('/\D/','',input('origin_cep', 20));
        if ($origin!=='' && strlen($origin)!==8) throw new RuntimeException('Informe um CEP de origem válido.');
        $email=input('shipping_email', 180);
        if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe um e-mail válido para o Melhor Envio.');
        $token=input('melhor_envio_token', 1000);
        if ($token!=='') $existing['melhorEnvioToken']=$token;
        $existing['originCep']=$origin;
        $existing['shippingEmail']=$email;
        save_private_json(PAYMENT_FILE,$existing);
        redirect_tab('payment','Pagamento atualizado.');
    }
    require_owner();
    if ($action === 'save_product') {
        $id = (int)($_POST['id'] ?? 0);
        $name = input('name', 120);
        $priceText = str_replace(',', '.', input('price', 30));
        $category = input('category', 100);
        if ($name === '' || !is_numeric($priceText) || (float)$priceText < 0) throw new RuntimeException('Informe nome e preço válidos.');
        if (!in_array($category, array_column($content['categories'], 'id'), true)) throw new RuntimeException('Escolha uma categoria existente.');
        $existing = null;
        foreach ($content['products'] as $p) if ((int)$p['id'] === $id) $existing = $p;
        if ($id === 0) $id = max(array_merge([0], array_map('intval', array_column($content['products'], 'id')))) + 1;
        $images = $existing['images'] ?? [];
        $remove = array_map('strval', (array)($_POST['remove_images'] ?? []));
        $images = array_values(array_filter($images, fn($path) => !in_array($path, $remove, true)));
        $images = array_merge($images, upload_image('images', true));
        $color = input('color', 7);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#ff008a';
        $dimensions=[];
        foreach (['weightKg','widthCm','heightCm','lengthCm'] as $field) {
            $value=str_replace(',','.',input($field, 30));
            if ($value!=='' && (!is_numeric($value) || (float)$value<=0 || (float)$value>1000)) throw new RuntimeException('Informe peso e dimensões válidos.');
            $dimensions[$field]=$value===''?null:(float)$value;
        }
        $product = [
            'id' => $id, 'name' => $name, 'price' => round((float)$priceText, 2),
            'category' => $category, 'badge' => input('badge', 40), 'color' => $color,
            'reviews' => (int)($existing['reviews'] ?? 0),
            'featured' => isset($_POST['featured']), 'active' => isset($_POST['active']),
            'description' => input('description', 3000),
            'specs' => parse_specs(input('specs', 3000)), 'images' => $images
        ];
        $product=array_merge($product,$dimensions);
        $found = false;
        foreach ($content['products'] as $index => $p) if ((int)$p['id'] === $id) { $content['products'][$index] = $product; $found = true; break; }
        if (!$found) $content['products'][] = $product;
        publish_content($content);
        redirect_tab('products', 'Produto salvo.');
    }
    if ($action === 'archive_product') {
        $id = (int)($_POST['id'] ?? 0);
        foreach ($content['products'] as &$p) if ((int)$p['id'] === $id) $p['active'] = false;
        unset($p);
        publish_content($content);
        redirect_tab('products', 'Produto retirado da vitrine.');
    }
    if ($action === 'save_category') {
        $id = input('id', 100);
        $name = input('name', 80);
        if ($name === '') throw new RuntimeException('Informe o nome da categoria.');
        if ($id === '') $id = slug($name);
        $existing = null;
        foreach ($content['categories'] as $cat) if ($cat['id'] === $id) $existing = $cat;
        $uploaded = upload_image('image');
        $category = ['id' => $id, 'name' => $name, 'subtitle' => input('subtitle', 120), 'image' => $uploaded[0] ?? ($existing['image'] ?? ''), 'enabled' => isset($_POST['enabled'])];
        $found = false;
        foreach ($content['categories'] as $index => $cat) if ($cat['id'] === $id) { $content['categories'][$index] = $category; $found = true; break; }
        if (!$found) $content['categories'][] = $category;
        publish_content($content);
        redirect_tab('categories', 'Categoria salva.');
    }
    if ($action === 'save_banner') {
        $id = input('id', 30);
        foreach ($content['banners'] as &$banner) if ($banner['id'] === $id) {
            $uploaded = upload_image('image');
            $banner['image'] = $uploaded[0] ?? $banner['image'];
            $banner['enabled'] = isset($_POST['enabled']);
            break;
        }
        unset($banner);
        publish_content($content);
        redirect_tab('banners', 'Banner salvo.');
    }
    if ($action === 'save_settings') {
        foreach (['storeName','tagline','location','email','whatsapp','instagram','heroEyebrow','heroTitle','heroDescription','catalogNote'] as $field) {
            $content['settings'][$field] = input($field, $field === 'heroDescription' ? 1000 : 300);
        }
        publish_content($content);
        redirect_tab('settings', 'Informações salvas.');
    }
    if ($action === 'change_password') {
        $users=staff_users();
        $id=current_staff()['id'] ?? '';
        $current=null;
        foreach($users as $u) if($u['id']===$id) $current=$u;
        if (!$current || !password_verify(input('current_password', 200), $current['passwordHash'])) throw new RuntimeException('Senha atual incorreta.');
        $new = input('new_password', 200);
        if (strlen($new) < 12) throw new RuntimeException('A nova senha deve ter pelo menos 12 caracteres.');
        foreach($users as &$u) if($u['id']===$id) $u['passwordHash']=password_hash($new,PASSWORD_DEFAULT);
        unset($u);
        save_private_json(USERS_FILE,$users);
        redirect_tab('settings', 'Senha alterada.');
    }
    throw new RuntimeException('Ação desconhecida.');
}
