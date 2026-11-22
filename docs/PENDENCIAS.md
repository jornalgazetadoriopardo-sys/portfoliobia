# Pendências para o Rafael

Tudo o que dependia só de código está feito. Esta lista reúne o que precisa de informação, material ou
decisão sua. A numeração das decisões segue a seção 11 do briefing.

## 1. Decisões que eu tomei e você precisa confirmar

| # | Decisão do briefing | O que foi feito | Se quiser mudar |
|---|---|---|---|
| 1 | WordPress ou PHP próprio | **PHP próprio**, continuando o site que já estava em andamento. Atende às restrições (hospedagem compartilhada, sem Node, painel simples no celular). O banco é SQLite, um arquivo dentro de `data/`, sem criar banco MySQL. | Migrar para WordPress é refazer tema e plugin; o scanner (JavaScript) seria reaproveitado quase inteiro. Me avise antes de cadastrar conteúdo real. |
| 2 | Seção de áudios | **Entrou.** Aceita MP3 enviado pelo painel ou link do YouTube (toca só o som, com tela animada). A página só aparece no menu quando há áudio. | Basta não cadastrar áudios. |
| 3 | Recados de visitantes | **Ligado, com aprovação**: o visitante envia e o recado só aparece depois de aprovado. A Bia também cadastra recados direto no painel, com relação e foto. | Painel → Recados → desmarcar "Visitantes podem enviar recados pelo site". |
| 7 | Ponto focal | **Manual por foto**, além de layouts sem corte. A galeria, o lightbox, a linha do tempo e a página da foto nunca cortam. O ponto focal vale para a capa e para as imagens dos trabalhos. | — |
| — | Identidade visual | A pedido seu, a paleta do briefing (azul-marinho, vermelho, creme) foi trocada pela do **logotipo**: petróleo `#0e6072`, cobre `#c2703f` e prata `#f6f4f0`, sempre em fundo claro. O topo voltou a ser o **dial de rádio**, com o ponteiro na página atual. Lora e Poppins foram mantidas. | As cores ficam no início de `assets/css/site.css`. |
| — | Frase da Bia | Dividida em duas: o **slogan** ("34 anos de rádio e o que a sintonia não mostra.") e o **convite** ("Vem viver comigo os bastidores da música e dos artistas, com muita energia boa!"). Aparecem juntos na capa, no rodapé e na descrição para o Google; o convite também abre a galeria. | Painel → Configurações → Slogan e Convite. |
| — | Vídeos | O briefing não cita, mas a página de vídeos do YouTube pedida no início foi mantida. Só aparece no menu se houver vídeo. | Basta não cadastrar vídeos. |

## 2. Informações que faltam (você preenche no painel → Configurações)

- [ ] **Link "Ouvir ao vivo"** da rádio ou do programa atual (decisão 6). Sem ele, o botão não aparece.
- [ ] **WhatsApp** da Bia e a mensagem padrão.
- [ ] **Redes sociais**: Instagram, Facebook, YouTube, TikTok.
- [ ] **Foto de capa** e o ponto focal dela.
- [ ] **Logotipo em arquivo** (SVG ou PNG com fundo transparente). Hoje o topo mostra o nome em texto, com "Comunicação" embaixo; com o arquivo eu coloco o símbolo do B com o balão.
- [ ] **Texto curto "sobre"** da página inicial. Hoje está um texto provisório meu.
- [ ] Conferir o **slogan** e a **cidade** (estão como "34 anos de rádio e o que a sintonia não mostra." e "São José do Rio Pardo, SP").

## 3. Material da Bia (cadastro pela contratante)

- [ ] Fotos de exemplo em estúdio, para a aprovação do layout. Enquanto não chegam, `php ferramentas/exemplo.php` cria conteúdo de exemplo com imagens coloridas.
- [ ] Marcos da linha do tempo, trabalhos atuais, recados e fotos antigas (pelo scanner).

## 4. Domínio e hospedagem

- [ ] **Domínio** (decisão 4): consultar `biapizani.com.br` no Registro.br. Não consegui consultar daqui.
- [ ] **Hospedagem** (decisão 5): escolher provedor e plano. O passo a passo está em [PUBLICACAO.md](PUBLICACAO.md).
- [ ] Depois de publicar, abrir `/admin/` imediatamente e criar o usuário e a senha.
- [ ] Ajustar os limites de envio do PHP (`upload_max_filesize` 20M, `post_max_size` 64M), como descrito em PUBLICACAO.md.

## 5. O que eu não consegui testar aqui

- **Scanner em celular de verdade.** Testei o fluxo completo em um Chrome que simula um Android, com uma foto de teste sintética (foto torta sobre mesa escura, amarelada e com sombra): detectou as bordas, endireitou, tratou e publicou. Falta validar com fotos impressas reais, na câmera de um Android intermediário e de um iPhone, e calibrar as intensidades do modo automático se precisar.
- **Áudio e vídeo do YouTube.** Meu ambiente não acessa o YouTube. O MP3 tocou normalmente.
- **Apache com `.htaccess`.** Testei com o servidor embutido do PHP. As regras de endereço amigável e de bloqueio de pastas precisam ser conferidas na hospedagem real (lista em PUBLICACAO.md).

## 6. Fora do escopo, deixado preparado

- Restauração com IA: o ponto de extensão é a função `enhance()` em `assets/js/scanner-worker.js`, e o original sem tratamento fica guardado para reprocessar.
- Pré-visualização ao vivo da câmera com contorno em tempo real (fase 2 do briefing).
- Analytics: nenhum rastreador foi incluído.
