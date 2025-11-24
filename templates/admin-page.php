<?php
/**
 * Template da página administrativa
 */

if (!defined('ABSPATH')) {
    exit;
}

$search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
?>

<div class="wrap btm-admin-wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Transações Belluno', 'belluno-transactions-manager'); ?>
    </h1>
    
    <hr class="wp-header-end">
    
    <div class="btm-search-box">
        <form method="get" action="<?php echo admin_url('admin.php'); ?>" id="btm-search-form">
            <input type="hidden" name="page" value="belluno-transactions">
            
            <p class="search-box">
                <label class="screen-reader-text" for="btm-search-input">
                    <?php _e('Buscar transações:', 'belluno-transactions-manager'); ?>
                </label>
                <input 
                    type="search" 
                    id="btm-search-input" 
                    name="search" 
                    value="<?php echo esc_attr($search_term); ?>" 
                    placeholder="<?php esc_attr_e('Buscar por NSU, ID da transação, nome, CPF/CNPJ ou número do pedido...', 'belluno-transactions-manager'); ?>"
                    class="btm-search-field"
                />
                <button type="submit" class="button" id="btm-search-submit">
                    <?php _e('Buscar', 'belluno-transactions-manager'); ?>
                </button>
                <?php if (!empty($search_term)): ?>
                    <a href="<?php echo admin_url('admin.php?page=belluno-transactions'); ?>" class="button">
                        <?php _e('Limpar', 'belluno-transactions-manager'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </form>
    </div>
    
    <div class="btm-transactions-container">
        <?php $list_table->display(); ?>
    </div>
</div>

