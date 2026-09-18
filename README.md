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

## Correção de checkout e frete — 18/09/2026

- O telefone brasileiro é normalizado para `+55` + DDD + número antes de gerar o link da InfinitePay; um telefone opcional vazio continua omitido.
- O token do Melhor Envio não é mais cortado em 1.000 caracteres. Entradas excessivas ou malformadas são rejeitadas sem substituir a configuração salva.
- Respostas de erro distinguem falhas de autenticação/permissão do frete e identificam campos de validação da InfinitePay quando fornecidos, sem expor os dados retornados do comprador.

Após atualizar `admin/commerce.php`, `admin/lib.php` e `api/checkout.php` na hospedagem, abra o painel em **Pagamento e frete** e salve novamente o token **completo, válido e de produção** do Melhor Envio. A parte que a versão anterior cortou não pode ser recuperada do arquivo salvo. Deixar o campo vazio mantém o token anterior.

O erro 422 antigo não incluía os detalhes retornados pela InfinitePay, portanto a confirmação do pagamento exige uma nova tentativa após a atualização. Não foi alterado o valor dos produtos ou o status de pagamento dos pedidos existentes.

Teste local, sem chamadas externas e com configurações temporárias isoladas:

```sh
php tests/commerce-regression.php
```

### Diagnóstico dos erros 422

Se o erro persistir, a mensagem agora inclui `Motivo informado:` com as explicações textuais de validação retornadas pela InfinitePay ou pelo Melhor Envio, inclusive erros que chegam apenas em `message` ou `error`. Dados enviados do comprador, tokens e campos de entrada ecoados são ocultados. Respostas não estruturadas continuam recebendo uma mensagem genérica.

Esta mudança melhora o diagnóstico; não comprova nem corrige, por si só, a causa de uma rejeição do provedor. Atualize `admin/commerce.php` e repita as duas tentativas para obter o motivo. O preço dos produtos, o valor segurado e as configurações comerciais não foram alterados. Não há chamadas externas nem cobranças nos testes automatizados.
