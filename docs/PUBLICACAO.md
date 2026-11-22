# Publicação em hospedagem compartilhada

Vale para Hostinger, HostGator e outros planos com Apache ou LiteSpeed.

## 1. Antes de enviar

No painel da hospedagem:

1. Selecione **PHP 8.1 ou mais novo** para o domínio.
2. Confirme as extensões `pdo_sqlite`, `gd`, `mbstring` e `fileinfo` (vêm ativas por padrão na maioria dos planos).
3. Ative o **SSL gratuito** (HTTPS). Sem HTTPS a câmera do scanner não abre.
4. Ajuste os limites de envio do PHP:

| Opção | Valor |
|---|---|
| `upload_max_filesize` | `20M` |
| `post_max_size` | `64M` |
| `memory_limit` | `256M` |
| `max_file_uploads` | `30` |

- **Hostinger**: hPanel → Avançado → Configuração PHP → Opções PHP.
- **HostGator**: cPanel → Selecionar versão do PHP → Opções (ou "Editor INI do MultiPHP").

## 2. Enviar os arquivos

1. Baixe o projeto (GitHub → Code → Download ZIP) e descompacte.
2. Envie **o conteúdo** da pasta para `public_html`, pelo Gerenciador de Arquivos ou por FTP. O `index.php` precisa ficar direto dentro de `public_html`.
3. Confira que os arquivos `.htaccess` foram junto (ative "mostrar arquivos ocultos"). São cinco: na raiz e em `inc/`, `data/`, `uploads/`, `ferramentas/` e `docs/`.
4. As pastas `data/` e `uploads/` precisam de permissão de escrita (755 normalmente basta).

As pastas `docs/` e `ferramentas/` não são necessárias em produção e podem ficar de fora.

## 3. Primeiro acesso

1. Abra `https://seudominio.com.br/admin/` **logo depois de enviar** e crie o usuário e a senha. Enquanto não existir conta, essa tela fica aberta para qualquer pessoa.
2. Preencha **Configurações**: capa, WhatsApp, redes e link "ouvir ao vivo".

## 4. Conferência depois de publicar

| Teste | Resultado esperado |
|---|---|
| `https://seudominio.com.br/galeria` | Abre a galeria (se der "Not Found", o `.htaccess` da raiz não foi enviado ou o `mod_rewrite` está desligado) |
| `https://seudominio.com.br/data/site.sqlite` | Acesso negado (403) |
| `https://seudominio.com.br/inc/bootstrap.php` | Acesso negado (403) |
| `https://seudominio.com.br/sitemap.xml` | Lista de endereços do site |
| Painel → Scanner, no celular | "Scanner pronto." e a câmera abre em "Fotografar agora" |
| Enviar uma foto grande de celular | Publica sem erro de tamanho |

Se `data/site.sqlite` abrir ou baixar pelo navegador, pare e me avise: o servidor não está respeitando o `.htaccess` e o banco ficaria exposto.

## 5. Apontar o domínio

1. Registre o domínio no **Registro.br** (ou use o da hospedagem).
2. No Registro.br: Domínios → o domínio → **Alterar servidores DNS** e informe os dois servidores da hospedagem.
   - Hostinger: aparecem em hPanel → Domínios → DNS / Nameservers (costumam ser `ns1.dns-parking.com` e `ns2.dns-parking.com`).
   - HostGator: vêm no e-mail de boas-vindas e no cPanel (formato `ns123.hostgator.com.br`).
3. Na hospedagem, adicione o domínio ao plano (domínio principal ou "domínio adicional") apontando para `public_html`.
4. Aguarde a propagação, que pode levar de minutos a 24 horas. Depois, ative o SSL para o domínio.

## 6. Backup

Copie as pastas `data/` (banco e originais sem tratamento) e `uploads/` (fotos e áudios). É tudo o que muda depois da instalação. Para trocar de hospedagem, envie os arquivos do projeto e essas duas pastas.

## 7. Atualizar o site depois

Envie os arquivos novos por cima dos antigos, **sem apagar** `data/` nem `uploads/`. O banco se atualiza sozinho no primeiro acesso.
