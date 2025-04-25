<?php
/**
 * Script para processar imagens do sitemap do Canal Meio
 * Este script lê o sitemap do Google News, extrai as URLs dos posts
 * e processa as imagens destacadas para garantir proporção 16:9
 */

define('SITEMAP_URL', 'https://www.canalmeio.com.br/sitemap-news.xml');
define('LOG_FILE', __DIR__ . '/sitemap_processing.log');

// Configurar log
function log_message($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
    echo $log_entry;
}

// Função para extrair o ID do post da URL
function get_post_id_from_url($url) {
    if (preg_match('/\/(\d+)\//', $url, $matches)) {
        return $matches[1];
    }
    return false;
}

// Carregar WordPress
require_once(dirname(__FILE__, 4) . '/wp-load.php');

// 1. Obter e processar o sitemap
log_message("Iniciando processamento do sitemap...");

$sitemap_content = @file_get_contents(SITEMAP_URL);
if (!$sitemap_content) {
    log_message("❌ Erro: Não foi possível acessar o sitemap.");
    die();
}

$xml = @simplexml_load_string($sitemap_content);
if (!$xml) {
    log_message("❌ Erro: Falha ao interpretar o XML do sitemap.");
    die();
}

// 2. Processar cada URL do sitemap
foreach ($xml->url as $url_entry) {
    $post_url = (string)$url_entry->loc;
    log_message("Processando URL: $post_url");

    // Extrair ID do post
    $post_id = get_post_id_from_url($post_url);
    if (!$post_id) {
        log_message("⚠️ Não foi possível extrair ID do post da URL: $post_url");
        continue;
    }

    // Verificar se o post existe
    $post = get_post($post_id);
    if (!$post) {
        log_message("⚠️ Post não encontrado com ID: $post_id");
        continue;
    }

    // Verificar se tem imagem destacada
    if (!has_post_thumbnail($post_id)) {
        log_message("⚠️ Post não tem imagem destacada: $post_id");
        continue;
    }

    // Gerar thumbnail 16:9
    $thumb_url = "http://thumbtest.local/wp-content/themes/thumbtest-theme/thumbnail.php?id=$post_id";
    $thumb_data = @file_get_contents($thumb_url);

    if ($thumb_data) {
        log_message("✅ Thumbnail processado com sucesso para post ID: $post_id");
    } else {
        log_message("❌ Erro ao processar thumbnail para post ID: $post_id");
    }
}

log_message("Processamento concluído!"); 