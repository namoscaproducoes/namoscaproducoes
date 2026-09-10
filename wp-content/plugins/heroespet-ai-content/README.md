# HeroesPet AI Content

Plugin WordPress para o site HeroesPet. Ele alterna automaticamente entre dicas, cuidados veterinários, curiosidades e notícias da semana; gera primeiro um resumo editorial; solicita em paralelo o artigo e a imagem ao Google Gemini; redimensiona a imagem para 1280×720; salva a imagem na Biblioteca de Mídia; define a imagem destacada; cria o post com pelo menos 1.000 palavras; e preenche os principais metadados do Yoast SEO.

## Instalação

1. Copie a pasta `heroespet-ai-content` para `wp-content/plugins/` ou instale o arquivo ZIP pelo painel do WordPress.
2. Ative **HeroesPet AI Content** em **Plugins**.
3. Abra **HeroesPet AI** no menu administrativo.
4. Informe uma chave Gemini para texto e uma chave Gemini para imagem. É possível usar chaves diferentes ou a mesma chave com permissões/modelos compatíveis.
5. Selecione uma das categorias já criadas no WordPress; o post será publicado diretamente nela.
6. Selecione diário, semanal ou mensal, escolha um horário em intervalos de 15 minutos, defina o status padrão e salve.

## Observações

- O agendamento usa o WP-Cron e o fuso definido em **Configurações > Geral**. Em sites com pouco tráfego, configure um cron real do servidor para chamar `wp-cron.php`.
- O plugin usa `gemini-3.6-flash` para o texto e `gemini-3.1-flash-image` (Nano Banana 2) para a imagem por padrão; a imagem usa a Interactions API oficial. Configurações antigas de texto e imagem são migradas automaticamente para esses modelos.
- A geração de imagem depende de cota habilitada no projeto Google. Se o Google retornar `quota ... limit: 0`, isso não é um erro do WordPress: é necessário habilitar faturamento/um tier compatível ou escolher um projeto com cota de imagem disponível no Google AI Studio. Consulte [limites do Gemini](https://ai.google.dev/gemini-api/docs/rate-limits) e [geração de imagens](https://ai.google.dev/gemini-api/docs/image-generation).
- A categoria é carregada diretamente de `get_categories()` e precisa ser selecionada no painel; o plugin não cria uma categoria paralela nem publica em uma categoria textual aproximada.
- A chamada paralela usa cURL multi quando disponível. Caso o servidor não tenha a extensão cURL, o plugin usa automaticamente o transporte HTTP nativo do WordPress.
- O preenchimento de Yoast grava foco, título SEO, meta description, Open Graph, Twitter e tipo de esquema. A cor verde final depende também da versão/configuração do Yoast e de sua análise interna.
- O botão **Gerar e publicar agora** respeita o status padrão: pode publicar ou salvar como rascunho.
- O painel administrativo exibe uma barra de progresso com as etapas de fila, resumo, geração paralela, validação e publicação; ele consulta o status automaticamente sem manter a requisição do navegador aberta.
- Erros temporários do Gemini, como alta demanda, indisponibilidade temporária ou HTTP 503, recebem até três tentativas automáticas com espera progressiva.
- O log mantém os 200 registros mais recentes e pode ser limpo no painel.
