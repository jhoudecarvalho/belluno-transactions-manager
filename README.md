# Belluno Transactions Manager

Plugin WordPress para gerenciar e buscar transações de pagamento do gateway Belluno (wc-belluno) integrado ao WooCommerce.

## Funcionalidades

- **Painel Administrativo Independente**: Item de menu próprio no WordPress admin
- **Listagem de Transações**: Exibe todas as compras realizadas com cartão de crédito via Belluno
- **Busca Inteligente Unificada**: Campo de busca único que localiza por:
  - NSU
  - ID da transação
  - Nome do titular do cartão
  - CPF/CNPJ do titular
  - Número do pedido do WooCommerce
- **Paginação**: Suporte para grandes volumes de dados
- **Interface Responsiva**: Design moderno e responsivo

## Requisitos

- WordPress 5.8+
- WooCommerce 6.0+
- Plugin wc-belluno instalado e ativo

## Instalação

1. Faça upload da pasta `belluno-transactions-manager` para `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Acesse o menu "Transações Belluno" no painel administrativo

## Uso

1. Acesse **Transações Belluno** no menu lateral do WordPress
2. Use o campo de busca para localizar transações por:
   - NSU do pagamento
   - ID da transação Belluno
   - Nome do cliente/titular
   - CPF ou CNPJ
   - Número do pedido
3. Navegue pelas páginas usando a paginação na parte inferior

## Colunas Exibidas

- **Pedido**: Número do pedido WooCommerce (link para edição)
- **NSU**: NSU do pagamento
- **ID Transação**: ID da transação no Belluno
- **Cliente**: Nome do cliente
- **CPF/CNPJ**: Documento do titular do cartão
- **Valor**: Valor total com número de parcelas
- **Data**: Data e hora da transação
- **Status**: Status da transação (Pago, Recusado, Processando, etc.)
- **Cartão**: Bandeira e últimos 4 dígitos do cartão

## Compatibilidade

- Compatível com HPOS (High-Performance Order Storage) do WooCommerce
- Compatível com estrutura tradicional de posts do WooCommerce

## Suporte

Para suporte, entre em contato:
- **Desenvolvedor**: Jhou de Carvalho
- **Empresa**: CDW TECH
- **Website**: https://cdwtech.com.br
- **Email**: comercias@cdwtech.com.br

## Changelog

### 1.0.0
- Versão inicial
- Listagem de transações Belluno
- Busca unificada
- Paginação
- Interface responsiva

