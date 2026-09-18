<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
ini_set('session.use_strict_mode', '1');
session_name('formagi_admin');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/admin']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(24));
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (($_POST['action'] ?? '') === 'login') {
            $attempts = (int)($_SESSION['failed'] ?? 0);
            if ($attempts >= 10) throw new RuntimeException('Muitas tentativas. Abra uma nova sessão mais tarde.');
            $candidate = find_staff(trim((string)($_POST['username'] ?? 'admin')));
            if (!$candidate || empty($candidate['active']) || !password_verify((string)($_POST['password'] ?? ''), $candidate['passwordHash'])) {
                $_SESSION['failed'] = $attempts + 1;
                throw new RuntimeException('Senha incorreta.');
            }
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['user_id'] = $candidate['id'];
            $_SESSION['failed'] = 0;
            header('Location: index.php');
            exit;
        }
        if (($_POST['action'] ?? '') === 'logout') {
            $_SESSION = [];
            session_destroy();
            header('Location: index.php');
            exit;
        }
        require_auth();
        if (!current_staff()) { $_SESSION=[]; session_destroy(); throw new RuntimeException('Acesso desativado.'); }
        $content = load_content();
        handle_action($content);
    }
} catch (Throwable $ex) {
    $error = $ex->getMessage();
}

$loggedIn = !empty($_SESSION['admin']) && current_staff() !== null;
$tab = (string)($_GET['tab'] ?? 'products');
if (!in_array($tab, ['products', 'banners', 'categories', 'settings', 'orders', 'users', 'payment'], true)) $tab = 'products';
if ($loggedIn && !is_owner() && !in_array($tab,['orders'],true)) $tab='orders';
$content = $loggedIn ? load_content() : [];
$editingProduct = null;
if ($loggedIn && isset($_GET['edit'])) foreach ($content['products'] as $product) if ((int)$product['id'] === (int)$_GET['edit']) $editingProduct = $product;
$editingCategory = null;
if ($loggedIn && isset($_GET['category'])) foreach ($content['categories'] as $category) if ($category['id'] === $_GET['category']) $editingCategory = $category;
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gerenciador | Formagi3D</title><link rel="stylesheet" href="admin.css"></head><body>
<?php if (!$loggedIn): ?>
<main class="login-card"><div class="mark">F<span>3D</span></div><p class="eyebrow">FORMAGI3D</p><h1>Gerenciador da loja</h1><p>Entre para editar produtos, imagens, banners e informações.</p>
<?php if ($error): ?><p class="alert" role="alert"><?=e($error)?></p><?php endif; ?>
<form method="post"><?=csrf_input()?><input type="hidden" name="action" value="login"><label>Usuário<input name="username" required autocomplete="username" value="admin"></label><label>Senha<input name="password" type="password" required autocomplete="current-password" autofocus></label><button class="primary">Entrar</button></form></main>
<?php else: ?>
<header class="topbar"><div><div class="mark">F<span>3D</span></div><div><strong>Gerenciador Formagi3D</strong><small>Conteúdo da loja</small></div></div><div><a href="../index.html" target="_blank" rel="noopener">Ver loja ↗</a><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="logout"><button>Sair</button></form></div></header>
<div class="layout"><nav class="sidebar" aria-label="Seções"><a class="<?=$tab==='orders'?'active':''?>" href="?tab=orders">Pedidos</a><?php if(is_owner()): ?><a class="<?=$tab==='products'?'active':''?>" href="?tab=products">Produtos</a><a class="<?=$tab==='banners'?'active':''?>" href="?tab=banners">Banners</a><a class="<?=$tab==='categories'?'active':''?>" href="?tab=categories">Categorias</a><a class="<?=$tab==='payment'?'active':''?>" href="?tab=payment">Pagamentos</a><a class="<?=$tab==='users'?'active':''?>" href="?tab=users">Usuários</a><a class="<?=$tab==='settings'?'active':''?>" href="?tab=settings">Informações</a><?php endif; ?></nav><main class="main">
<?php if ($error): ?><p class="alert" role="alert"><?=e($error)?></p><?php endif; ?>
<?php if (!empty($_GET['saved'])): ?><p class="notice" role="status"><?=e($_GET['saved'])?></p><?php endif; ?>

<?php if (in_array($tab,['orders','users','payment'],true)): require __DIR__ . '/commerce-view.php'; ?>
<?php elseif ($tab === 'products'): ?>
<div class="page-heading"><div><p class="eyebrow">CATÁLOGO</p><h1>Produtos</h1><p>Cadastre as peças, preços, fotos e detalhes que aparecem na loja.</p></div><a class="primary link-button" href="?tab=products&edit=new">+ Novo produto</a></div>
<?php if (isset($_GET['edit'])): $p = $editingProduct ?? ['id'=>0,'name'=>'','price'=>'','category'=>'','badge'=>'','color'=>'#ff008a','featured'=>false,'active'=>true,'description'=>'','specs'=>[],'images'=>[]]; ?>
<section class="panel"><div class="panel-heading"><h2><?=$p['id']?'Editar produto':'Novo produto'?></h2><a href="?tab=products">Fechar</a></div><form method="post" enctype="multipart/form-data" class="form-grid"><?=csrf_input()?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?=e($p['id'])?>">
<label>Nome do produto<input name="name" required maxlength="120" value="<?=e($p['name'])?>"></label><label>Preço (R$)<input name="price" required inputmode="decimal" value="<?=e($p['price'])?>" placeholder="49,90"></label>
<label>Categoria<select name="category" required><option value="">Selecione</option><?php foreach ($content['categories'] as $cat): ?><option value="<?=e($cat['id'])?>" <?=$cat['id']===$p['category']?'selected':''?>><?=e($cat['name'])?></option><?php endforeach; ?></select></label>
<label>Selo curto<input name="badge" maxlength="40" value="<?=e($p['badge'])?>" placeholder="Novidade"></label>
<label>Cor do selo<input name="color" type="color" value="<?=e($p['color'])?>"></label>
<label>Peso embalado (kg)<input name="weightKg" type="number" min="0.001" step="0.001" value="<?=e($p['weightKg']??'')?>" placeholder="0,300"></label>
<label>Largura da embalagem (cm)<input name="widthCm" type="number" min="1" step="0.1" value="<?=e($p['widthCm']??'')?>"></label>
<label>Altura da embalagem (cm)<input name="heightCm" type="number" min="1" step="0.1" value="<?=e($p['heightCm']??'')?>"></label>
<label>Comprimento da embalagem (cm)<input name="lengthCm" type="number" min="1" step="0.1" value="<?=e($p['lengthCm']??'')?>"></label>
<label class="wide">Descrição<textarea name="description" rows="4" maxlength="3000"><?=e($p['description'])?></textarea></label>
<label class="wide">Características <small>Uma por linha, no formato Material: PLA</small><textarea name="specs" rows="4"><?=e(implode("\n", array_map(fn($key,$value)=>$key.': '.$value, array_keys($p['specs']), array_values($p['specs']))))?></textarea></label>
<label class="wide">Fotos do produto <small>JPG, PNG ou WebP. Até 8 MB por arquivo. A primeira foto será a capa.</small><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
<?php if ($p['images']): ?><div class="wide image-list"><?php foreach ($p['images'] as $path): ?><label class="image-tile"><img src="../<?=e($path)?>" alt=""><span><input type="checkbox" name="remove_images[]" value="<?=e($path)?>"> Remover</span></label><?php endforeach; ?></div><?php endif; ?>
<div class="wide checks"><label><input type="checkbox" name="active" <?=$p['active']?'checked':''?>> Publicado na loja</label><label><input type="checkbox" name="featured" <?=$p['featured']?'checked':''?>> Destaque na página inicial</label></div>
<div class="wide actions"><button class="primary">Salvar produto</button><a href="?tab=products">Cancelar</a></div></form></section>
<?php endif; ?>
<section class="panel"><h2>Produtos cadastrados <span class="count"><?=count($content['products'])?></span></h2><div class="list">
<?php foreach ($content['products'] as $p): ?><article class="item"><div class="thumb"><?php if (!empty($p['images'][0])): ?><img src="../<?=e($p['images'][0])?>" alt=""><?php endif; ?></div><div class="item-main"><strong><?=e($p['name'])?></strong><small><?=e($p['category'])?> · R$ <?=e(number_format((float)$p['price'],2,',','.'))?></small></div><span class="status <?=$p['active']?'on':'off'?>"><?=$p['active']?'Publicado':'Rascunho'?></span><a href="?tab=products&edit=<?=e($p['id'])?>">Editar</a><?php if ($p['active']): ?><form method="post" onsubmit="return confirm('Retirar este produto da vitrine?')"><?=csrf_input()?><input type="hidden" name="action" value="archive_product"><input type="hidden" name="id" value="<?=e($p['id'])?>"><button class="quiet">Retirar</button></form><?php endif; ?></article><?php endforeach; ?>
<?php if (!$content['products']): ?><p class="empty">Nenhum produto ainda. Use “Novo produto” para começar.</p><?php endif; ?></div></section>

<?php elseif ($tab === 'banners'): ?>
<div class="page-heading"><div><p class="eyebrow">VISUAL DA LOJA</p><h1>Banners</h1><p>Troque as imagens do banner principal e das faixas promocionais.</p></div></div>
<div class="banner-grid"><?php foreach ($content['banners'] as $banner): ?><section class="panel"><h2><?=e($banner['name'])?></h2><div class="banner-preview"><?php if ($banner['image']): ?><img src="../<?=e($banner['image'])?>" alt="Imagem atual do banner"><?php else: ?><span>Sem imagem</span><?php endif; ?></div><form method="post" enctype="multipart/form-data"><?=csrf_input()?><input type="hidden" name="action" value="save_banner"><input type="hidden" name="id" value="<?=e($banner['id'])?>"><label>Nova imagem<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><label class="check"><input type="checkbox" name="enabled" <?=$banner['enabled']?'checked':''?>> Exibir na loja</label><button class="primary">Salvar banner</button></form></section><?php endforeach; ?></div>

<?php elseif ($tab === 'categories'): ?>
<div class="page-heading"><div><p class="eyebrow">ORGANIZAÇÃO</p><h1>Categorias</h1><p>Crie grupos para facilitar a busca pelos produtos.</p></div><a class="primary link-button" href="?tab=categories&category=new">+ Nova categoria</a></div>
<?php if (isset($_GET['category'])): $cat=$editingCategory ?? ['id'=>'','name'=>'','subtitle'=>'','image'=>'','enabled'=>true]; ?><section class="panel"><div class="panel-heading"><h2><?=$cat['id']?'Editar categoria':'Nova categoria'?></h2><a href="?tab=categories">Fechar</a></div><form method="post" enctype="multipart/form-data" class="form-grid"><?=csrf_input()?><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?=e($cat['id'])?>"><label>Nome<input name="name" value="<?=e($cat['name'])?>" required></label><label>Subtítulo<input name="subtitle" value="<?=e($cat['subtitle'])?>"></label><label class="wide">Imagem da categoria<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><?php if ($cat['image']): ?><img class="category-preview" src="../<?=e($cat['image'])?>" alt="Imagem atual"><?php endif; ?><label class="wide check"><input type="checkbox" name="enabled" <?=$cat['enabled']?'checked':''?>> Exibir na loja</label><div class="wide actions"><button class="primary">Salvar categoria</button></div></form></section><?php endif; ?>
<section class="panel"><h2>Categorias cadastradas</h2><div class="list"><?php foreach ($content['categories'] as $cat): ?><article class="item"><div class="thumb"><?php if ($cat['image']): ?><img src="../<?=e($cat['image'])?>" alt=""><?php endif; ?></div><div class="item-main"><strong><?=e($cat['name'])?></strong><small><?=e($cat['subtitle'])?></small></div><span class="status <?=$cat['enabled']?'on':'off'?>"><?=$cat['enabled']?'Visível':'Oculta'?></span><a href="?tab=categories&category=<?=e(urlencode($cat['id']))?>">Editar</a></article><?php endforeach; ?></div></section>

<?php else: $s=$content['settings']; ?>
<div class="page-heading"><div><p class="eyebrow">IDENTIDADE</p><h1>Informações da loja</h1><p>Atualize os textos, contato e apresentação do banner.</p></div></div>
<section class="panel"><h2>Dados e textos</h2><form method="post" class="form-grid"><?=csrf_input()?><input type="hidden" name="action" value="save_settings">
<?php foreach (['storeName'=>'Nome da loja','tagline'=>'Frase da marca','location'=>'Cidade / localização','email'=>'E-mail de contato','whatsapp'=>'WhatsApp com DDD','instagram'=>'Link do Instagram','heroEyebrow'=>'Chamada acima do título','heroTitle'=>'Título do banner','heroDescription'=>'Descrição do banner','catalogNote'=>'Aviso abaixo dos produtos'] as $key=>$label): ?><label class="<?=in_array($key,['heroDescription','catalogNote'],true)?'wide':''?>"><?=e($label)?><?php if (in_array($key,['heroDescription','catalogNote'],true)): ?><textarea name="<?=e($key)?>" rows="3"><?=e($s[$key]??'')?></textarea><?php else: ?><input name="<?=e($key)?>" value="<?=e($s[$key]??'')?>"><?php endif; ?></label><?php endforeach; ?>
<div class="wide actions"><button class="primary">Salvar informações</button></div></form></section>
<section class="panel"><h2>Trocar senha</h2><form method="post" class="form-grid"><?=csrf_input()?><input type="hidden" name="action" value="change_password"><label>Senha atual<input type="password" name="current_password" required></label><label>Nova senha <small>Ao menos 12 caracteres</small><input type="password" name="new_password" minlength="12" required></label><div class="wide actions"><button>Alterar senha</button></div></form></section>
<?php endif; ?>
</main></div>
<?php endif; ?>
</body></html>
