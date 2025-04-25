<?php
/**
 * Script para processar thumbnails a partir do sitemap do Google News
 * 
 * Este script lê o sitemap do Google News, extrai as URLs dos posts,
 * obtém os IDs dos posts diretamente das páginas HTML e gera URLs para o script thumbnail.php.
 * 
 * Esta versão é independente do WordPress e não requer acesso ao banco de dados.
 */

// Configurações
$sitemap_url = 'https://www.canalmeio.com.br/sitemap-news.xml'; // url que deve ser monitorada
$thumbnail_base_url = 'https://thumbtest.local/wp-content/themes/thumbtest-theme/thumbnail.php'; // url para teste
$log_file = __DIR__ . '/thumbnail_processing.log';
$output_file = __DIR__ . '/thumbnail_urls.txt';

// Função para fazer log - necessário!!
function write_log($message) {
    global $log_file;
    $date = date('Y-m-d H:i:s');
    $log_message = "[$date] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $message . PHP_EOL; // Exibir na saída padrão também
}

// Função para obter o conteúdo de uma URL
function get_url_content($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36',
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    
    if ($content === false) {
        $error = error_get_last();
        $error_message = isset($error['message']) ? $error['message'] : "Erro desconhecido";
        write_log("Erro ao acessar URL $url: $error_message");
        return false;
    }
    
    return $content;
}

// Função para extrair URLs do sitemap
function extract_urls_from_sitemap($sitemap_content) {
    $urls = [];
    
    // Carregar o XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($sitemap_content);
    
    if ($xml === false) {
        write_log("Erro ao analisar o XML do sitemap");
        foreach(libxml_get_errors() as $error) {
            write_log("\t" . $error->message);
        }
        libxml_clear_errors();
        return [];
    }
    
    // Extrair as URLs do sitemap
    foreach ($xml->url as $url) {
        $urls[] = (string)$url->loc;
    }
    
    return $urls;
}

// Função para extrair o ID do post do HTML da página
function extract_post_id_from_html($html_content) {

    // Tenta procurar por padrões comuns onde o ID do post é mencionado
    // Padrão 1: class="post-X" ou id="post-X"
    if (preg_match('/(class|id)=["\'](post|article)-(\d+)["\']/', $html_content, $matches)) {
        return (int)$matches[3];
    }
    
    // Padrão 2: wp-X (como em imagens de thumbnail)
    if (preg_match('/wp-image-(\d+)/', $html_content, $matches)) {
        return (int)$matches[1];
    }
    
    // Padrão 3: ?p=X nos links
    if (preg_match('/\?p=(\d+)/', $html_content, $matches)) {
        return (int)$matches[1];
    }
    
    // Padrão 4: "postId":X em scripts JSON
    if (preg_match('/"postId":(\d+)/', $html_content, $matches)) {
        return (int)$matches[1];
    }
    
    // Padrão 5: data-post-id="X"
    if (preg_match('/data-post-id=["\']([\d]+)["\']/', $html_content, $matches)) {
        return (int)$matches[1];
    }
    
    // Padrão 6: post_id = X ou postId = X em scripts
    if (preg_match('/post(_)?id\s*=\s*(\d+)/i', $html_content, $matches)) {
        return (int)$matches[2];
    }
    
    // Padrão 7: "currentID":X em meta tags ou scripts
    if (preg_match('/"currentID":(\d+)/', $html_content, $matches)) {
        return (int)$matches[1];
    }
    
    return 0; // Não foi possível encontrar o ID
}

// Função para extrair URLs das imagens destacadas
function extract_featured_image_urls($html_content) {
    $image_urls = [];
    
    // Tenta procurar por padrões comuns onde a imagem do post pode ser mencionada
    // Padrão 1: imagem destacada em meta tags
    if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html_content, $matches)) {
        $image_urls[] = $matches[1];
    }
    
    // Padrão 2: imagens com classe específica
    preg_match_all('/<img[^>]+class="[^"]*featured-image[^"]*"[^>]+src="([^"]+)"/', $html_content, $matches);
    if (!empty($matches[1])) {
        $image_urls = array_merge($image_urls, $matches[1]);
    }
    
    // Padrão 3: qualquer imagem com ID que pareça ser uma thumbnail
    preg_match_all('/<img[^>]+id="[^"]*thumbnail[^"]*"[^>]+src="([^"]+)"/', $html_content, $matches);
    if (!empty($matches[1])) {
        $image_urls = array_merge($image_urls, $matches[1]);
    }
    
    return array_unique($image_urls);
}

// Função principal
function process_sitemap() {
    global $sitemap_url, $thumbnail_base_url, $output_file;
    
    write_log("Iniciando processamento do sitemap");
    
    // Obter conteúdo do sitemap
    $sitemap_content = get_url_content($sitemap_url);
    if (!$sitemap_content) {
        write_log("Falha ao obter conteúdo do sitemap. Encerrando.");
        return;
    }
    
    // Extrair URLs
    $urls = extract_urls_from_sitemap($sitemap_content);
    $total_urls = count($urls);
    write_log("Encontradas $total_urls URLs no sitemap");
    
    // Contadores para estatísticas
    $processed = 0;
    $failed = 0;
    
    // Arquivo para armazenar as URLs de thumbnail
    $thumbnail_urls = [];
    
    // Processar cada URL
    foreach ($urls as $index => $url) {
        $current = $index + 1;
        write_log("[$current/$total_urls] Processando URL: $url");
        
        // Obter conteúdo da página
        $page_content = get_url_content($url);
        if (!$page_content) {
            write_log("Falha ao obter conteúdo da página. Pulando.");
            $failed++;
            continue;
        }
        
        // Extrair ID do post
        $post_id = extract_post_id_from_html($page_content);
        
        if ($post_id <= 0) {
            write_log("Não foi possível extrair ID do post da página. Tentando alternativas.");
            
            // Extrair URLs das imagens destacadas
            $image_urls = extract_featured_image_urls($page_content);
            
            if (!empty($image_urls)) {
                write_log("Encontradas " . count($image_urls) . " URLs de imagens destacadas.");
                
                // Extrair ID do post das URLs das imagens
                foreach ($image_urls as $image_url) {
                    if (preg_match('/\/(\d+)\/(thumbnail|medium|large|full)\//', $image_url, $matches)) {
                        $post_id = (int)$matches[1];
                        write_log("ID do post extraído da URL da imagem: $post_id");
                        break;
                    }
                }
            }
            
            // Se ainda não encontrou o ID, tenta extrair do slug da URL
            if ($post_id <= 0) {
                // Extrair slug da URL
                $path = parse_url($url, PHP_URL_PATH);
                $slug = basename(rtrim($path, '/'));
                
                write_log("Não foi possível determinar o ID do post. Usando slug: $slug");
                
                // Como não podemos resolver o ID a partir do slug sem acesso ao banco de dados,
                // vamos registrar isso para processamento manual
                $failed++;
                continue;
            }
        }
        
        // Gerar URL do thumbnail
        $thumbnail_url = "$thumbnail_base_url?id=$post_id";
        write_log("URL do thumbnail gerada: $thumbnail_url");
        
        // Adicionar à lista de URLs de thumbnail
        $thumbnail_urls[] = [
            'original_url' => $url,
            'post_id' => $post_id,
            'thumbnail_url' => $thumbnail_url
        ];
        
        $processed++;
    }
    
    // Salvar as URLs de thumbnail em um arquivo
    $output_content = "";
    foreach ($thumbnail_urls as $item) {
        $output_content .= "Post URL: {$item['original_url']}\n";
        $output_content .= "Post ID: {$item['post_id']}\n";
        $output_content .= "Thumbnail URL: {$item['thumbnail_url']}\n";
        $output_content .= "\n";
    }
    
    file_put_contents($output_file, $output_content);
    
    // Resumo
    write_log("Processamento concluído!");
    write_log("Total de URLs: $total_urls");
    write_log("URLs processadas com sucesso: $processed");
    write_log("URLs com falha: $failed");
    write_log("As URLs de thumbnail foram salvas em: $output_file");
}

// Executar o processamento
write_log("Script iniciado em " . date('Y-m-d H:i:s'));
process_sitemap();
write_log("Script finalizado em " . date('Y-m-d H:i:s'));