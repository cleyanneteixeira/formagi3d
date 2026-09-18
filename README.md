# Formagi3D

Loja de impressão 3D com catálogo, páginas de produtos, carrinho, checkout, rastreamento e painel administrativo em PHP.

Esta base foi importada da versão fornecida em 18/09/2026, desenvolvida em outro computador.

## Estrutura

- `index.html` e demais páginas HTML: loja e páginas institucionais.
- `shared.js`, `home.js` e scripts por página: navegação e comportamento da loja.
- `data/content.json` e `content.js`: catálogo e conteúdo público.
- `admin/`: gerenciamento do catálogo, usuários e configurações comerciais.
- `api/`: checkout, frete e notificações de pagamento.
- `assets/` e `uploads/`: imagens e arquivos públicos da loja.

## Ambiente

O projeto usa HTML, CSS e JavaScript sem etapa de compilação. As funções administrativas e comerciais exigem PHP; a configuração importada utiliza Apache/cPanel com PHP 8.2. O domínio de retorno está definido em `admin/commerce.php` e deve ser revisado ao mudar a hospedagem.

## Dados privados

Credenciais, usuários, configuração de pagamento, tokens, backups e pedidos em `.private/` não são versionados. Somente a regra de bloqueio de acesso `.private/.htaccess` acompanha o código.

Para instalar em outro ambiente, configure os dados privados separadamente. O primeiro administrador exige `.private/auth.php` retornando um array com a chave `passwordHash`, gerada com `password_hash`. Não existe senha padrão no repositório. O painel permite configurar pagamento e frete após o acesso administrativo.

Preserve os dados privados existentes ao atualizar a hospedagem. Configure permissões de escrita nas pastas usadas pelo painel e mantenha `.private/` inacessível por HTTP. A configuração `php.ini` específica do servidor também fica fora do Git.

O envio ao GitHub não executa pagamentos nem publica automaticamente a loja na hospedagem.
