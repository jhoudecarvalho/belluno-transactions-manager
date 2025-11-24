<?php
/**
 * Plugin Name: Belluno Transactions Manager
 * Plugin URI: https://cdwtech.com.br
 * Description: Gerencia e busca transações de pagamento do gateway Belluno (wc-belluno) integrado ao WooCommerce.
 * Version: 1.0.0
 * Author: Jhou de Carvalho - CDW TECH
 * Author URI: https://cdwtech.com.br
 * Text Domain: belluno-transactions-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Verifica se WooCommerce está ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Define constantes do plugin
define('BTM_VERSION', '1.0.0');
define('BTM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BTM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BTM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principal do plugin
 */
class Belluno_Transactions_Manager {
    
    /**
     * Instância única do plugin
     */
    private static $instance = null;
    
    /**
     * Retorna instância única do plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializa hooks do WordPress
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_btm_search_transactions', array($this, 'ajax_search_transactions'));
    }
    
    /**
     * Adiciona item de menu no admin
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Transações Belluno', 'belluno-transactions-manager'),
            __('Transações Belluno', 'belluno-transactions-manager'),
            'manage_woocommerce',
            'belluno-transactions',
            array($this, 'render_admin_page'),
            'dashicons-money-alt',
            56
        );
    }
    
    /**
     * Carrega assets do admin
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_belluno-transactions' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'btm-admin-style',
            BTM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BTM_VERSION
        );
        
        wp_enqueue_script(
            'btm-admin-script',
            BTM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            BTM_VERSION,
            true
        );
        
        wp_localize_script('btm-admin-script', 'btmAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('btm_search_nonce')
        ));
    }
    
    /**
     * Renderiza página administrativa
     */
    public function render_admin_page() {
        require_once BTM_PLUGIN_DIR . 'includes/class-btm-transactions-list.php';
        
        $search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $list_table = new BTM_Transactions_List();
        
        if (!empty($search_term)) {
            $list_table->set_search_term($search_term);
        }
        
        $list_table->prepare_items();
        
        include BTM_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * AJAX para busca de transações
     */
    public function ajax_search_transactions() {
        check_ajax_referer('btm_search_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'belluno-transactions-manager')));
        }
        
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        require_once BTM_PLUGIN_DIR . 'includes/class-btm-transactions-list.php';
        $list_table = new BTM_Transactions_List();
        $list_table->set_search_term($search_term);
        $list_table->prepare_items();
        
        ob_start();
        $list_table->display();
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
}

/**
 * Inicializa o plugin
 */
function btm_init() {
    return Belluno_Transactions_Manager::get_instance();
}

// Inicia o plugin
add_action('plugins_loaded', 'btm_init');

