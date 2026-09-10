<?php
/**
 * Plugin Name: HeroesPet AI Content
 * Plugin URI: https://heroespet.com.br
 * Description: Gera, agenda e publica conteúdos pet/veterinários com Google Gemini, imagem destacada 1280x720 e campos SEO Yoast.
 * Version: 1.0.0
 * Author: HeroesPet
 * Author URI: https://heroespet.com.br
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: heroespet-ai-content
 */

if (!defined('ABSPATH')) { exit; }

define('HEROESPET_AI_VERSION', '1.0.0');
define('HEROESPET_AI_OPTION', 'heroespet_ai_options');
define('HEROESPET_AI_LOG_OPTION', 'heroespet_ai_logs');
define('HEROESPET_AI_CRON_HOOK', 'heroespet_ai_generate_event');

register_activation_hook(__FILE__, 'heroespet_ai_activate');
register_deactivation_hook(__FILE__, 'heroespet_ai_deactivate');
add_action('admin_menu', 'heroespet_ai_admin_menu');
add_action('admin_init', 'heroespet_ai_register_settings');
add_action('admin_post_heroespet_ai_generate_now', 'heroespet_ai_generate_now');
add_action('admin_post_heroespet_ai_clear_logs', 'heroespet_ai_clear_logs');
add_action(HEROESPET_AI_CRON_HOOK, 'heroespet_ai_cron_generate');
add_action('admin_notices', 'heroespet_ai_admin_notice');

function heroespet_ai_defaults() {
    return array(
        'gemini_text_key' => '',
        'gemini_image_key' => '',
        'text_model' => 'gemini-2.5-flash',
        'image_model' => 'gemini-2.5-flash-image',
        'frequency' => 'daily',
        'publish_time' => '08:00',
        'prompt' => "Você é um jornalista especializado em mundo pet e medicina veterinária. Escreva em português do Brasil, com linguagem acolhedora, precisa e responsável. Alterne os temas automaticamente entre dicas práticas, cuidados veterinários, curiosidades e notícias relevantes da semana. Nunca invente diagnósticos, estatísticas ou fontes. Quando falar de saúde, inclua orientação para procurar um médico-veterinário. Produza título, subtítulo, artigo completo e dados SEO.",
        'category' => 'Mundo Pet',
        'status' => 'publish',
    );
}

function heroespet_ai_get_options() {
    return wp_parse_args((array) get_option(HEROESPET_AI_OPTION, array()), heroespet_ai_defaults());
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
        'gemini_image_key' => array('Chave Gemini para imagem', 'password'),
        'text_model' => array('Modelo de texto', 'text'),
        'image_model' => array('Modelo de imagem', 'text'),
        'prompt' => array('Prompt editável do artigo', 'textarea'),
        'category' => array('Categoria dos posts', 'text'),
    );
    foreach ($fields as $key => $data) {
        add_settings_field($key, esc_html($data[0]), 'heroespet_ai_render_field', 'heroespet-ai', 'heroespet_ai_main', array('key' => $key, 'type' => $data[1]));
    }
}

function heroespet_ai_sanitize_options($input) {
    $old = heroespet_ai_get_options();
    $out = heroespet_ai_defaults();
    foreach (array('gemini_text_key', 'gemini_image_key', 'text_model', 'image_model', 'category') as $key) {
        $value = isset($input[$key]) ? sanitize_text_field($input[$key]) : '';
        $out[$key] = ($value === '' && in_array($key, array('gemini_text_key', 'gemini_image_key'), true)) ? $old[$key] : $value;
    }
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
    if ($type === 'textarea') {
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
    ?>
    <div class="wrap">
      <h1>HeroesPet AI Content <small style="font-size:13px;color:#666">v<?php echo esc_html(HEROESPET_AI_VERSION); ?></small></h1>
      <p>Gere conteúdos editoriais sobre mundo pet e veterinário, com imagem profissional, mídia e SEO Yoast.</p>
      <?php settings_errors(); ?>
      <form method="post" action="options.php">
        <?php settings_fields('heroespet_ai_settings'); do_settings_sections('heroespet-ai'); ?>
        <table class="form-table"><tr><th>Frequência</th><td><select name="<?php echo esc_attr(HEROESPET_AI_OPTION); ?>[frequency]"><option value="daily" <?php selected($opts['frequency'], 'daily'); ?>>Diário</option><option value="weekly" <?php selected($opts['frequency'], 'weekly'); ?>>Semanal</option><option value="monthly" <?php selected($opts['frequency'], 'monthly'); ?>>Mensal</option></select></td></tr>
        <tr><th>Horário de publicação</th><td><select name="<?php echo esc_attr(HEROESPET_AI_OPTION); ?>[publish_time]"><?php foreach ($times as $time) printf('<option value="%s" %s>%s</option>', esc_attr($time), selected($opts['publish_time'], $time, false), esc_html($time)); ?></select><p class="description">O horário usa o fuso configurado em Configurações &gt; Geral.</p></td></tr>
        <tr><th>Status padrão</th><td><select name="<?php echo esc_attr(HEROESPET_AI_OPTION); ?>[status]"><option value="publish" <?php selected($opts['status'], 'publish'); ?>>Publicar automaticamente</option><option value="draft" <?php selected($opts['status'], 'draft'); ?>>Salvar como rascunho</option></select></td></tr></table>
        <?php submit_button('Salvar configurações'); ?>
      </form>
      <hr><h2>Publicar agora</h2><p>O processo cria primeiro um resumo editorial; depois solicita simultaneamente o artigo e a imagem ao Gemini, salva a imagem na biblioteca e define a imagem destacada.</p>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('heroespet_ai_generate_now'); ?><input type="hidden" name="action" value="heroespet_ai_generate_now"><?php submit_button('Gerar e publicar agora', 'primary'); ?></form>
      <hr><h2>Log de operações</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('heroespet_ai_clear_logs'); ?><input type="hidden" name="action" value="heroespet_ai_clear_logs"><?php submit_button('Limpar log', 'secondary', 'submit', array('onclick' => "return confirm('Limpar todo o log?');")); ?></form>
      <div style="max-height:420px;overflow:auto;margin-top:12px"><table class="widefat striped"><thead><tr><th>Data</th><th>Nível</th><th>Mensagem</th></tr></thead><tbody><?php if (!$logs) echo '<tr><td colspan="3">Nenhum registro.</td></tr>'; foreach (array_reverse($logs) as $log) printf('<tr><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html($log['time']), esc_html($log['level']), esc_html($log['message'])); ?></tbody></table></div>
    </div>
    <?php
}

function heroespet_ai_reschedule($options = null) {
    $opts = $options ?: heroespet_ai_get_options();
    wp_clear_scheduled_hook(HEROESPET_AI_CRON_HOOK);
    $timestamp = heroespet_ai_next_timestamp($opts['publish_time'], $opts['frequency']);
    if ($timestamp) wp_schedule_single_event($timestamp, HEROESPET_AI_CRON_HOOK);
}

function heroespet_ai_next_timestamp($time, $frequency) {
    list($hour, $minute) = array_map('intval', explode(':', $time));
    $now = current_time('timestamp'); $candidate = mktime($hour, $minute, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now));
    if ($candidate <= $now) $candidate = strtotime('+1 day', $candidate);
    if ($frequency === 'weekly') $candidate = strtotime('+6 days', $candidate);
    if ($frequency === 'monthly') $candidate = strtotime('+1 month', $candidate);
    return $candidate;
}

function heroespet_ai_cron_generate() {
    heroespet_ai_generate_content(false);
    heroespet_ai_reschedule();
}

function heroespet_ai_generate_now() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('heroespet_ai_generate_now');
    $result = heroespet_ai_generate_content(true);
    $url = add_query_arg(array('page' => 'heroespet-ai', 'heroespet_ai_result' => $result['ok'] ? 'success' : 'error', 'heroespet_ai_message' => rawurlencode($result['message'])), admin_url('admin.php'));
    wp_safe_redirect($url); exit;
}

function heroespet_ai_clear_logs() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('heroespet_ai_clear_logs'); delete_option(HEROESPET_AI_LOG_OPTION);
    wp_safe_redirect(admin_url('admin.php?page=heroespet-ai')); exit;
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
    if (!$opts['gemini_text_key'] || !$opts['gemini_image_key']) { heroespet_ai_log('ERROR', 'As duas chaves Gemini precisam estar configuradas.'); return array('ok' => false, 'message' => 'Configure as duas chaves Gemini antes de gerar.'); }
    $topic = heroespet_ai_pick_topic();
    heroespet_ai_log('INFO', 'Início da geração: ' . $topic['label']);
    $brief = heroespet_ai_call_text($opts['gemini_text_key'], $opts['text_model'], 'Crie apenas um resumo editorial de 45 a 70 palavras para um conteúdo sobre ' . $topic['label'] . '. Inclua o ângulo principal, público e cuidados editoriais. Retorne somente o resumo.');
    if (is_wp_error($brief)) { heroespet_ai_log('ERROR', 'Falha ao criar resumo: ' . $brief->get_error_message()); return array('ok' => false, 'message' => $brief->get_error_message()); }
    heroespet_ai_log('INFO', 'Resumo criado. Solicitando artigo e imagem em paralelo.');
    $article_prompt = $opts['prompt'] . "\n\nTema desta publicação: " . $topic['label'] . "\nResumo editorial: " . $brief . "\n\nRetorne JSON válido com as chaves title, excerpt, content, focus_keyword, seo_title, meta_description, image_alt. O conteúdo deve ter no mínimo 1000 palavras, usar subtítulos HTML <h2> e <h3>, parágrafos úteis e não incluir markdown fences.";
    $image_prompt = "Fotografia profissional editorial, realista e natural, relacionada ao seguinte conteúdo para um portal pet brasileiro: " . $brief . ". Pode conter pessoas e pets ou apenas pets conforme fizer sentido. Composição horizontal para capa de artigo, iluminação profissional, sem texto, sem logotipos, sem marca d'água, aspecto 16:9.";
    $responses = heroespet_ai_parallel_requests(array(
        'article' => heroespet_ai_request_payload($opts['gemini_text_key'], $opts['text_model'], $article_prompt, false),
        'image' => heroespet_ai_request_payload($opts['gemini_image_key'], $opts['image_model'], $image_prompt, true),
    ));
    if (is_wp_error($responses['article'])) { heroespet_ai_log('ERROR', 'Falha no artigo: ' . $responses['article']->get_error_message()); return array('ok' => false, 'message' => 'Falha na geração do artigo.'); }
    if (is_wp_error($responses['image'])) { heroespet_ai_log('ERROR', 'Falha na imagem: ' . $responses['image']->get_error_message()); return array('ok' => false, 'message' => 'Falha na geração da imagem.'); }
    $data = heroespet_ai_parse_json($responses['article']);
    if (!$data || empty($data['content']) || str_word_count(wp_strip_all_tags($data['content'])) < 1000) { heroespet_ai_log('ERROR', 'O artigo retornado não atingiu 1000 palavras ou não veio em JSON.'); return array('ok' => false, 'message' => 'O artigo retornado não atingiu 1000 palavras.'); }
    $image = heroespet_ai_extract_image($responses['image']);
    if (!$image) { heroespet_ai_log('ERROR', 'A resposta do Gemini não continha dados de imagem.'); return array('ok' => false, 'message' => 'O Gemini não retornou dados de imagem.'); }
    $post_id = heroespet_ai_create_post($data, $image, $topic, $opts);
    if (is_wp_error($post_id)) { heroespet_ai_log('ERROR', 'Falha ao criar post: ' . $post_id->get_error_message()); return array('ok' => false, 'message' => $post_id->get_error_message()); }
    heroespet_ai_log('SUCCESS', 'Post #' . $post_id . ' criado com artigo, mídia, imagem destacada e campos Yoast.');
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

function heroespet_ai_request_payload($key, $model, $prompt, $image) {
    $generation = $image ? array('responseModalities' => array('IMAGE'), 'imageConfig' => array('aspectRatio' => '16:9')) : array('responseMimeType' => 'application/json', 'temperature' => 0.7, 'maxOutputTokens' => 8192);
    return array('url' => 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key), 'body' => array('contents' => array(array('role' => 'user', 'parts' => array(array('text' => $prompt)))), 'generationConfig' => $generation));
}

function heroespet_ai_call_text($key, $model, $prompt) {
    $payload = heroespet_ai_request_payload($key, $model, $prompt, false); $response = wp_remote_post($payload['url'], array('timeout' => 120, 'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode($payload['body'])));
    if (is_wp_error($response)) return $response; $code = wp_remote_retrieve_response_code($response); $body = json_decode(wp_remote_retrieve_body($response), true); if ($code >= 300) return new WP_Error('gemini_http', $body['error']['message'] ?? 'Erro HTTP do Gemini.'); return trim($body['candidates'][0]['content']['parts'][0]['text'] ?? '');
}

function heroespet_ai_parallel_requests($payloads) {
    if (!function_exists('curl_multi_init')) {
        $fallback = array();
        foreach ($payloads as $name => $payload) {
            $response = wp_remote_post($payload['url'], array('timeout' => 180, 'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode($payload['body'])));
            if (is_wp_error($response)) { $fallback[$name] = $response; continue; }
            $code = wp_remote_retrieve_response_code($response); $data = json_decode(wp_remote_retrieve_body($response), true);
            $fallback[$name] = ($code >= 300 || !is_array($data)) ? new WP_Error('gemini_http', $data['error']['message'] ?? 'Erro na chamada Gemini.') : $data;
        }
        return $fallback;
    }
    $mh = curl_multi_init(); $handles = array(); $results = array();
    foreach ($payloads as $name => $payload) { $ch = curl_init($payload['url']); curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 180, CURLOPT_HTTPHEADER => array('Content-Type: application/json'), CURLOPT_POSTFIELDS => wp_json_encode($payload['body']))); curl_multi_add_handle($mh, $ch); $handles[$name] = $ch; }
    do { curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 1); } while ($running);
    foreach ($handles as $name => $ch) { $raw = curl_multi_getcontent($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $data = json_decode($raw, true); $results[$name] = ($code >= 300 || !is_array($data)) ? new WP_Error('gemini_http', $data['error']['message'] ?? 'Erro na chamada Gemini.') : $data; curl_multi_remove_handle($mh, $ch); curl_close($ch); }
    curl_multi_close($mh); return $results;
}

function heroespet_ai_parse_json($response) {
    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) $text = $response['candidates'][0]['content']['parts'][0]['text']; else return null;
    $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)); $data = json_decode($text, true); return is_array($data) ? $data : null;
}

function heroespet_ai_extract_image($response) {
    foreach (($response['candidates'][0]['content']['parts'] ?? array()) as $part) { if (!empty($part['inlineData']['data'])) return array('data' => base64_decode($part['inlineData']['data']), 'mime' => $part['inlineData']['mimeType'] ?? 'image/png'); }
    return null;
}

function heroespet_ai_create_post($data, $image, $topic, $opts) {
    require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
    $tmp = wp_tempnam('heroespet-ai'); $ext = (strpos($image['mime'], 'jpeg') !== false || strpos($image['mime'], 'jpg') !== false) ? 'jpg' : 'png'; $tmp .= '.' . $ext; file_put_contents($tmp, $image['data']);
    $editor = wp_get_image_editor($tmp); if (!is_wp_error($editor)) { $editor->resize(1280, 720, true); $saved = $editor->save($tmp, 'image/jpeg'); if (!is_wp_error($saved)) { $tmp = $saved['path']; $ext = 'jpg'; } }
    $file = array('name' => sanitize_file_name(($data['title'] ?? 'heroespet-artigo') . '.' . $ext), 'tmp_name' => $tmp, 'type' => 'image/jpeg', 'error' => 0, 'size' => filesize($tmp));
    $attachment_id = media_handle_sideload($file, 0, $data['title'] ?? 'Imagem do artigo HeroesPet'); if (is_wp_error($attachment_id)) { @unlink($tmp); return $attachment_id; }
    $category = get_cat_ID($opts['category']); if (!$category) $category = wp_create_category($opts['category']);
    $post_id = wp_insert_post(wp_slash(array('post_title' => wp_strip_all_tags($data['title']), 'post_excerpt' => wp_strip_all_tags($data['excerpt'] ?? ''), 'post_content' => $data['content'], 'post_status' => $opts['status'], 'post_type' => 'post', 'post_category' => array((int) $category), 'tags_input' => array('mundo pet', 'veterinária', $topic['key']))), true);
    if (is_wp_error($post_id)) return $post_id;
    set_post_thumbnail($post_id, $attachment_id); wp_update_post(array('ID' => $attachment_id, 'post_parent' => $post_id)); update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($data['image_alt'] ?? $data['title']));
    $yoast = array('_yoast_wpseo_focuskw' => $data['focus_keyword'] ?? $topic['key'], '_yoast_wpseo_title' => $data['seo_title'] ?? $data['title'], '_yoast_wpseo_metadesc' => $data['meta_description'] ?? $data['excerpt'], '_yoast_wpseo_opengraph-title' => $data['seo_title'] ?? $data['title'], '_yoast_wpseo_opengraph-description' => $data['meta_description'] ?? $data['excerpt'], '_yoast_wpseo_twitter-title' => $data['seo_title'] ?? $data['title'], '_yoast_wpseo_twitter-description' => $data['meta_description'] ?? $data['excerpt'], '_yoast_wpseo_schema_page_type' => 'WebPage', '_yoast_wpseo_schema_article_type' => 'Article'); foreach ($yoast as $key => $value) update_post_meta($post_id, $key, sanitize_text_field($value));
    return $post_id;
}

add_filter('cron_schedules', function($schedules) { return $schedules; });
add_action('update_option_' . HEROESPET_AI_OPTION, function($old, $new) { heroespet_ai_reschedule($new); }, 10, 2);
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) { array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=heroespet-ai')) . '">Configurar</a>'); return $links; });

?>
