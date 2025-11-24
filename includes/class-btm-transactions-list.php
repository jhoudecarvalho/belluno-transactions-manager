<?php
/**
 * Classe para listar transações Belluno
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;

class BTM_Transactions_List {
    
    private $search_term = '';
    private $items_per_page = 20;
    
    /**
     * Define termo de busca
     */
    public function set_search_term($term) {
        $this->search_term = $term;
    }
    
    /**
     * Prepara itens para exibição
     */
    public function prepare_items() {
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $this->items_per_page;
        
        $transactions = $this->get_transactions($this->items_per_page, $offset);
        $total_items = $this->get_total_transactions();
        
        $this->items = $transactions;
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $this->items_per_page,
            'total_pages' => ceil($total_items / $this->items_per_page)
        ));
    }
    
    /**
     * Obtém número da página atual
     */
    private function get_pagenum() {
        return isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    }
    
    /**
     * Busca transações Belluno
     */
    private function get_transactions($limit = 20, $offset = 0) {
        global $wpdb;
        
        $transactions = array();
        
        // Verifica se está usando HPOS
        $using_hpos = OrderUtil::custom_orders_table_usage_is_enabled();
        
        if ($using_hpos) {
            // HPOS - usa wc_orders_meta
            $meta_table = $wpdb->prefix . 'wc_orders_meta';
            $orders_table = $wpdb->prefix . 'wc_orders';
            $order_id_col = 'order_id';
            $order_id_field = 'id';
        } else {
            // Estrutura tradicional - usa postmeta
            $meta_table = $wpdb->prefix . 'postmeta';
            $orders_table = $wpdb->prefix . 'posts';
            $order_id_col = 'post_id';
            $order_id_field = 'ID';
        }
        
        // Busca apenas pedidos com transação Belluno (cartão de crédito)
        $query = "
            SELECT DISTINCT m1.{$order_id_col} as order_id
            FROM {$meta_table} m1
            INNER JOIN {$orders_table} o ON o.{$order_id_field} = m1.{$order_id_col}
            WHERE m1.meta_key = '_belluno_transaction_id'
            AND m1.meta_value != ''
            AND m1.meta_value IS NOT NULL
            AND EXISTS (
                SELECT 1 FROM {$meta_table} m_nsu 
                WHERE m_nsu.{$order_id_col} = m1.{$order_id_col} 
                AND m_nsu.meta_key = '_nsu_payment' 
                AND m_nsu.meta_value != ''
                AND m_nsu.meta_value IS NOT NULL
            )
        ";
        
        // Aplica filtro de busca se houver
        if (!empty($this->search_term)) {
            $search = '%' . $wpdb->esc_like($this->search_term) . '%';
            
            $query .= " AND (
                m1.meta_value LIKE %s
                OR EXISTS (
                    SELECT 1 FROM {$meta_table} m2 
                    WHERE m2.{$order_id_col} = m1.{$order_id_col} 
                    AND m2.meta_key = '_nsu_payment' 
                    AND m2.meta_value LIKE %s
                )
                OR EXISTS (
                    SELECT 1 FROM {$meta_table} m3 
                    WHERE m3.{$order_id_col} = m1.{$order_id_col} 
                    AND m3.meta_key = '_card_holder' 
                    AND m3.meta_value LIKE %s
                )
                OR EXISTS (
                    SELECT 1 FROM {$meta_table} m4 
                    WHERE m4.{$order_id_col} = m1.{$order_id_col} 
                    AND m4.meta_key = '_card_holder_document' 
                    AND m4.meta_value LIKE %s
                )
                OR CAST(o.{$order_id_field} AS CHAR) LIKE %s
            )";
            
            $query .= " ORDER BY o.post_date DESC LIMIT %d OFFSET %d";
            
            $prepared = $wpdb->prepare(
                $query,
                $search, // transaction_id
                $search, // nsu_payment
                $search, // card_holder
                $search, // card_holder_document
                $search, // order_id
                $limit,
                $offset
            );
        } else {
            $query .= " ORDER BY o.post_date DESC LIMIT %d OFFSET %d";
            $prepared = $wpdb->prepare($query, $limit, $offset);
        }
        
        $order_ids = $wpdb->get_col($prepared);
        
        // Busca dados completos de cada pedido
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                continue;
            }
            
            // Verifica se é pagamento com cartão (tem NSU)
            $nsu = $order->get_meta('_nsu_payment');
            if (empty($nsu)) {
                continue; // Pula boletos e PIX
            }
            
            $transaction_id = $order->get_meta('_belluno_transaction_id');
            $status = $order->get_meta('_belluno_status');
            $card = $order->get_meta('_card');
            $card_brand = $order->get_meta('_card_brand');
            $card_holder = $order->get_meta('_card_holder');
            $card_holder_document = $order->get_meta('_card_holder_document');
            $installments = $order->get_meta('_installments_number');
            
            $transactions[] = array(
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'nsu' => $nsu,
                'transaction_id' => $transaction_id,
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'cpf_cnpj' => $card_holder_document ?: ($order->get_meta('_billing_cpf') ?: $order->get_meta('_billing_cnpj')),
                'amount' => $order->get_total(),
                'date' => $order->get_date_created()->date_i18n('d/m/Y H:i'),
                'status' => $this->format_status($status),
                'card_last_digits' => $card,
                'card_brand' => $this->format_card_brand($card_brand),
                'installments' => $installments ? $installments . 'x' : '1x',
                'order_status' => wc_get_order_status_name($order->get_status())
            );
        }
        
        return $transactions;
    }
    
    /**
     * Obtém total de transações
     */
    private function get_total_transactions() {
        global $wpdb;
        
        $using_hpos = OrderUtil::custom_orders_table_usage_is_enabled();
        
        if ($using_hpos) {
            $meta_table = $wpdb->prefix . 'wc_orders_meta';
            $orders_table = $wpdb->prefix . 'wc_orders';
            $order_id_col = 'order_id';
            $order_id_field = 'id';
        } else {
            $meta_table = $wpdb->prefix . 'postmeta';
            $orders_table = $wpdb->prefix . 'posts';
            $order_id_col = 'post_id';
            $order_id_field = 'ID';
        }
        
        $query = "
            SELECT COUNT(DISTINCT m1.{$order_id_col})
            FROM {$meta_table} m1
            INNER JOIN {$orders_table} o ON o.{$order_id_field} = m1.{$order_id_col}
            WHERE m1.meta_key = '_belluno_transaction_id'
            AND m1.meta_value != ''
            AND m1.meta_value IS NOT NULL
            AND EXISTS (
                SELECT 1 FROM {$meta_table} m2 
                WHERE m2.{$order_id_col} = m1.{$order_id_col} 
                AND m2.meta_key = '_nsu_payment' 
                AND m2.meta_value != ''
                AND m2.meta_value IS NOT NULL
            )
        ";
        
        if (!empty($this->search_term)) {
            $search = '%' . $wpdb->esc_like($this->search_term) . '%';
            
            $query .= " AND (
                m1.meta_value LIKE %s
                OR EXISTS (
                    SELECT 1 FROM {$meta_table} m3 
                    WHERE m3.{$order_id_col} = m1.{$order_id_col} 
                    AND m3.meta_key = '_nsu_payment' 
                    AND m3.meta_value LIKE %s
                )
                OR EXISTS (
                    SELECT 1 FROM {$meta_table} m4 
                    WHERE m4.{$order_id_col} = m1.{$order_id_col} 
                    AND m4.meta_key = '_card_holder' 
                    AND m4.meta_value LIKE %s
                )
                OR EXISTS (
                    SELECT 1 FROM {$meta_table} m5 
                    WHERE m5.{$order_id_col} = m1.{$order_id_col} 
                    AND m5.meta_key = '_card_holder_document' 
                    AND m5.meta_value LIKE %s
                )
                OR CAST(o.{$order_id_field} AS CHAR) LIKE %s
            )";
            
            return $wpdb->get_var($wpdb->prepare(
                $query,
                $search,
                $search,
                $search,
                $search,
                $search
            ));
        }
        
        return $wpdb->get_var($query);
    }
    
    /**
     * Formata status da transação
     */
    private function format_status($status) {
        $statuses = array(
            'Paid' => __('Pago', 'belluno-transactions-manager'),
            'Refused' => __('Recusado', 'belluno-transactions-manager'),
            'Processing' => __('Processando', 'belluno-transactions-manager'),
            'Manual Analysis' => __('Análise Manual', 'belluno-transactions-manager'),
            'Client Manual Analysis' => __('Análise Manual Cliente', 'belluno-transactions-manager'),
            'Open' => __('Aberto', 'belluno-transactions-manager'),
            'Unpaid' => __('Não Pago', 'belluno-transactions-manager')
        );
        
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
    
    /**
     * Formata bandeira do cartão
     */
    private function format_card_brand($brand) {
        $brands = array(
            'MASTERCARD' => 'Mastercard',
            'VISA' => 'Visa',
            'ELO' => 'Elo',
            'HIPERCARD' => 'Hipercard',
            'CABAL' => 'Cabal',
            'HIPER' => 'Hiper',
            'AMEX' => 'American Express'
        );
        
        $brand_upper = strtoupper($brand);
        return isset($brands[$brand_upper]) ? $brands[$brand_upper] : $brand;
    }
    
    /**
     * Exibe tabela de transações
     */
    public function display() {
        $this->display_table();
        $this->display_pagination();
    }
    
    /**
     * Exibe tabela
     */
    private function display_table() {
        ?>
        <table class="wp-list-table widefat fixed striped btm-transactions-table">
            <thead>
                <tr>
                    <th class="column-order-number"><?php _e('Pedido', 'belluno-transactions-manager'); ?></th>
                    <th class="column-nsu"><?php _e('NSU', 'belluno-transactions-manager'); ?></th>
                    <th class="column-transaction-id"><?php _e('ID Transação', 'belluno-transactions-manager'); ?></th>
                    <th class="column-customer"><?php _e('Cliente', 'belluno-transactions-manager'); ?></th>
                    <th class="column-document"><?php _e('CPF/CNPJ', 'belluno-transactions-manager'); ?></th>
                    <th class="column-amount"><?php _e('Valor', 'belluno-transactions-manager'); ?></th>
                    <th class="column-date"><?php _e('Data', 'belluno-transactions-manager'); ?></th>
                    <th class="column-status"><?php _e('Status', 'belluno-transactions-manager'); ?></th>
                    <th class="column-card"><?php _e('Cartão', 'belluno-transactions-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($this->items)): ?>
                    <tr>
                        <td colspan="9" class="no-items">
                            <?php _e('Nenhuma transação encontrada.', 'belluno-transactions-manager'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($this->items as $transaction): ?>
                        <tr>
                            <td class="column-order-number">
                                <a href="<?php echo admin_url('post.php?post=' . $transaction['order_id'] . '&action=edit'); ?>" target="_blank">
                                    #<?php echo esc_html($transaction['order_number']); ?>
                                </a>
                            </td>
                            <td class="column-nsu">
                                <strong><?php echo esc_html($transaction['nsu']); ?></strong>
                            </td>
                            <td class="column-transaction-id">
                                <?php echo esc_html($transaction['transaction_id']); ?>
                            </td>
                            <td class="column-customer">
                                <?php echo esc_html($transaction['customer_name']); ?>
                            </td>
                            <td class="column-document">
                                <?php echo esc_html($transaction['cpf_cnpj']); ?>
                            </td>
                            <td class="column-amount">
                                <strong><?php echo wc_price($transaction['amount']); ?></strong>
                                <small>(<?php echo esc_html($transaction['installments']); ?>)</small>
                            </td>
                            <td class="column-date">
                                <?php echo esc_html($transaction['date']); ?>
                            </td>
                            <td class="column-status">
                                <span class="btm-status btm-status-<?php echo esc_attr(strtolower($transaction['status'])); ?>">
                                    <?php echo esc_html($transaction['status']); ?>
                                </span>
                            </td>
                            <td class="column-card">
                                <?php if ($transaction['card_last_digits']): ?>
                                    <span class="btm-card-info">
                                        <?php echo esc_html($transaction['card_brand']); ?> 
                                        ****<?php echo esc_html($transaction['card_last_digits']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="btm-card-info">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Exibe paginação
     */
    private function display_pagination() {
        $pagination_args = $this->get_pagination_args();
        
        if ($pagination_args['total_pages'] <= 1) {
            return;
        }
        
        $current_page = $this->get_pagenum();
        $base_url = admin_url('admin.php?page=belluno-transactions');
        
        if (!empty($this->search_term)) {
            $base_url .= '&search=' . urlencode($this->search_term);
        }
        
        ?>
        <div class="btm-pagination">
            <span class="displaying-num">
                <?php 
                printf(
                    __('Exibindo %d de %d transações', 'belluno-transactions-manager'),
                    count($this->items),
                    $pagination_args['total_items']
                );
                ?>
            </span>
            
            <div class="pagination-links">
                <?php if ($current_page > 1): ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $base_url)); ?>">
                        &laquo; <?php _e('Anterior', 'belluno-transactions-manager'); ?>
                    </a>
                <?php endif; ?>
                
                <span class="paging-input">
                    <?php 
                    printf(
                        __('Página %d de %d', 'belluno-transactions-manager'),
                        $current_page,
                        $pagination_args['total_pages']
                    );
                    ?>
                </span>
                
                <?php if ($current_page < $pagination_args['total_pages']): ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $base_url)); ?>">
                        <?php _e('Próxima', 'belluno-transactions-manager'); ?> &raquo;
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Obtém argumentos de paginação
     */
    private function get_pagination_args() {
        return isset($this->_pagination_args) ? $this->_pagination_args : array(
            'total_items' => 0,
            'per_page' => $this->items_per_page,
            'total_pages' => 0
        );
    }
    
    /**
     * Define argumentos de paginação
     */
    private function set_pagination_args($args) {
        $this->_pagination_args = $args;
    }
}

