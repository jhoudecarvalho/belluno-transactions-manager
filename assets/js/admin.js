/**
 * JavaScript do plugin Belluno Transactions Manager
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        var searchInput = $('#btm-search-input');
        var searchForm = $('#btm-search-form');
        var searchTimeout;
        
        // Busca em tempo real (opcional - desabilitado por padrão)
        // Descomente as linhas abaixo para habilitar busca em tempo real
        /*
        searchInput.on('input', function() {
            var searchTerm = $(this).val();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length >= 3 || searchTerm.length === 0) {
                searchTimeout = setTimeout(function() {
                    performSearch(searchTerm);
                }, 500);
            }
        });
        */
        
        // Busca ao pressionar Enter
        searchForm.on('submit', function(e) {
            e.preventDefault();
            var searchTerm = searchInput.val();
            
            if (searchTerm.length > 0) {
                window.location.href = btmAjax.ajax_url.replace('admin-ajax.php', 'admin.php') + 
                    '?page=belluno-transactions&search=' + encodeURIComponent(searchTerm);
            } else {
                window.location.href = btmAjax.ajax_url.replace('admin-ajax.php', 'admin.php') + 
                    '?page=belluno-transactions';
            }
        });
        
        // Função para busca AJAX (se necessário no futuro)
        function performSearch(term) {
            $.ajax({
                url: btmAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'btm_search_transactions',
                    nonce: btmAjax.nonce,
                    search: term
                },
                beforeSend: function() {
                    $('.btm-transactions-container').html('<p>Buscando...</p>');
                },
                success: function(response) {
                    if (response.success) {
                        $('.btm-transactions-container').html(response.data.html);
                    }
                },
                error: function() {
                    $('.btm-transactions-container').html('<p>Erro ao buscar transações.</p>');
                }
            });
        }
    });
})(jQuery);

