# HeroesPet AI Content

Plugin WordPress para o site HeroesPet. Ele alterna automaticamente entre dicas, cuidados veterinários, curiosidades e notícias da semana; gera primeiro um resumo editorial; usa o Gemini somente para o artigo e a Manus exclusivamente para a imagem; redimensiona a imagem para 1280×720; salva a imagem na Biblioteca de Mídia; define a imagem destacada; cria o post com pelo menos 1.000 palavras; e preenche os principais metadados do Yoast SEO.

## Instalação

1. Copie a pasta `heroespet-ai-content` para `wp-content/plugins/` ou instale o arquivo ZIP pelo painel do WordPress.
2. Ative **HeroesPet AI Content** em **Plugins**.
3. Abra **HeroesPet AI** no menu administrativo.
4. Informe uma chave Gemini para texto e uma chave da API Manus para imagem.
5. Opcionalmente, informe uma segunda chave em **Chave Gemini alternativa para texto**. Ela deve pertencer preferencialmente a outro projeto Google Cloud/AI Studio com cota independente; quando a chave principal atingir quota, rate limit, alta demanda ou HTTP 503, o plugin tentará a alternativa automaticamente.
   A troca é imediata: o plugin não espera três tentativas da chave principal quando existe uma alternativa configurada. Depois que o artigo completo passar pela validação, a imagem é solicitada exclusivamente à Manus.
6. Selecione uma das categorias já criadas no WordPress; o post será publicado diretamente nela.
7. Selecione diário, semanal ou mensal, escolha um horário em intervalos de 15 minutos, defina o status padrão e salve.

Quando **Semanal** estiver selecionado, marque vários dias na grade **Publicações semanais** e escolha o horário de cada um. Por exemplo, é possível configurar segunda-feira às 10:00 e sexta-feira às 09:00; o plugin agenda sempre o próximo dia/horário válido.

### Geração de imagem pela Manus

Informe a chave no campo **Chave da API Manus para imagem**. A chave é enviada somente no cabeçalho `x-manus-api-key`, não é incorporada ao código e é armazenada nas opções protegidas do WordPress. O plugin cria uma tarefa privada na API Manus v2, aguarda o anexo da imagem, baixa o arquivo e continua com o redimensionamento para 1280×720 e a publicação no WordPress.

Ao usar Manus para a imagem, a chave Gemini de imagem deixa de ser necessária; a chave Gemini de texto continua necessária para o artigo.

## Observações

- O agendamento usa o WP-Cron e o fuso definido em **Configurações > Geral**. Em sites com pouco tráfego, configure um cron real do servidor para chamar `wp-cron.php`.
- Os horários são convertidos usando o fuso do WordPress e registrados no log com a próxima data calculada. Para o horário brasileiro, selecione **São Paulo** em **Configurações > Geral > Fuso horário**; o painel mostra o fuso efetivamente usado.
- Se o Gemini retornar alta demanda, HTTP 503 ou indisponibilidade temporária, o plugin agenda uma nova tentativa 15 minutos depois em um hook separado. Essa tentativa não é apagada pelo reagendamento semanal, evitando que uma falha às 08:00 faça o post desaparecer até a semana seguinte.
- Quando o Gemini retornar `Quota exceeded`, `rate limit` ou `free_tier`, o plugin não desperdiça as três tentativas de alta demanda: registra a cota atingida e agenda uma única recuperação cinco minutos depois. Se a cota do projeto for realmente diária, será necessário aguardar a renovação ou usar um projeto/modelo com cota disponível.
- Se o Gemini informar um prazo específico, como `Please retry in 9h20m`, o plugin interpreta esse prazo e agenda o retry somente depois dele, com uma margem adicional de 60 segundos. Assim, não fica repetindo requisições enquanto a cota diária ainda está bloqueada.
- Na frequência semanal, o plugin valida o dia atual antes de iniciar uma publicação automática. Eventos principais e retries fora dos dias marcados são bloqueados e redirecionados para o próximo dia configurado. O botão **Gerar e publicar agora** usa uma fila manual separada e continua permitido em qualquer dia.
- O plugin usa `gemini-3.6-flash` somente para o texto. A geração de imagens é exclusivamente pela Manus e não usa a cota de imagens do Gemini.
- A geração pela Manus depende dos limites e créditos da conta Manus. A tarefa é privada e o arquivo retornado é baixado imediatamente para a Biblioteca de Mídia; o plugin não grava a chave no repositório.
- O acompanhamento da Manus é assíncrono: o plugin salva o identificador da tarefa e faz verificações curtas pelo WP-Cron, em vez de manter uma única requisição aberta por vários minutos. Isso evita que o servidor pare em uma etapa como “7/18”.
- A categoria é carregada diretamente de `get_categories()` e precisa ser selecionada no painel; o plugin não cria uma categoria paralela nem publica em uma categoria textual aproximada.
- O preenchimento de Yoast grava foco, título SEO, meta description, Open Graph, Twitter e tipo de esquema. A cor verde final depende também da versão/configuração do Yoast e de sua análise interna.
- Antes de iniciar a tarefa de imagem, o plugin valida título, resumo, conteúdo, frase-chave, título SEO, meta description e alt text. Também exige pelo menos 1.000 palavras. Se qualquer campo vier vazio ou o JSON estiver incompleto, nenhuma imagem/post será publicado; uma nova geração é agendada automaticamente.
- O prompt exige frase-chave no primeiro parágrafo, subtítulos, metadados e texto pelo menos quatro vezes; inclui link interno e link externo confiável; limita título/meta description; define alt text com a frase-chave; e insere automaticamente a imagem gerada no topo do artigo antes do primeiro parágrafo.
- O botão **Gerar e publicar agora** respeita o status padrão: pode publicar ou salvar como rascunho.
- O painel administrativo exibe uma barra de progresso com as etapas de fila, resumo, geração paralela, validação e publicação; ele consulta o status automaticamente sem manter a requisição do navegador aberta.
- Erros temporários do Gemini, como alta demanda, indisponibilidade temporária ou HTTP 503, recebem até três tentativas automáticas com espera progressiva.
- O log mantém os 200 registros mais recentes e pode ser limpo no painel.
