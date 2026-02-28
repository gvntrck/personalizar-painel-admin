<?php
/**
 * Plugin Name: Painel Admin
 * Description: Personaliza a tela inicial do wp-admin com atalhos e blocos para facilitar o uso por clientes.
 * Version: 1.2.0
 * Author: Codex
 * License: GPLv2 or later
 * Text Domain: painel-admin
 */

if (!defined('ABSPATH')) {
    exit;
}

final class PAC_Painel_Admin_Cliente
{
    const OPTION_KEY = 'pac_admin_panel_settings';
    const SLUG = 'pac-admin-panel-settings';
    const VERSION = '1.2.0';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_post_pac_save_settings', array($this, 'handle_save_settings'));
        add_action('wp_dashboard_setup', array($this, 'setup_dashboard'), 999);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public static function activate()
    {
        if (false === get_option(self::OPTION_KEY, false)) {
            update_option(self::OPTION_KEY, self::defaults());
        }
    }

    private static function defaults()
    {
        return array(
            'replace_dashboard' => 1,
            'welcome_title' => 'Bem-vindo ao painel da empresa',
            'welcome_text' => 'Use os atalhos abaixo para acessar as tarefas do dia a dia.',
            'items' => array(
                array(
                    'title' => 'Criar novo post',
                    'url' => 'post-new.php',
                    'description' => 'Publicar uma nova noticia ou conteudo.',
                    'icon' => 'dashicons-edit',
                    'color' => '#2271b1',
                    'new_tab' => 0,
                ),
                array(
                    'title' => 'Biblioteca de midia',
                    'url' => 'upload.php',
                    'description' => 'Gerenciar imagens, PDFs e arquivos enviados.',
                    'icon' => 'dashicons-format-image',
                    'color' => '#00a32a',
                    'new_tab' => 0,
                ),
                array(
                    'title' => 'Visualizar site',
                    'url' => '/',
                    'description' => 'Abrir o site publico para revisar alteracoes.',
                    'icon' => 'dashicons-admin-site',
                    'color' => '#d63638',
                    'new_tab' => 1,
                ),
            ),
            'meta_boxes' => array(
                array(
                    'title' => 'Instrucoes rapidas',
                    'content' => "<ul><li>Use os atalhos do painel para tarefas comuns.</li><li>Se precisar de ajuda, fale com o suporte.</li></ul>",
                    'context' => 'normal',
                    'priority' => 'default',
                ),
            ),
        );
    }

    private function empty_item()
    {
        return array(
            'title' => '',
            'url' => '',
            'description' => '',
            'icon' => 'dashicons-admin-links',
            'color' => '#2271b1',
            'new_tab' => 0,
        );
    }

    private function empty_meta_box()
    {
        return array(
            'title' => '',
            'content' => '',
            'context' => 'normal',
            'priority' => 'default',
        );
    }

    private function get_settings()
    {
        $settings = get_option(self::OPTION_KEY, array());
        if (!is_array($settings)) {
            $settings = array();
        }

        $settings = wp_parse_args($settings, self::defaults());

        if (!isset($settings['items']) || !is_array($settings['items'])) {
            $settings['items'] = array();
        }

        if (!isset($settings['meta_boxes']) || !is_array($settings['meta_boxes'])) {
            $settings['meta_boxes'] = array();
        }

        return $settings;
    }

    public function register_menu()
    {
        add_menu_page(
            'Painel do Cliente',
            'Painel do Cliente',
            'manage_options',
            self::SLUG,
            array($this, 'render_settings_page'),
            'dashicons-screenoptions',
            3
        );
    }

    public function enqueue_assets($hook)
    {
        $settings_hook = 'toplevel_page_' . self::SLUG;

        if ('index.php' !== $hook && $settings_hook !== $hook) {
            return;
        }

        wp_enqueue_style(
            'pac-admin-style',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            array(),
            self::VERSION
        );

        if ($settings_hook === $hook) {
            $settings = $this->get_settings();
            $next_index = count($settings['items']);
            $next_meta_box_index = count($settings['meta_boxes']);

            wp_enqueue_script(
                'pac-admin-script',
                plugin_dir_url(__FILE__) . 'assets/admin.js',
                array('jquery'),
                self::VERSION,
                true
            );

            wp_localize_script(
                'pac-admin-script',
                'pacPanelSettings',
                array(
                    'nextIndex' => $next_index,
                    'nextMetaBoxIndex' => $next_meta_box_index,
                    'icons' => $this->get_available_dashicons(),
                    'i18n' => array(
                        'searchPlaceholder' => 'Buscar icone...',
                        'emptySearch' => 'Nenhum icone encontrado.',
                    ),
                )
            );
        }
    }

    public function setup_dashboard()
    {
        $settings = $this->get_settings();

        if (!empty($settings['replace_dashboard'])) {
            $this->remove_default_dashboard_widgets();
            remove_action('welcome_panel', 'wp_welcome_panel');
        }

        wp_add_dashboard_widget(
            'pac_dashboard_widget',
            'Painel rapido do cliente',
            array($this, 'render_dashboard_widget')
        );

        $meta_boxes = isset($settings['meta_boxes']) && is_array($settings['meta_boxes']) ? $settings['meta_boxes'] : array();
        $this->register_custom_meta_boxes($meta_boxes);
    }

    private function remove_default_dashboard_widgets()
    {
        $widgets = array(
            'dashboard_right_now',
            'dashboard_activity',
            'dashboard_quick_press',
            'dashboard_primary',
            'dashboard_secondary',
            'dashboard_site_health',
            'dashboard_browser_nag',
            'dashboard_php_nag',
            'dashboard_recent_drafts',
            'dashboard_recent_comments',
            'dashboard_incoming_links',
            'dashboard_plugins',
        );

        foreach ($widgets as $widget_id) {
            remove_meta_box($widget_id, 'dashboard', 'normal');
            remove_meta_box($widget_id, 'dashboard', 'side');
        }
    }

    private function build_item_url($url)
    {
        $url = trim((string) $url);

        if ('' === $url) {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (0 === strpos($url, '/')) {
            return home_url($url);
        }

        return admin_url(ltrim($url, '/'));
    }

    public function render_dashboard_widget()
    {
        $settings = $this->get_settings();
        $items = isset($settings['items']) && is_array($settings['items']) ? $settings['items'] : array();

        echo '<div class="pac-dashboard-wrapper">';

        if (!empty($settings['welcome_title'])) {
            echo '<h2 class="pac-dashboard-title">' . esc_html($settings['welcome_title']) . '</h2>';
        }

        if (!empty($settings['welcome_text'])) {
            echo '<p class="pac-dashboard-text">' . esc_html($settings['welcome_text']) . '</p>';
        }

        if (empty($items)) {
            $settings_url = admin_url('admin.php?page=' . self::SLUG);
            echo '<p>';
            echo esc_html__('Nenhum atalho configurado ainda.', 'painel-admin-cliente') . ' ';
            echo '<a href="' . esc_url($settings_url) . '">' . esc_html__('Clique aqui para configurar.', 'painel-admin-cliente') . '</a>';
            echo '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="pac-card-grid">';

        foreach ($items as $item) {
            $item = wp_parse_args($item, $this->empty_item());

            $title = $item['title'];
            $description = $item['description'];
            $icon = $this->sanitize_icon($item['icon']);
            $color = sanitize_hex_color($item['color']);
            $url = $this->build_item_url($item['url']);
            $new_tab = !empty($item['new_tab']);

            if ('' === $title || '' === $url) {
                continue;
            }

            if (!$color) {
                $color = '#2271b1';
            }

            echo '<a class="pac-card" href="' . esc_url($url) . '" style="--pac-color:' . esc_attr($color) . ';"';
            if ($new_tab) {
                echo ' target="_blank" rel="noopener noreferrer"';
            }
            echo '>';
            echo '<span class="dashicons ' . esc_attr($icon) . ' pac-card-icon" aria-hidden="true"></span>';
            echo '<span class="pac-card-content">';
            echo '<strong class="pac-card-title">' . esc_html($title) . '</strong>';
            if (!empty($description)) {
                echo '<small class="pac-card-description">' . esc_html($description) . '</small>';
            }
            echo '</span>';
            echo '</a>';
        }

        echo '</div>';
        echo '</div>';
    }

    private function register_custom_meta_boxes($meta_boxes)
    {
        if (empty($meta_boxes) || !is_array($meta_boxes)) {
            return;
        }

        foreach ($meta_boxes as $index => $meta_box) {
            $meta_box = wp_parse_args($meta_box, $this->empty_meta_box());
            $title = trim((string) $meta_box['title']);
            $content = trim((string) $meta_box['content']);

            if ('' === $title || '' === $content) {
                continue;
            }

            $context = $this->normalize_meta_box_context($meta_box['context']);
            $priority = $this->normalize_meta_box_priority($meta_box['priority']);
            $widget_id = $this->build_meta_box_id($index, $title);

            wp_add_dashboard_widget(
                $widget_id,
                $title,
                array($this, 'render_custom_meta_box'),
                null,
                array(
                    'content' => $content,
                ),
                $context,
                $priority
            );
        }
    }

    public function render_custom_meta_box($post, $callback_args)
    {
        $args = isset($callback_args['args']) && is_array($callback_args['args']) ? $callback_args['args'] : array();
        $content = isset($args['content']) ? (string) $args['content'] : '';

        if ('' === trim($content)) {
            echo '<p>Sem conteudo.</p>';
            return;
        }

        echo '<div class="pac-custom-meta-box-content">';
        echo wp_kses_post(wpautop($content));
        echo '</div>';
    }

    private function build_meta_box_id($index, $title)
    {
        $sanitized_title = sanitize_key(remove_accents($title));
        if ('' === $sanitized_title) {
            $sanitized_title = 'box';
        }

        return 'pac_custom_box_' . absint($index) . '_' . $sanitized_title;
    }

    private function normalize_meta_box_context($context)
    {
        $allowed = array('normal', 'side');
        $context = is_string($context) ? strtolower($context) : 'normal';

        return in_array($context, $allowed, true) ? $context : 'normal';
    }

    private function normalize_meta_box_priority($priority)
    {
        $allowed = array('high', 'core', 'default', 'low');
        $priority = is_string($priority) ? strtolower($priority) : 'default';

        return in_array($priority, $allowed, true) ? $priority : 'default';
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        $items = $settings['items'];
        $meta_boxes = $settings['meta_boxes'];

        if (empty($items)) {
            $items = array($this->empty_item());
        }

        if (empty($meta_boxes)) {
            $meta_boxes = array($this->empty_meta_box());
        }

        echo '<div class="wrap pac-settings-wrap">';
        echo '<h1>Painel do Cliente</h1>';
        echo '<p>Monte um painel simples para o cliente usar na tela inicial do wp-admin.</p>';

        if (!empty($_GET['pac_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Configuracoes salvas com sucesso.</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pac_save_settings_nonce');
        echo '<input type="hidden" name="action" value="pac_save_settings" />';

        echo '<table class="form-table" role="presentation">';
        echo '<tbody>';

        echo '<tr>';
        echo '<th scope="row">Substituir Dashboard padrao</th>';
        echo '<td><label><input type="checkbox" name="pac_replace_dashboard" value="1" ' . checked(!empty($settings['replace_dashboard']), true, false) . ' /> Ocultar widgets padrao e mostrar apenas este painel</label></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="pac_welcome_title">Titulo de boas-vindas</label></th>';
        echo '<td><input class="regular-text" type="text" id="pac_welcome_title" name="pac_welcome_title" value="' . esc_attr($settings['welcome_title']) . '" /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="pac_welcome_text">Texto de apoio</label></th>';
        echo '<td><textarea class="large-text" rows="3" id="pac_welcome_text" name="pac_welcome_text">' . esc_textarea($settings['welcome_text']) . '</textarea></td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        echo '<h2>Atalhos do painel</h2>';
        echo '<p>Use URL absoluta (https://...) ou caminho interno do wp-admin (ex: <code>post-new.php</code>).</p>';

        echo '<table class="widefat striped pac-items-table">';
        echo '<thead><tr>';
        echo '<th>Titulo</th>';
        echo '<th>URL</th>';
        echo '<th>Descricao</th>';
        echo '<th>Icone Dashicon</th>';
        echo '<th>Cor</th>';
        echo '<th>Nova aba</th>';
        echo '<th>Acao</th>';
        echo '</tr></thead>';
        echo '<tbody id="pac-items-body">';

        foreach ($items as $index => $item) {
            $this->render_item_row($item, (string) $index);
        }

        echo '</tbody>';
        echo '</table>';

        echo '<p><button type="button" class="button" id="pac-add-item">Adicionar atalho</button></p>';
        echo '<p><small>Escolha o icone pelo seletor visual. Referencia completa: <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank" rel="noopener noreferrer">Dashicons</a>.</small></p>';

        echo '<hr />';
        echo '<h2>Meta Boxes adicionais</h2>';
        echo '<p>Crie quantos blocos extras quiser para aparecer no Dashboard (ex: avisos, checklist, instrucoes).</p>';

        echo '<table class="widefat striped pac-meta-boxes-table">';
        echo '<thead><tr>';
        echo '<th>Titulo do Meta Box</th>';
        echo '<th>Conteudo</th>';
        echo '<th>Posicao</th>';
        echo '<th>Prioridade</th>';
        echo '<th>Acao</th>';
        echo '</tr></thead>';
        echo '<tbody id="pac-meta-boxes-body">';

        foreach ($meta_boxes as $index => $meta_box) {
            $this->render_meta_box_row($meta_box, (string) $index);
        }

        echo '</tbody>';
        echo '</table>';
        echo '<p><button type="button" class="button" id="pac-add-meta-box">Adicionar Meta Box</button></p>';

        submit_button('Salvar configuracoes');

        echo '</form>';

        echo '<script type="text/template" id="pac-item-row-template">';
        $this->render_item_row($this->empty_item(), '__INDEX__');
        echo '</script>';

        echo '<script type="text/template" id="pac-meta-box-row-template">';
        $this->render_meta_box_row($this->empty_meta_box(), '__MB_INDEX__');
        echo '</script>';

        $this->render_icon_picker_modal();

        echo '</div>';
    }

    private function render_item_row($item, $index)
    {
        $item = wp_parse_args($item, $this->empty_item());

        $icon = $this->sanitize_icon($item['icon']);
        $color = sanitize_hex_color($item['color']);
        $new_tab = !empty($item['new_tab']) ? 1 : 0;

        if (!$color) {
            $color = '#2271b1';
        }

        echo '<tr class="pac-item-row">';
        echo '<td><input type="text" class="regular-text" name="pac_items[' . esc_attr($index) . '][title]" value="' . esc_attr($item['title']) . '" /></td>';
        echo '<td><input type="text" class="regular-text" name="pac_items[' . esc_attr($index) . '][url]" value="' . esc_attr($item['url']) . '" placeholder="post-new.php ou https://site.com" /></td>';
        echo '<td><input type="text" class="regular-text" name="pac_items[' . esc_attr($index) . '][description]" value="' . esc_attr($item['description']) . '" /></td>';
        echo '<td>';
        echo '<input type="hidden" class="pac-icon-input" name="pac_items[' . esc_attr($index) . '][icon]" value="' . esc_attr($icon) . '" />';
        echo '<button type="button" class="button pac-open-icon-picker">';
        echo '<span class="dashicons ' . esc_attr($icon) . ' pac-icon-preview" aria-hidden="true"></span>';
        echo '<span class="pac-icon-name">' . esc_html(str_replace('dashicons-', '', $icon)) . '</span>';
        echo '</button>';
        echo '</td>';
        echo '<td><input type="color" name="pac_items[' . esc_attr($index) . '][color]" value="' . esc_attr($color) . '" /></td>';
        echo '<td><label><input type="checkbox" name="pac_items[' . esc_attr($index) . '][new_tab]" value="1" ' . checked($new_tab, 1, false) . ' /> Sim</label></td>';
        echo '<td><button type="button" class="button-link-delete pac-remove-item">Remover</button></td>';
        echo '</tr>';
    }

    private function render_meta_box_row($meta_box, $index)
    {
        $meta_box = wp_parse_args($meta_box, $this->empty_meta_box());
        $context = $this->normalize_meta_box_context($meta_box['context']);
        $priority = $this->normalize_meta_box_priority($meta_box['priority']);

        echo '<tr class="pac-meta-box-row">';
        echo '<td><input type="text" class="regular-text" name="pac_meta_boxes[' . esc_attr($index) . '][title]" value="' . esc_attr($meta_box['title']) . '" placeholder="Ex: Avisos importantes" /></td>';
        echo '<td><textarea class="large-text pac-meta-box-content" rows="4" name="pac_meta_boxes[' . esc_attr($index) . '][content]" placeholder="Conteudo do bloco (aceita HTML basico)">' . esc_textarea($meta_box['content']) . '</textarea></td>';
        echo '<td>';
        echo '<select name="pac_meta_boxes[' . esc_attr($index) . '][context]">';
        echo '<option value="normal" ' . selected($context, 'normal', false) . '>Principal</option>';
        echo '<option value="side" ' . selected($context, 'side', false) . '>Lateral</option>';
        echo '</select>';
        echo '</td>';
        echo '<td>';
        echo '<select name="pac_meta_boxes[' . esc_attr($index) . '][priority]">';
        echo '<option value="high" ' . selected($priority, 'high', false) . '>Alta</option>';
        echo '<option value="core" ' . selected($priority, 'core', false) . '>Media alta</option>';
        echo '<option value="default" ' . selected($priority, 'default', false) . '>Padrao</option>';
        echo '<option value="low" ' . selected($priority, 'low', false) . '>Baixa</option>';
        echo '</select>';
        echo '</td>';
        echo '<td><button type="button" class="button-link-delete pac-remove-meta-box">Remover</button></td>';
        echo '</tr>';
    }

    private function render_icon_picker_modal()
    {
        echo '<div id="pac-icon-modal" class="pac-icon-modal" hidden>';
        echo '<div class="pac-icon-modal-content" role="dialog" aria-modal="true" aria-labelledby="pac-icon-modal-title">';
        echo '<div class="pac-icon-modal-header">';
        echo '<h2 id="pac-icon-modal-title">Escolher icone</h2>';
        echo '<button type="button" class="button-link pac-icon-close" id="pac-icon-close" aria-label="Fechar seletor">Fechar</button>';
        echo '</div>';
        echo '<div class="pac-icon-modal-body">';
        echo '<input type="search" id="pac-icon-search" class="regular-text" placeholder="Buscar icone..." />';
        echo '<div id="pac-icon-grid" class="pac-icon-grid" aria-live="polite"></div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    private function get_available_dashicons()
    {
        return array(
            'dashicons-admin-links',
            'dashicons-admin-site',
            'dashicons-dashboard',
            'dashicons-admin-post',
            'dashicons-admin-media',
            'dashicons-admin-page',
            'dashicons-admin-comments',
            'dashicons-admin-appearance',
            'dashicons-admin-plugins',
            'dashicons-admin-users',
            'dashicons-admin-tools',
            'dashicons-admin-settings',
            'dashicons-admin-home',
            'dashicons-admin-generic',
            'dashicons-admin-customizer',
            'dashicons-admin-network',
            'dashicons-menu',
            'dashicons-filter',
            'dashicons-welcome-write-blog',
            'dashicons-welcome-add-page',
            'dashicons-welcome-view-site',
            'dashicons-welcome-comments',
            'dashicons-welcome-learn-more',
            'dashicons-edit',
            'dashicons-edit-large',
            'dashicons-clipboard',
            'dashicons-forms',
            'dashicons-format-image',
            'dashicons-format-gallery',
            'dashicons-format-video',
            'dashicons-format-audio',
            'dashicons-format-chat',
            'dashicons-format-status',
            'dashicons-format-quote',
            'dashicons-images-alt',
            'dashicons-images-alt2',
            'dashicons-video-alt',
            'dashicons-video-alt2',
            'dashicons-video-alt3',
            'dashicons-media-document',
            'dashicons-media-text',
            'dashicons-media-audio',
            'dashicons-media-video',
            'dashicons-media-code',
            'dashicons-media-archive',
            'dashicons-upload',
            'dashicons-download',
            'dashicons-portfolio',
            'dashicons-book',
            'dashicons-book-alt',
            'dashicons-feedback',
            'dashicons-testimonial',
            'dashicons-businessman',
            'dashicons-groups',
            'dashicons-id',
            'dashicons-id-alt',
            'dashicons-awards',
            'dashicons-smiley',
            'dashicons-heart',
            'dashicons-star-filled',
            'dashicons-star-half',
            'dashicons-star-empty',
            'dashicons-thumbs-up',
            'dashicons-thumbs-down',
            'dashicons-yes',
            'dashicons-no',
            'dashicons-dismiss',
            'dashicons-plus',
            'dashicons-plus-alt',
            'dashicons-plus-alt2',
            'dashicons-minus',
            'dashicons-trash',
            'dashicons-archive',
            'dashicons-tag',
            'dashicons-tagcloud',
            'dashicons-category',
            'dashicons-calendar',
            'dashicons-clock',
            'dashicons-search',
            'dashicons-visibility',
            'dashicons-hidden',
            'dashicons-external',
            'dashicons-lock',
            'dashicons-unlock',
            'dashicons-chart-pie',
            'dashicons-chart-bar',
            'dashicons-chart-line',
            'dashicons-chart-area',
            'dashicons-analytics',
            'dashicons-megaphone',
            'dashicons-bell',
            'dashicons-email',
            'dashicons-email-alt',
            'dashicons-rss',
            'dashicons-wordpress',
            'dashicons-wordpress-alt',
            'dashicons-arrow-right',
            'dashicons-arrow-left',
            'dashicons-arrow-up',
            'dashicons-arrow-down',
            'dashicons-arrow-right-alt',
            'dashicons-arrow-left-alt',
            'dashicons-arrow-up-alt',
            'dashicons-arrow-down-alt',
            'dashicons-arrow-right-alt2',
            'dashicons-arrow-left-alt2',
            'dashicons-arrow-up-alt2',
            'dashicons-arrow-down-alt2',
            'dashicons-sort',
            'dashicons-randomize',
            'dashicons-leftright',
            'dashicons-list-view',
            'dashicons-exerpt-view',
            'dashicons-screenoptions',
            'dashicons-grid-view',
            'dashicons-move',
            'dashicons-share',
            'dashicons-share-alt',
            'dashicons-share-alt2',
            'dashicons-performance',
            'dashicons-hammer',
            'dashicons-art',
            'dashicons-location',
            'dashicons-location-alt',
            'dashicons-shield',
            'dashicons-shield-alt',
            'dashicons-cloud',
            'dashicons-controls-play',
            'dashicons-controls-pause',
            'dashicons-controls-forward',
            'dashicons-controls-back',
            'dashicons-controls-repeat',
            'dashicons-controls-volumeon',
            'dashicons-controls-volumeoff',
            'dashicons-smartphone',
            'dashicons-tablet',
            'dashicons-desktop',
            'dashicons-microphone',
            'dashicons-cart',
            'dashicons-store',
            'dashicons-tickets-alt',
            'dashicons-schedule',
            'dashicons-lightbulb',
            'dashicons-universal-access',
            'dashicons-universal-access-alt',
            'dashicons-translation',
        );
    }

    public function handle_save_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado.');
        }

        check_admin_referer('pac_save_settings_nonce');

        $replace_dashboard = !empty($_POST['pac_replace_dashboard']) ? 1 : 0;
        $welcome_title = isset($_POST['pac_welcome_title']) ? sanitize_text_field(wp_unslash($_POST['pac_welcome_title'])) : '';
        $welcome_text = isset($_POST['pac_welcome_text']) ? sanitize_textarea_field(wp_unslash($_POST['pac_welcome_text'])) : '';

        $raw_items = isset($_POST['pac_items']) ? wp_unslash($_POST['pac_items']) : array();
        $items = $this->sanitize_items($raw_items);
        $raw_meta_boxes = isset($_POST['pac_meta_boxes']) ? wp_unslash($_POST['pac_meta_boxes']) : array();
        $meta_boxes = $this->sanitize_meta_boxes($raw_meta_boxes);

        $settings = array(
            'replace_dashboard' => $replace_dashboard,
            'welcome_title' => $welcome_title,
            'welcome_text' => $welcome_text,
            'items' => $items,
            'meta_boxes' => $meta_boxes,
        );

        update_option(self::OPTION_KEY, $settings);

        $redirect_url = add_query_arg(
            array(
                'page' => self::SLUG,
                'pac_saved' => '1',
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function sanitize_items($raw_items)
    {
        $items = array();

        if (!is_array($raw_items)) {
            return $items;
        }

        foreach ($raw_items as $raw_item) {
            if (!is_array($raw_item)) {
                continue;
            }

            $title = isset($raw_item['title']) ? sanitize_text_field($raw_item['title']) : '';
            $url = isset($raw_item['url']) ? sanitize_text_field($raw_item['url']) : '';
            $description = isset($raw_item['description']) ? sanitize_text_field($raw_item['description']) : '';
            $icon = isset($raw_item['icon']) ? $this->sanitize_icon($raw_item['icon']) : 'dashicons-admin-links';
            $color = isset($raw_item['color']) ? sanitize_hex_color($raw_item['color']) : '#2271b1';
            $new_tab = !empty($raw_item['new_tab']) ? 1 : 0;

            if ('' === $title && '' === $url) {
                continue;
            }

            if (!$color) {
                $color = '#2271b1';
            }

            $items[] = array(
                'title' => $title,
                'url' => $url,
                'description' => $description,
                'icon' => $icon,
                'color' => $color,
                'new_tab' => $new_tab,
            );
        }

        return $items;
    }

    private function sanitize_meta_boxes($raw_meta_boxes)
    {
        $meta_boxes = array();

        if (!is_array($raw_meta_boxes)) {
            return $meta_boxes;
        }

        foreach ($raw_meta_boxes as $raw_meta_box) {
            if (!is_array($raw_meta_box)) {
                continue;
            }

            $title = isset($raw_meta_box['title']) ? sanitize_text_field($raw_meta_box['title']) : '';
            $content = isset($raw_meta_box['content']) ? wp_kses_post($raw_meta_box['content']) : '';
            $context = isset($raw_meta_box['context']) ? $this->normalize_meta_box_context($raw_meta_box['context']) : 'normal';
            $priority = isset($raw_meta_box['priority']) ? $this->normalize_meta_box_priority($raw_meta_box['priority']) : 'default';

            if ('' === trim($title) && '' === trim($content)) {
                continue;
            }

            if ('' === trim($title) || '' === trim($content)) {
                continue;
            }

            $meta_boxes[] = array(
                'title' => $title,
                'content' => $content,
                'context' => $context,
                'priority' => $priority,
            );
        }

        return $meta_boxes;
    }

    private function sanitize_icon($icon)
    {
        $icon = strtolower(trim((string) $icon));
        $icon = preg_replace('/[^a-z0-9\-]/', '', $icon);

        if ('' === $icon) {
            $icon = 'dashicons-admin-links';
        }

        if (0 !== strpos($icon, 'dashicons-')) {
            $icon = 'dashicons-admin-links';
        }

        return $icon;
    }
}

register_activation_hook(__FILE__, array('PAC_Painel_Admin_Cliente', 'activate'));
new PAC_Painel_Admin_Cliente();
