<?php
/**
 * Plugin Name: HeroesPet AI Content
 * Plugin URI: https://heroespet.com.br
 * Description: Gera, agenda e publica conteúdos pet/veterinários com Google Gemini, imagem destacada 1280x720 e campos SEO Yoast.
 * Version: 1.4.0
 * Author: HeroesPet
 * Author URI: https://heroespet.com.br
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: heroespet-ai-content
 */

if (!defined('ABSPATH')) { exit; }

define('HEROESPET_AI_VERSION', '1.4.0');
define('HEROESPET_AI_OPTION', 'heroespet_ai_options');
define('HEROESPET_AI_LOG_OPTION', 'heroespet_ai_logs');
define('HEROESPET_AI_CRON_HOOK', 'heroespet_ai_generate_event');

register_activation_hook(__FILE__, 'heroespet_ai_activate');
register_deactivation_hook(__FILE__, 'heroespet_ai_deactivate');
add_action('admin_menu', 'heroespet_ai_admin_menu');
add_action('admin_init', 'heroespet_ai_register_settings');
add_action('admin_post_heroespet_ai_generate_now', 'heroespet_ai_generate_now');
add_action('admin_post_heroespet_ai_clear_logs', 'heroespet_ai_clear_logs');
add_action('wp_ajax_heroespet_ai_progress', 'heroespet_ai_progress_ajax');
add_action(HEROESPET_AI_CRON_HOOK, 'heroespet_ai_cron_generate');
add_action('heroespet_ai_manus_poll_event', 'heroespet_ai_manus_poll');
add_action('admin_notices', 'heroespet_ai_admin_notice');

function heroespet_ai_defaults() {
    return array(
        'gemini_text_key' => '',
        'manus_api_key' => '',
        'text_model' => 'gemini-3.6-flash',
        'frequency' => 'daily',
        'publish_time' => '08:00',
        'weekly_slots' => array(1 => '10:00'),
        'prompt' => "Você é um jornalista especializado em mundo pet e medicina veterinária. Escreva em português do Brasil, com linguagem acolhedora, precisa e responsável. Alterne os temas automaticamente entre dicas práticas, cuidados veterinários, curiosidades e notícias relevantes da semana. Nunca invente diagnósticos, estatísticas ou fontes. Quando falar de saúde, inclua orientação para procurar um médico-veterinário. Produza título, subtítulo, artigo completo e dados SEO.",
        'category_id' => 0,
        'status' => 'publish',
    );
}

function heroespet_ai_get_options() {
    $options = wp_parse_args((array) get_option(HEROESPET_AI_OPTION, array()), heroespet_ai_defaults());
    if (($options['text_model'] ?? '') === 'gemini-2.5-flash') {
        $options['text_model'] = 'gemini-3.6-flash';
        update_option(HEROESPET_AI_OPTION, $options, false);
    }
    return $options;
}

function heroespet_ai_activate() {
    if (!get_option(HEROESPET_AI_OPTION)) { add_option(HEROESPET_AI_OPTION, heroespet_ai_defaults()); }
    heroespet_ai_reschedule();
}

function heroespet_ai_deactivate() {
    wp_clear_scheduled_hook(HEROESPET_AI_CRON_HOOK);
}

function heroespet_ai_register_settings() {
    register_setting('heroespet_ai_settings', HEROESPET_AI_OPTION, array(
        'type' => 'array', 'sanitize_callback' => 'heroespet_ai_sanitize_options', 'default' => heroespet_ai_defaults(),
    ));
    add_settings_section('heroespet_ai_main', 'Configuração do gerador', '__return_false', 'heroespet-ai');
    $fields = array(
        'gemini_text_key' => array('Chave Gemini para texto', 'password'),
        'manus_api_key' => array('Chave da API Manus para imagem', 'password'),
        'text_model' => array('Modelo de texto', 'text'),
        'prompt' => array('Prompt editável do artigo', 'textarea'),
        'category_id' => array('Categoria dos posts', 'category'),
    );
    foreach ($fields as $key => $data) {
        add_settings_field($key, esc_html($data[0]), 'heroespet_ai_render_field', 'heroespet-ai', 'heroespet_ai_main', array('key' => $key, 'type' => $data[1]));
    }
}

function heroespet_ai_sanitize_options($input) {
    $old = heroespet_ai_get_options();
    $out = heroespet_ai_defaults();
    foreach (array('gemini_text_key', 'manus_api_key', 'text_model') as $key) {
        $value = isset($input[$key]) ? sanitize_text_field($input[$key]) : '';
        $out[$key] = ($value === '' && in_array($key, array('gemini_text_key', 'manus_api_key'), true)) ? ($old[$key] ?? '') : $value;
    }
    $legacy_category = !empty($old['category']) ? get_category_by_slug(sanitize_title($old['category'])) : null;
    $out['category_id'] = absint($input['category_id'] ?? ($old['category_id'] ?? ($legacy_category ? $legacy_category->term_id : 0)));
    $out['weekly_slots'] = array();
    $enabled = isset($input['weekly_enabled']) && is_array($input['weekly_enabled']) ? $input['weekly_enabled'] : array();
    $submitted_slots = isset($input['weekly_slots']) && is_array($input['weekly_slots']) ? $input['weekly_slots'] : array();
    foreach (range(1, 7) as $day) if (!empty($enabled[$day]) && preg_match('/^(?:[01]\d|2[0-3]):(?:00|15|30|45)$/', $submitted_slots[$day] ?? '')) $out['weekly_slots'][$day] = $submitted_slots[$day];
    $out['prompt'] = isset($input['prompt']) ? sanitize_textarea_field($input['prompt']) : $out['prompt'];
    $out['frequency'] = in_array(($input['frequency'] ?? ''), array('daily', 'weekly', 'monthly'), true) ? $input['frequency'] : 'daily';
    $out['publish_time'] = preg_match('/^(?:[01]\d|2[0-3]):(?:00|15|30|45)$/', $input['publish_time'] ?? '') ? $input['publish_time'] : '08:00';
    $out['status'] = ($input['status'] ?? 'publish') === 'draft' ? 'draft' : 'publish';
    heroespet_ai_reschedule($out);
    return $out;
}

function heroespet_ai_render_field($args) {
    $opts = heroespet_ai_get_options(); $key = $args['key']; $type = $args['type'];
    $value = $opts[$key] ?? '';
    if ($type === 'category') {
        $categories = get_categories(array('hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
        echo '<select name="' . esc_attr(HEROESPET_AI_OPTION) . '[category_id]" required><option value="">Selecione uma categoria</option>';
        foreach ($categories as $category) printf('<option value="%d" %s>%s</option>', (int) $category->term_id, selected((int) $value, (int) $category->term_id, false), esc_html($category->name));
        echo '</select><p class="description">A publicação será vinculada diretamente à categoria escolhida entre as categorias existentes no WordPress.</p>';
    } elseif ($type === 'textarea') {
        printf('<textarea class="large-text" rows="9" name="%s[%s]">%s</textarea><p class="description">O prompt é combinado com o tema alternado e deve orientar o texto com responsabilidade editorial.</p>', esc_attr(HEROESPET_AI_OPTION), esc_attr($key), esc_textarea($value));
    } else {
        printf('<input class="regular-text" type="%s" name="%s[%s]" value="%s" autocomplete="off">', esc_attr($type), esc_attr(HEROESPET_AI_OPTION), esc_attr($key), esc_attr($value));
        if (strpos($key, '_key') !== false) echo '<p class="description">A chave é armazenada nas opções do WordPress e nunca é exibida no painel.</p>';
    }
}

function heroespet_ai_admin_menu() {
    add_menu_page('HeroesPet AI', 'HeroesPet AI', 'manage_options', 'heroespet-ai', 'heroespet_ai_settings_page', 'dashicons-pets', 58);
}

function heroespet_ai_settings_page() {
    if (!current_user_can('manage_options')) return;
    $opts = heroespet_ai_get_options(); $logs = get_option(HEROESPET_AI_LOG_OPTION, array());
    $times = array(); for ($h = 0; $h < 24; $h++) { foreach (array(0,15,30,45) as $m) $times[] = sprintf('%02d:%02d', $h, $m); }
    $weekdays = array(1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo');
    ?>
    <div class="wrap">
      <h1>HeroesPet AI Content <small style="font-size:13px;color:#666">v<?php echo esc_html(HEROESPET_AI_VERSION); ?></small></h1>
      <p>Gere conteúdos editoriais sobre mundo pet e veterinário, com imagem profissional, mídia e SEO Yoast.</p>
      <?php settings_errors(); ?>
      <form method="post" action="options.php">
        <?php settings_fields('heroespet_ai_settings'); do_settings_sections('heroespet-ai'); ?>
        <table class="form-table"><tr><th>Frequência</th><td><select name="<?php echo esc_attr(HEROESPET_AI_OPTION); ?>[frequency]"><option value="daily" <?php selected($opts['frequency'], 'daily'); ?>>Diário</option><option value="weekly" <?php selected($opts['frequency'], 'weekly'); ?>>Semanal</option><option value="monthly" <?php selected($opts['frequency'], 'monthly'); ?>>Mensal</option></select></td></tr>
        <tr><th>Horário de publicação</th><td><select name="<?php echo esc_attr(HEROESPET_AI_OPTION); ?>[publish_time]"><?php foreach ($times as $time) printf('<option value="%s" %s>%s</option>', esc_attr($time), selected($opts['publish_time'], $time, false), esc_html($time)); ?></select><p class="description">Usado para Diário e Mensal. O fuso é o definido em Configurações &gt; Geral.</p></td></tr>
        <tr><th>Publicações semanais</th><td><p>Marque quantos dias quiser e escolha um horário individual para cada dia. Esta grade é usada quando a frequência está definida como <strong>Semanal</strong>.</p><?php foreach ($weekdays as $day => $label) { $slot = $opts['weekly_slots'][$day] ?? ''; echo '<label style="display:block;margin:7px 0"><input type="checkbox" name="' . esc_attr(HEROESPET_AI_OPTION) . '[weekly_enabled][' . (int) $day . ']" value="1" ' . checked($slot !== '', true, false) . '> ' . esc_html($label) . ' <select name="' . esc_attr(HEROESPET_AI_OPTION) . '[weekly_slots][' . (int) $day . ']">'; foreach ($times as $time) printf('<option value="%s" %s>%s</option>', esc_attr($time), selected($slot ?: '08:00', $time, false), esc_html($time)); echo '</select></label>'; } ?></td></tr>
        <tr><th>Status padrão</th><td><select name="<?php echo esc_attr(HEROESPET_AI_OPTION); ?>[status]"><option value="publish" <?php selected($opts['status'], 'publish'); ?>>Publicar automaticamente</option><option value="draft" <?php selected($opts['status'], 'draft'); ?>>Salvar como rascunho</option></select></td></tr></table>
        <?php submit_button('Salvar configurações'); ?>
      </form>
      <hr><h2>Publicar agora</h2><p>O processo cria primeiro um resumo editorial; depois solicita simultaneamente o artigo e a imagem ao Gemini, salva a imagem na biblioteca e define a imagem destacada.</p>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('heroespet_ai_generate_now'); ?><input type="hidden" name="action" value="heroespet_ai_generate_now"><?php submit_button('Gerar e publicar agora', 'primary'); ?></form>
      <div id="heroespet-ai-progress" style="display:none;max-width:720px;margin:18px 0;padding:16px;border:1px solid #dcdcde;border-radius:8px;background:#fff"><strong id="heroespet-ai-progress-title">Aguardando...</strong><div style="height:12px;background:#e2e8f0;border-radius:8px;overflow:hidden;margin:12px 0 8px"><div id="heroespet-ai-progress-bar" style="height:100%;width:0%;background:#2271b1;transition:width .5s ease"></div></div><span id="heroespet-ai-progress-message" style="color:#50575e">Acompanhe o processamento nesta tela.</span></div>
      <script>(function(){const box=document.getElementById('heroespet-ai-progress'),bar=document.getElementById('heroespet-ai-progress-bar'),title=document.getElementById('heroespet-ai-progress-title'),message=document.getElementById('heroespet-ai-progress-message');if(!box)return;function poll(){fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php?action=heroespet_ai_progress&_ajax_nonce=' . wp_create_nonce('heroespet_ai_progress'))); ?>,{credentials:'same-origin'}).then(r=>r.json()).then(d=>{if(!d.success||!d.data)return;const p=d.data;if(p.status&&p.status!=='idle'){box.style.display='block';bar.style.width=(p.percent||0)+'%';title.textContent=p.title||'Processando...';message.textContent=p.message||'';}if(['success','error'].indexOf(p.status)>=0){bar.style.background=p.status==='success'?'#00a32a':'#d63638';}else{setTimeout(poll,3000);}}).catch(function(){setTimeout(poll,5000);});}poll();})();</script>
      <hr><h2>Log de operações</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('heroespet_ai_clear_logs'); ?><input type="hidden" name="action" value="heroespet_ai_clear_logs"><?php submit_button('Limpar log', 'secondary', 'submit', array('onclick' => "return confirm('Limpar todo o log?');")); ?></form>
      <div style="max-height:420px;overflow:auto;margin-top:12px"><table class="widefat striped"><thead><tr><th>Data</th><th>Nível</th><th>Mensagem</th></tr></thead><tbody><?php if (!$logs) echo '<tr><td colspan="3">Nenhum registro.</td></tr>'; foreach (array_reverse($logs) as $log) printf('<tr><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html($log['time']), esc_html($log['level']), esc_html($log['message'])); ?></tbody></table></div>
    </div>
    <?php
}

function heroespet_ai_reschedule($options = null) {
    $opts = $options ?: heroespet_ai_get_options();
    wp_clear_scheduled_hook(HEROESPET_AI_CRON_HOOK);
    $timestamp = heroespet_ai_next_timestamp($opts['publish_time'], $opts['frequency'], $opts['weekly_slots'] ?? array());
    if ($timestamp) wp_schedule_single_event($timestamp, HEROESPET_AI_CRON_HOOK);
}

function heroespet_ai_next_timestamp($time, $frequency, $weekly_slots = array()) {
    if ($frequency === 'weekly') {
        $slots = $weekly_slots ?: (heroespet_ai_get_options()['weekly_slots'] ?? array());
        if ($slots) {
            $now = current_time('timestamp'); $best = 0; $today = (int) date('N', $now);
            foreach ($slots as $day => $slot) { list($hour, $minute) = array_map('intval', explode(':', $slot)); $delta = ((int) $day - $today + 7) % 7; $candidate = mktime($hour, $minute, 0, (int) date('n', $now), (int) date('j', $now) + $delta, (int) date('Y', $now)); if ($candidate <= $now) $candidate = strtotime('+7 days', $candidate); if (!$best || $candidate < $best) $best = $candidate; }
            return $best;
        }
    }
    list($hour, $minute) = array_map('intval', explode(':', $time));
    $now = current_time('timestamp'); $candidate = mktime($hour, $minute, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now));
    if ($candidate <= $now) $candidate = strtotime('+1 day', $candidate);
    if ($frequency === 'weekly') $candidate = strtotime('+6 days', $candidate);
    if ($frequency === 'monthly') $candidate = strtotime('+1 month', $candidate);
    return $candidate;
}

function heroespet_ai_cron_generate() {
    if (get_transient('heroespet_ai_generation_lock')) {
        heroespet_ai_log('WARNING', 'Geração ignorada porque já existe outra execução em andamento.');
        heroespet_ai_reschedule();
        return;
    }
    set_transient('heroespet_ai_generation_lock', 1, 15 * MINUTE_IN_SECONDS);
    heroespet_ai_generate_content(false);
    delete_transient('heroespet_ai_generation_lock');
    heroespet_ai_reschedule();
}

function heroespet_ai_generate_now() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('heroespet_ai_generate_now');
    if (get_transient('heroespet_ai_generation_lock')) {
        $url = add_query_arg(array('page' => 'heroespet-ai', 'heroespet_ai_result' => 'error', 'heroespet_ai_message' => rawurlencode('Já existe uma geração em andamento. Aguarde o log ser atualizado.')), admin_url('admin.php'));
        wp_safe_redirect($url); exit;
    }
    wp_schedule_single_event(time() + 5, HEROESPET_AI_CRON_HOOK);
    heroespet_ai_set_progress('queued', 5, 'Publicação na fila', 'A geração começará em segundo plano em alguns segundos.');
    heroespet_ai_log('INFO', 'Geração manual enfileirada para execução em segundo plano.');
    $url = add_query_arg(array('page' => 'heroespet-ai', 'heroespet_ai_result' => 'success', 'heroespet_ai_message' => rawurlencode('Geração enfileirada. O artigo será criado em segundo plano; acompanhe o log nesta tela.')), admin_url('admin.php'));
    wp_safe_redirect($url); exit;
}

function heroespet_ai_clear_logs() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('heroespet_ai_clear_logs'); delete_option(HEROESPET_AI_LOG_OPTION);
    wp_safe_redirect(admin_url('admin.php?page=heroespet-ai')); exit;
}

function heroespet_ai_set_progress($status, $percent, $title, $message) {
    set_transient('heroespet_ai_progress', array('status' => $status, 'percent' => (int) $percent, 'title' => $title, 'message' => $message, 'updated' => time()), 30 * MINUTE_IN_SECONDS);
}

function heroespet_ai_progress_ajax() {
    check_ajax_referer('heroespet_ai_progress');
    if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Sem permissão.'), 403);
    $pending = get_option('heroespet_ai_manus_pending', array());
    if (!empty($pending['task_id']) && !get_transient('heroespet_ai_manus_ajax_lock')) {
        set_transient('heroespet_ai_manus_ajax_lock', 1, 8);
        heroespet_ai_manus_poll();
        delete_transient('heroespet_ai_manus_ajax_lock');
    }
    wp_send_json_success(get_transient('heroespet_ai_progress') ?: array('status' => 'idle', 'percent' => 0, 'title' => '', 'message' => ''));
}

function heroespet_ai_admin_notice() {
    if (($_GET['page'] ?? '') !== 'heroespet-ai' || empty($_GET['heroespet_ai_result'])) return;
    $class = $_GET['heroespet_ai_result'] === 'success' ? 'notice-success' : 'notice-error';
    printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html(rawurldecode($_GET['heroespet_ai_message'] ?? '')));
}

function heroespet_ai_log($level, $message) {
    $logs = get_option(HEROESPET_AI_LOG_OPTION, array());
    $logs[] = array('time' => current_time('mysql'), 'level' => $level, 'message' => $message);
    if (count($logs) > 200) $logs = array_slice($logs, -200);
    update_option(HEROESPET_AI_LOG_OPTION, $logs, false);
}

function heroespet_ai_generate_content($manual = false) {
    $opts = heroespet_ai_get_options();
    heroespet_ai_set_progress('running', 10, 'Preparando publicação', 'Validando configurações e escolhendo o próximo tema.');
    if (!$opts['gemini_text_key'] || empty($opts['manus_api_key'])) { $message = 'Configure a chave Gemini para texto e a chave da API Manus para imagem.'; heroespet_ai_set_progress('error', 100, 'Configuração incompleta', $message); heroespet_ai_log('ERROR', $message); return array('ok' => false, 'message' => $message); }
    $topic = heroespet_ai_pick_topic();
    heroespet_ai_log('INFO', 'Início da geração: ' . $topic['label']);
    heroespet_ai_set_progress('running', 20, 'Criando resumo editorial', 'O Gemini está preparando o briefing que será usado no texto e na imagem.');
    $brief = heroespet_ai_call_text_retry($opts['gemini_text_key'], $opts['text_model'], 'Crie apenas um resumo editorial de 45 a 70 palavras para um conteúdo sobre ' . $topic['label'] . '. Inclua o ângulo principal, público e cuidados editoriais. Retorne somente o resumo.');
    if (is_wp_error($brief)) { heroespet_ai_set_progress('error', 100, 'Não foi possível criar o resumo', $brief->get_error_message()); heroespet_ai_log('ERROR', 'Falha ao criar resumo: ' . $brief->get_error_message()); return array('ok' => false, 'message' => $brief->get_error_message()); }
    heroespet_ai_log('INFO', 'Resumo criado. Solicitando artigo ao Gemini e imagem exclusivamente à Manus.');
    heroespet_ai_set_progress('running', 45, 'Gerando artigo e imagem', 'O artigo será gerado pelo Gemini e a imagem pela Manus.');
    $article_prompt = $opts['prompt'] . "\n\nTema desta publicação: " . $topic['label'] . "\nResumo editorial: " . $brief . "\n\nREQUISITOS OBRIGATÓRIOS DE SEO E FORMATAÇÃO:\n- Retorne JSON válido com as chaves title, excerpt, content, focus_keyword, seo_title, meta_description, image_alt.\n- O conteúdo deve ter no mínimo 1000 palavras, usar HTML sem markdown fences e conter subtítulos <h2> e <h3>.\n- Escolha uma frase-chave específica de 2 a 5 palavras em focus_keyword e use exatamente essa frase de forma natural pelo menos 4 vezes no texto.\n- Use a frase-chave no primeiro parágrafo, em pelo menos um <h2> ou <h3>, no seo_title, na meta_description e no image_alt.\n- O seo_title deve ter no máximo 55 caracteres e a meta_description entre 120 e 155 caracteres, contendo a frase-chave.\n- Inclua pelo menos 1 link interno para " . esc_url(home_url('/')) . " e pelo menos 1 link externo confiável relacionado a saúde animal, usando elementos HTML <a href=\"...\">.\n- Não invente URLs internas: use somente o endereço interno informado.\n- A imagem gerada será inserida automaticamente no topo do artigo, antes do primeiro parágrafo; não insira outra imagem no conteúdo.\n- Retorne somente o objeto JSON.";
    $image_prompt = "Fotografia profissional editorial, realista e natural, relacionada ao seguinte conteúdo para um portal pet brasileiro: " . $brief . ". Pode conter pessoas e pets ou apenas pets conforme fizer sentido. Composição horizontal para capa de artigo, iluminação profissional, sem texto, sem logotipos, sem marca d'água, aspecto 16:9.";
    $article = heroespet_ai_call_text_retry($opts['gemini_text_key'], $opts['text_model'], $article_prompt);
    if (is_wp_error($article)) { heroespet_ai_set_progress('error', 100, 'Falha no artigo', $article->get_error_message()); heroespet_ai_log('ERROR', 'Falha no artigo: ' . $article->get_error_message()); return array('ok' => false, 'message' => 'Falha na geração do artigo.'); }
    $data = heroespet_ai_parse_json(array('candidates' => array(array('content' => array('parts' => array(array('text' => $article)))))));
    $manus_task = heroespet_ai_manus_create_task($opts['manus_api_key'], $image_prompt);
    if (is_wp_error($manus_task)) { heroespet_ai_set_progress('error', 100, 'Falha ao iniciar imagem Manus', $manus_task->get_error_message()); heroespet_ai_log('ERROR', 'Falha ao iniciar imagem Manus: ' . $manus_task->get_error_message()); return array('ok' => false, 'message' => $manus_task->get_error_message()); }
    update_option('heroespet_ai_manus_pending', array('task_id' => $manus_task, 'api_key' => $opts['manus_api_key'], 'data' => $data, 'topic' => $topic, 'opts' => $opts), false);
    heroespet_ai_set_progress('running', 50, 'Imagem Manus em processamento', 'A tarefa foi criada; o acompanhamento continuará em execuções curtas.');
    heroespet_ai_log('INFO', 'Tarefa Manus ' . $manus_task . ' criada; polling assíncrono agendado.');
    wp_schedule_single_event(time() + 10, 'heroespet_ai_manus_poll_event');
    return array('ok' => true, 'pending' => true, 'message' => 'Imagem Manus em processamento.');
    heroespet_ai_set_progress('running', 72, 'Validando conteúdo', 'Conferindo JSON, quantidade de palavras e dados da imagem.');
    if (!$data || empty($data['content']) || str_word_count(wp_strip_all_tags($data['content'])) < 1000) { heroespet_ai_set_progress('error', 100, 'Conteúdo inválido', 'O artigo não atingiu 1.000 palavras ou não veio em JSON.'); heroespet_ai_log('ERROR', 'O artigo retornado não atingiu 1000 palavras ou não veio em JSON.'); return array('ok' => false, 'message' => 'O artigo retornado não atingiu 1000 palavras.'); }
    if (!$image) { heroespet_ai_set_progress('error', 100, 'Imagem não recebida', 'O Gemini não retornou dados de imagem.'); heroespet_ai_log('ERROR', 'A resposta do Gemini não continha dados de imagem.'); return array('ok' => false, 'message' => 'O Gemini não retornou dados de imagem.'); }
    heroespet_ai_set_progress('running', 88, 'Publicando no WordPress', 'Salvando a imagem na mídia, definindo destaque e preenchendo o Yoast.');
    $post_id = heroespet_ai_create_post($data, $image, $topic, $opts);
    if (is_wp_error($post_id)) { heroespet_ai_set_progress('error', 100, 'Falha na publicação', $post_id->get_error_message()); heroespet_ai_log('ERROR', 'Falha ao criar post: ' . $post_id->get_error_message()); return array('ok' => false, 'message' => $post_id->get_error_message()); }
    heroespet_ai_log('SUCCESS', 'Post #' . $post_id . ' criado com artigo, mídia, imagem destacada e campos Yoast.');
    heroespet_ai_set_progress('success', 100, 'Publicação concluída', 'Post #' . $post_id . ' criado com sucesso.');
    return array('ok' => true, 'message' => 'Post #' . $post_id . ' criado com sucesso.');
}

function heroespet_ai_pick_topic() {
    $topics = array(
        array('label' => 'dicas práticas de bem-estar e rotina para cães e gatos', 'key' => 'dicas'),
        array('label' => 'cuidados preventivos e orientação veterinária', 'key' => 'cuidados'),
        array('label' => 'curiosidades sobre comportamento, espécies e saúde animal', 'key' => 'curiosidades'),
        array('label' => 'notícias e tendências da semana no universo pet e veterinário', 'key' => 'noticias'),
    );
    $last = get_option('heroespet_ai_last_topic', -1); $index = ((int) $last + 1) % count($topics); update_option('heroespet_ai_last_topic', $index, false); return $topics[$index];
}

function heroespet_ai_request_payload($key, $model, $prompt) {
    return array(
        'url' => 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key),
        'headers' => array('Content-Type: application/json'),
        'body' => array(
            'contents' => array(array('role' => 'user', 'parts' => array(array('text' => $prompt)))),
            'generationConfig' => array('responseMimeType' => 'application/json', 'temperature' => 0.7, 'maxOutputTokens' => 8192),
        ),
    );
}

function heroespet_ai_call_text_retry($key, $model, $prompt) {
    $last = null;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $last = heroespet_ai_call_text($key, $model, $prompt);
        if (!is_wp_error($last)) return $last;
        $message = strtolower($last->get_error_message());
        if (strpos($message, 'high demand') === false && strpos($message, 'temporarily') === false && strpos($message, '503') === false) return $last;
        heroespet_ai_log('WARNING', 'Gemini em alta demanda; nova tentativa ' . $attempt . ' de 3.');
        if ($attempt < 3) sleep(4 * $attempt);
    }
    return $last;
}

function heroespet_ai_call_text($key, $model, $prompt) {
    $payload = heroespet_ai_request_payload($key, $model, $prompt); $response = wp_remote_post($payload['url'], array('timeout' => 120, 'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode($payload['body'])));
    if (is_wp_error($response)) return $response; $code = wp_remote_retrieve_response_code($response); $body = json_decode(wp_remote_retrieve_body($response), true); if ($code >= 300) return new WP_Error('gemini_http', $body['error']['message'] ?? 'Erro HTTP do Gemini.'); return trim($body['candidates'][0]['content']['parts'][0]['text'] ?? '');
}

function heroespet_ai_manus_create_task($api_key, $prompt) {
    $headers = array('Content-Type' => 'application/json', 'x-manus-api-key' => $api_key);
    $body = array('message' => array('content' => "Gere uma única imagem fotográfica profissional para capa de artigo, sem texto, sem logotipos e sem marca d'água. Use composição horizontal 16:9 e entregue a imagem como anexo da resposta. Tema: " . $prompt), 'interactive_mode' => false, 'hide_in_task_list' => true, 'share_visibility' => 'private', 'agent_profile' => 'manus-1.6-lite', 'title' => 'HeroesPet — imagem editorial');
    $response = wp_remote_post('https://api.manus.ai/v2/task.create', array('timeout' => 45, 'headers' => $headers, 'body' => wp_json_encode($body)));
    if (is_wp_error($response)) return new WP_Error('manus_http', $response->get_error_message());
    $data = json_decode(wp_remote_retrieve_body($response), true); $code = wp_remote_retrieve_response_code($response);
    if ($code >= 300 || empty($data['ok'])) return new WP_Error('manus_create', $data['error']['message'] ?? 'A API Manus recusou a tarefa.');
    $task_id = $data['task_id'] ?? ($data['task']['id'] ?? ($data['data']['task_id'] ?? ''));
    return $task_id ? $task_id : new WP_Error('manus_task', 'A API Manus não retornou o identificador da tarefa.');
}

function heroespet_ai_manus_poll() {
    $pending = get_option('heroespet_ai_manus_pending', array());
    if (empty($pending['task_id']) || empty($pending['api_key'])) return;
    $poll = wp_remote_get('https://api.manus.ai/v2/task.listMessages?task_id=' . rawurlencode($pending['task_id']) . '&order=desc&limit=50', array('timeout' => 45, 'headers' => array('x-manus-api-key' => $pending['api_key'])));
    if (is_wp_error($poll)) { wp_schedule_single_event(time() + 20, 'heroespet_ai_manus_poll_event'); return; }
    $messages = json_decode(wp_remote_retrieve_body($poll), true); $attachment = null;
    foreach (($messages['messages'] ?? array()) as $event) {
        if (!empty($event['error_message']['content'])) { delete_option('heroespet_ai_manus_pending'); heroespet_ai_set_progress('error', 100, 'Falha na imagem Manus', $event['error_message']['content']); heroespet_ai_log('ERROR', 'Falha na tarefa Manus: ' . $event['error_message']['content']); return; }
        foreach (($event['assistant_message']['attachments'] ?? array()) as $item) if (!empty($item['url']) && in_array(($item['type'] ?? ''), array('image', 'file'), true)) $attachment = $item;
    }
    if (!$attachment) { heroespet_ai_set_progress('running', 55, 'Gerando imagem pela Manus', 'A tarefa Manus ainda está processando; o próximo acompanhamento será automático.'); wp_schedule_single_event(time() + 20, 'heroespet_ai_manus_poll_event'); return; }
    $download = wp_remote_get($attachment['url'], array('timeout' => 90));
    if (is_wp_error($download) || !wp_remote_retrieve_body($download)) { wp_schedule_single_event(time() + 20, 'heroespet_ai_manus_poll_event'); return; }
    $image = array('data' => wp_remote_retrieve_body($download), 'mime' => $attachment['content_type'] ?? 'image/png');
    heroespet_ai_set_progress('running', 88, 'Publicando no WordPress', 'Imagem Manus recebida; salvando mídia, destaque e SEO.');
    $post_id = heroespet_ai_create_post($pending['data'], $image, $pending['topic'], $pending['opts']);
    delete_option('heroespet_ai_manus_pending');
    if (is_wp_error($post_id)) { heroespet_ai_set_progress('error', 100, 'Falha na publicação', $post_id->get_error_message()); heroespet_ai_log('ERROR', 'Falha ao criar post: ' . $post_id->get_error_message()); return; }
    heroespet_ai_log('SUCCESS', 'Post #' . $post_id . ' criado com imagem da Manus, mídia, destaque e Yoast.');
    heroespet_ai_set_progress('success', 100, 'Publicação concluída', 'Post #' . $post_id . ' criado com sucesso usando imagem Manus.');
}

function heroespet_ai_parse_json($response) {
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) $text = $response['candidates'][0]['content']['parts'][0]['text']; else return null;
    $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)); $data = json_decode($text, true); return is_array($data) ? $data : null;
}

function heroespet_ai_create_post($data, $image, $topic, $opts) {
    require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
    $tmp = wp_tempnam('heroespet-ai'); $ext = (strpos($image['mime'], 'jpeg') !== false || strpos($image['mime'], 'jpg') !== false) ? 'jpg' : 'png'; $tmp .= '.' . $ext; file_put_contents($tmp, $image['data']);
    $editor = wp_get_image_editor($tmp); if (!is_wp_error($editor)) { $editor->resize(1280, 720, true); $saved = $editor->save($tmp, 'image/jpeg'); if (!is_wp_error($saved)) { $tmp = $saved['path']; $ext = 'jpg'; } }
    $file = array('name' => sanitize_file_name(($data['title'] ?? 'heroespet-artigo') . '.' . $ext), 'tmp_name' => $tmp, 'type' => 'image/jpeg', 'error' => 0, 'size' => filesize($tmp));
    $attachment_id = media_handle_sideload($file, 0, $data['title'] ?? 'Imagem do artigo HeroesPet'); if (is_wp_error($attachment_id)) { @unlink($tmp); return $attachment_id; }
    $category = absint($opts['category_id'] ?? 0);
    if (!$category || !get_category($category)) return new WP_Error('heroespet_category', 'Selecione uma categoria válida do WordPress nas configurações do plugin.');
    $focus = sanitize_text_field($data['focus_keyword'] ?? $topic['key']);
    $image_alt = sanitize_text_field($data['image_alt'] ?? (($focus ?: $data['title']) . ' para cães e gatos'));
    if ($focus && stripos($image_alt, $focus) === false) $image_alt = $focus . ' — ' . $image_alt;
    update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_alt);
    $top_image = wp_get_attachment_image($attachment_id, 'full', false, array('class' => 'heroespet-ai-featured-image', 'alt' => $image_alt, 'loading' => 'eager'));
    $body = wp_kses_post($data['content']);
    if ($focus) {
        if (stripos($body, $focus) === false) $body = '<p>' . esc_html($focus) . ': ' . $body . '</p>';
        $body = preg_replace('/(<h[23][^>]*>)(.*?)(<\/h[23]>)/is', '$1' . esc_html($focus) . ': $2$3', $body, 1);
        $occurrences = substr_count(mb_strtolower(wp_strip_all_tags($body)), mb_strtolower($focus));
        if ($occurrences < 4) $body .= '<p>Ao longo deste guia, você encontrará orientações práticas sobre ' . esc_html($focus) . ' para aplicar com segurança na rotina.</p>';
    }
    $content = $top_image . "\n\n" . $body;
    if (stripos($content, esc_url(home_url('/'))) === false) $content .= '<p>Veja mais conteúdos no <a href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>. ';
    else $content .= '<p>';
    if (stripos($content, 'avma.org') === false) $content .= 'Para referência veterinária, consulte também a <a href="https://www.avma.org/resources-tools/pet-owners" target="_blank" rel="noopener noreferrer">American Veterinary Medical Association</a>.';
    $content .= '</p>';
    $post_id = wp_insert_post(wp_slash(array('post_title' => wp_strip_all_tags($data['title']), 'post_excerpt' => wp_strip_all_tags($data['excerpt'] ?? ''), 'post_content' => $content, 'post_status' => $opts['status'], 'post_type' => 'post', 'post_category' => array($category), 'tags_input' => array('mundo pet', 'veterinária', $topic['key']))), true);
    if (is_wp_error($post_id)) return $post_id;
    set_post_thumbnail($post_id, $attachment_id); wp_update_post(array('ID' => $attachment_id, 'post_parent' => $post_id));
    $seo_title = wp_trim_words(sanitize_text_field($data['seo_title'] ?? $data['title']), 9, ''); if (mb_strlen($seo_title) > 55) $seo_title = mb_substr($seo_title, 0, 52) . '...';
    $meta = sanitize_text_field($data['meta_description'] ?? $data['excerpt']); if (mb_strlen($meta) > 155) $meta = mb_substr($meta, 0, 152) . '...';
    if ($focus && stripos($meta, $focus) === false) $meta = mb_substr($focus . ': ' . $meta, 0, 155);
    $yoast = array('_yoast_wpseo_focuskw' => $focus, '_yoast_wpseo_title' => $seo_title, '_yoast_wpseo_metadesc' => $meta, '_yoast_wpseo_opengraph-title' => $seo_title, '_yoast_wpseo_opengraph-description' => $meta, '_yoast_wpseo_twitter-title' => $seo_title, '_yoast_wpseo_twitter-description' => $meta, '_yoast_wpseo_schema_page_type' => 'WebPage', '_yoast_wpseo_schema_article_type' => 'Article'); foreach ($yoast as $key => $value) update_post_meta($post_id, $key, sanitize_text_field($value));
    return $post_id;
}

add_filter('cron_schedules', function($schedules) { return $schedules; });
add_action('update_option_' . HEROESPET_AI_OPTION, function($old, $new) { heroespet_ai_reschedule($new); }, 10, 2);
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) { array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=heroespet-ai')) . '">Configurar</a>'); return $links; });

?>
