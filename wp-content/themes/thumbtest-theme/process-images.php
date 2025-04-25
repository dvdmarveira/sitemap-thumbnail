<?php
/**
 * Script para importar posts do Canal Meio e baixar thumbnails em 16:9
 */

define('SITEMAP_URL', 'https://www.canalmeio.com.br/sitemap-news.xml');
define('DOWNLOAD_DIR', __DIR__ . '/baixadas');
define('LIMIT', 10); // Limita a 10 posts por execução

// Diretório para cache das imagens processadas via thumbnail.php
define('THUMBNAIL_CACHE_DIR', __DIR__ . '/cache');

// Criar diretórios se não existirem
foreach ([DOWNLOAD_DIR, THUMBNAIL_CACHE_DIR] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

function extract_og_image($html) {
    if (preg_match('/<meta property="og:image" content="([^"]+)"/i', $html, $matches)) {
        return $matches[1];
    }
    return false;
}

function extract_title($html) {
    if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
        return trim($matches[1]);
    }
    return 'sem-titulo';
}

function sanitize_filename($title) {
    return preg_replace('/[^a-z0-9\-_]/i', '_', strtolower($title));
}

// 1. Obter sitemap
$sitemap_content = @file_get_contents(SITEMAP_URL);
if (!$sitemap_content) {
    die("❌ Não foi possível acessar o sitemap.");
}

$xml = @simplexml_load_string($sitemap_content);
if (!$xml) {
    die("❌ Erro ao interpretar o XML do sitemap.");
}

$counter = 0;

foreach ($xml->url as $url_entry) {
    $post_url = (string)$url_entry->loc;
    echo "🔗 Verificando: $post_url\n";

    $html = @file_get_contents($post_url);
    if (!$html) {
        echo "⚠️ Erro ao acessar o post: $post_url\n";
        continue;
    }

    $title = extract_title($html);
    $og_image = extract_og_image($html);

    if (!$og_image) {
        echo "❌ Nenhuma imagem encontrada: $post_url\n";
        continue;
    }

    echo "📌 Título: $title\n";
    echo "🖼️ Imagem: $og_image\n";

    // Baixar a imagem original
    $image_data = @file_get_contents($og_image);
    if (!$image_data) {
        echo "⚠️ Falha ao baixar imagem\n";
        continue;
    }

    $filename = sanitize_filename($title) . '.jpg';
    $file_path = DOWNLOAD_DIR . '/' . $filename;

    file_put_contents($file_path, $image_data);
    echo "✅ Imagem original salva: $file_path\n";

    // --- INTEGRAÇÃO COM WordPress ---
    require_once(__DIR__ . '/../../../wp-load.php');

    // Criar post no WordPress se não existir
    $existing_post = get_page_by_title($title, OBJECT, 'post');
    if (!$existing_post) {
        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'draft',
            'post_type' => 'post',
        ]);
        echo "📝 Post criado com ID: $post_id\n";

        // Anexar imagem destacada
        $upload_dir = wp_upload_dir();
        $new_file_path = $upload_dir['path'] . '/' . basename($file_path);
        copy($file_path, $new_file_path);

        $filetype = wp_check_filetype(basename($new_file_path), null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name(basename($new_file_path)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $new_file_path, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $new_file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        set_post_thumbnail($post_id, $attach_id);

    } else {
        $post_id = $existing_post->ID;
        echo "🔄 Post já existente. ID: $post_id\n";
    }

    // --- GERAR THUMBNAIL VIA thumbnail.php ---
    $thumb_url = "http://thumbtest.local/wp-content/themes/thumbtest-theme/thumbnail.php?id=$post_id";
    $thumb_data = @file_get_contents($thumb_url);

    if ($thumb_data) {
        $thumb_filename = sanitize_filename($title) . '_16x9.jpg';
        $thumb_path = THUMBNAIL_CACHE_DIR . '/' . $thumb_filename;
        file_put_contents($thumb_path, $thumb_data);
        echo "🖼️ Thumbnail gerado e salvo: $thumb_path\n";
    } else {
        echo "⚠️ Erro ao gerar thumbnail via thumbnail.php\n";
    }

    echo "---------------------------\n";

    if (++$counter >= LIMIT) break;
}
