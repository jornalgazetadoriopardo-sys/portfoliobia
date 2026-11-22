# Portfólio da Bia

Site pessoal de radialista e comunicadora: abertura, biografia com fotos, galeria com legendas,
vídeos e áudios do YouTube, recados dos visitantes e contatos. Tudo é atualizado por um painel
administrativo, sem mexer em código.

Feito em PHP puro com banco SQLite (um arquivo), para funcionar em qualquer hospedagem
compartilhada sem configurar banco de dados.

## Requisitos da hospedagem

- PHP 8.1 ou mais novo, com as extensões `pdo_sqlite`, `gd` e `mbstring` (padrão na maioria dos planos)
- Apache ou LiteSpeed (os arquivos `.htaccess` protegem as pastas internas)
- HTTPS ativo (recomendado; o painel usa senha)

## Como publicar

1. Envie todos os arquivos para a pasta pública do domínio (`public_html`), inclusive os `.htaccess`.
2. Garanta que o PHP pode gravar nas pastas `data/` e `uploads/` (permissão 755 ou 775).
3. Abra `seudominio.com.br/admin/` e crie o usuário e a senha. **Faça isso logo após enviar os
   arquivos**: enquanto não existir conta, a tela de primeiro acesso fica aberta.
4. No painel, preencha Perfil e Contatos e publique fotos, vídeos e áudios.

## Painel

| Seção | O que faz |
|---|---|
| Perfil | Nome, frase, cidade, apresentação, biografia, foto de abertura e fotos da biografia |
| Fotos | Envio de várias fotos de uma vez, legenda, ordem e exclusão |
| Vídeos | Link do YouTube, título e descrição |
| Áudios | Link do YouTube; o site toca só o som, com uma tela animada |
| Recados | Aprovar, retirar ou excluir; escolher se os recados passam por aprovação |
| Contatos | WhatsApp, telefone, e-mail, Instagram, Facebook, YouTube e TikTok |
| Senha | Troca da senha do painel |

Seções sem conteúdo (fotos, vídeos, áudios, contatos) não aparecem no site.

## Estrutura

```
index.php          página pública
comentar.php       recebe os recados
admin/index.php    painel
inc/bootstrap.php  banco, sessão, segurança e utilitários
assets/            CSS e JavaScript
data/              banco SQLite (criado sozinho; acesso bloqueado pela web)
uploads/           fotos enviadas (execução de scripts bloqueada)
```

## Backup

Copie a pasta `data/` (banco) e a pasta `uploads/` (fotos). É tudo o que muda depois da instalação.

## Testar no computador

```
php -S localhost:8000
```

O servidor embutido do PHP não lê `.htaccess`; use só para testes.
