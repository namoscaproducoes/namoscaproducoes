# HeroesPet AI Content

Plugin WordPress para o site HeroesPet. Ele alterna automaticamente entre dicas, cuidados veterinários, curiosidades e notícias da semana; gera primeiro um resumo editorial; solicita em paralelo o artigo e a imagem ao Google Gemini; redimensiona a imagem para 1280×720; salva a imagem na Biblioteca de Mídia; define a imagem destacada; cria o post com pelo menos 1.000 palavras; e preenche os principais metadados do Yoast SEO.

## Instalação

1. Copie a pasta `heroespet-ai-content` para `wp-content/plugins/` ou instale o arquivo ZIP pelo painel do WordPress.
2. Ative **HeroesPet AI Content** em **Plugins**.
3. Abra **HeroesPet AI** no menu administrativo.
4. Informe uma chave Gemini para texto e uma chave Gemini para imagem. É possível usar chaves diferentes ou a mesma chave com permissões/modelos compatíveis.
5. Selecione uma das categorias já criadas no WordPress; o post será publicado diretamente nela.
6. Selecione diário, semanal ou mensal, escolha um horário em intervalos de 15 minutos, defina o status padrão e salve.

### Geração de imagem pela Manus

No campo **Provedor de imagens**, escolha **Manus API** e informe a chave em **Chave da API Manus para imagem**. A chave é enviada somente no cabeçalho `x-manus-api-key`, não é incorporada ao código e é armazenada nas opções protegidas do WordPress. O plugin cria uma tarefa privada na API Manus v2, aguarda o anexo da imagem, baixa o arquivo e continua com o redimensionamento para 1280×720 e a publicação no WordPress.

Ao usar Manus para a imagem, a chave Gemini de imagem deixa de ser necessária; a chave Gemini de texto continua necessária para o artigo.

## Observações

- O agendamento usa o WP-Cron e o fuso definido em **Configurações > Geral**. Em sites com pouco tráfego, configure um cron real do servidor para chamar `wp-cron.php`.
- O plugin usa `gemini-3.6-flash` para o texto e `gemini-3.1-flash-image` (Nano Banana 2) para a imagem por padrão; a imagem usa a Interactions API oficial. Configurações antigas de texto e imagem são migradas automaticamente para esses modelos.
- A geração de imagem depende de cota habilitada no projeto Google. Se o Google retornar `quota ... limit: 0`, isso não é um erro do WordPress: é necessário habilitar faturamento/um tier compatível ou escolher um projeto com cota de imagem disponível no Google AI Studio. Consulte [limites do Gemini](https://ai.google.dev/gemini-api/docs/rate-limits) e [geração de imagens](https://ai.google.dev/gemini-api/docs/image-generation).
- A geração pela Manus depende dos limites e créditos da conta Manus. A tarefa é privada e o arquivo retornado é baixado imediatamente para a Biblioteca de Mídia; o plugin não grava a chave no repositório.
- O acompanhamento da Manus é assíncrono: o plugin salva o identificador da tarefa e faz verificações curtas pelo WP-Cron, em vez de manter uma única requisição aberta por vários minutos. Isso evita que o servidor pare em uma etapa como “7/18”.
- A categoria é carregada diretamente de `get_categories()` e precisa ser selecionada no painel; o plugin não cria uma categoria paralela nem publica em uma categoria textual aproximada.
- A chamada paralela usa cURL multi quando disponível. Caso o servidor não tenha a extensão cURL, o plugin usa automaticamente o transporte HTTP nativo do WordPress.
- O preenchimento de Yoast grava foco, título SEO, meta description, Open Graph, Twitter e tipo de esquema. A cor verde final depende também da versão/configuração do Yoast e de sua análise interna.
- O prompt exige frase-chave no primeiro parágrafo, subtítulos, metadados e texto pelo menos quatro vezes; inclui link interno e link externo confiável; limita título/meta description; define alt text com a frase-chave; e insere automaticamente a imagem gerada no topo do artigo antes do primeiro parágrafo.
- O botão **Gerar e publicar agora** respeita o status padrão: pode publicar ou salvar como rascunho.
- O painel administrativo exibe uma barra de progresso com as etapas de fila, resumo, geração paralela, validação e publicação; ele consulta o status automaticamente sem manter a requisição do navegador aberta.
- Erros temporários do Gemini, como alta demanda, indisponibilidade temporária ou HTTP 503, recebem até três tentativas automáticas com espera progressiva.
- O log mantém os 200 registros mais recentes e pode ser limpo no painel.
