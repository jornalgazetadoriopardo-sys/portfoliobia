# Site Bia Pizani

Site pessoal da locutora Bia Pizani: trajetória no rádio em linha do tempo, galeria de fotos por época,
trabalhos atuais, áudios, vídeos, recados e contato. Todo o conteúdo é cadastrado por um painel simples,
em português e pensado para o celular. O painel inclui o **Scanner Virtual de Fotos**, que digitaliza
fotos impressas pela câmera do celular.

Feito em PHP puro com banco SQLite (um arquivo), sem Node em produção. Roda em hospedagem compartilhada
comum (Hostinger, HostGator e similares).

## Documentos

| Arquivo | Para quem | Conteúdo |
|---|---|---|
| [docs/PENDENCIAS.md](docs/PENDENCIAS.md) | Rafael | O que depende de informação ou decisão sua |
| [docs/PUBLICACAO.md](docs/PUBLICACAO.md) | Rafael | Como publicar na hospedagem e apontar o domínio |
| [docs/GUIA-DE-USO.md](docs/GUIA-DE-USO.md) | Bia | Como usar o painel, com capturas de tela |

## Páginas

| Endereço | Conteúdo |
|---|---|
| `/` | Capa, fotos em destaque, trabalho atual, trecho da história e um recado |
| `/minha-historia` | Linha do tempo por décadas |
| `/galeria` e `/galeria/decada/1990` | Fotos por época, com filtro por década e ampliação |
| `/foto/12-titulo-da-foto` | Página própria de cada foto, para compartilhar |
| `/trabalhos` | Programas, horários e projetos |
| `/audios` e `/videos` | Aparecem no menu quando há conteúdo |
| `/recados` | Depoimentos; envio por visitantes é opcional |
| `/contato` | WhatsApp e redes sociais (sem formulário) |
| `/admin/` | Painel |

## Estrutura

```
index.php              entrada do site (decide a página pelo endereço)
comentar.php           recebe recados de visitantes
admin/index.php        entrada do painel
inc/bootstrap.php      banco, sessão, segurança, imagens
inc/site.php           cabeçalho, rodapé, sitemap
inc/paginas/           páginas públicas
inc/admin/             ações e telas do painel
assets/css, js, fonts  estilos, scripts e fontes (Lora e Poppins, no próprio site)
assets/vendor/opencv   OpenCV.js usado pelo scanner (servido pelo próprio site)
data/                  banco SQLite e originais sem tratamento (bloqueado pela web)
uploads/               fotos e áudios enviados (execução de scripts bloqueada)
ferramentas/           roteador de testes e conteúdo de exemplo
```

## Scanner Virtual de Fotos

Tudo acontece no navegador do celular; o servidor só recebe a imagem final.

1. **Fotografa**: câmera do celular ou imagem já salva no aparelho.
2. **Alinha**: detecção automática das bordas (Canny, limiar de Otsu, limiar adaptativo, nessa ordem),
   com quatro cantos arrastáveis e lupa. Botões "Detectar de novo", "Girar 90°" e "Usar imagem inteira".
3. **Trata**: correção de perspectiva, sombra (normalização da luminosidade em LAB, misturada com o
   original), cor (percentis 1%–99% por canal), contraste (CLAHE) e nitidez (filtro bilateral + unsharp
   mask). Modo automático que escolhe as intensidades pela própria foto, controles manuais e comparador
   de antes e depois.
4. **Publica**: JPEG sem metadados, até 2400 px, com legenda. "Salvar rascunho" ou "Publicar na galeria".
   O original sem tratamento é guardado em `data/originais/` quando cabe no limite de envio do servidor.

O processamento roda em um Web Worker (`assets/js/scanner-worker.js`). A função `enhance()` desse
arquivo é o ponto de extensão para uma futura restauração com IA.

## Testar no computador

```
php ferramentas/exemplo.php                       # opcional: conteúdo de exemplo
php -S localhost:8000 ferramentas/router.php
```

Abra `http://localhost:8000` e `http://localhost:8000/admin/`. A câmera do celular só funciona em HTTPS
ou em `localhost`; em rede local, use "Escolher uma imagem do aparelho".

## Requisitos

PHP 8.1 ou mais novo com `pdo_sqlite`, `gd`, `mbstring` e `fileinfo`; Apache ou LiteSpeed com
`mod_rewrite`; HTTPS.

## Licenças de terceiros

OpenCV.js (Apache 2.0), Lora e Poppins (SIL Open Font License). Os textos estão em
`assets/vendor/opencv/LICENSE` e `assets/fonts/`.
