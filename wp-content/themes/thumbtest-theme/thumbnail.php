<?php
/**
 * Script para processar thumbnails e garantir proporção 16:9
 * 
 * Uso: thumbnail.php?id={post_id}
 * Ex: https://thumbtest.local/wp-content/themes/thumbtest-theme/thumbnail.php?id=6
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: image/jpeg');

// Carregar WordPress 
// require_once(__DIR__ . '/../../../wp-load.php'); ou
require_once(dirname(__FILE__, 4) . '/wp-load.php');

// Diretório para cache de imagens processadas - Desativado, não preciso salvar as imgs
// $cache_dir = __DIR__ . '/cache';
// if (!file_exists($cache_dir)) {
//     mkdir($cache_dir, 0755, true);
// }

// Função para obter o caminho da imagem destacada
function get_featured_image_path($post_id) {
    // Verificar se o post existe
    $post = get_post($post_id);
    if (!$post) return false;

    $thumbnail_id = get_post_thumbnail_id($post_id);
    if (!$thumbnail_id) return false;

    $image_path = get_attached_file($thumbnail_id);
    if (!$image_path || !file_exists($image_path)) return false;

    return $image_path;
}

// Função para verificar se a imagem já está em proporção 16:9
function is_16_9_ratio($width, $height) {
    $ratio = $width / $height;
    $target = 16 / 9;
    // Tolerância de 1% para pequenas variações
    return abs($ratio - $target) < 0.01;
}

// Função para processar imagem e adicionar bordas se necessário
function process_image_to_16_9($image_path) {
    if (!file_exists($image_path)) {
        return false;
    }

    $image_info = @getimagesize($image_path);
    if (!$image_info) {
        return false;
    }

    $mime_type = $image_info['mime'];
    // Criar imagem a partir do arquivo original
    switch ($mime_type) {
        case 'image/jpeg':
            $source_image = @imagecreatefromjpeg($image_path);
            break;
        case 'image/png':
            $source_image = @imagecreatefrompng($image_path);
            break;
        case 'image/gif':
            $source_image = @imagecreatefromgif($image_path);
            break;
        default:
            return false; // Formato não suportado (diferente de JPEG, PNG e GIF)
    }

    if (!$source_image) {
        return false;
    }

    $original_width = imagesx($source_image);
    $original_height = imagesy($source_image);

    // Se já estiver em 16:9, não precisa modificar
    if (is_16_9_ratio($original_width, $original_height)) {
        return $source_image;
    }

    // Calcular proporção atual
    $current_ratio = $original_width / $original_height;
    $target_ratio = 16 / 9;

    // Determinar dimensões do novo canvas mantendo a imagem original inteira
    if ($current_ratio > $target_ratio) {
        // Imagem é mais larga que 16:9 - ajustar altura
        $new_width = $original_width;
        $new_height = $original_width / $target_ratio;
    } else {
        // Imagem é mais alta que 16:9 - ajustar largura
        $new_height = $original_height;
        $new_width = $original_height * $target_ratio;
    }

    // Criar nova imagem com fundo branco
    $new_image = imagecreatetruecolor((int)$new_width, (int)$new_height);
    // Verificar se a criação do recurso foi bem-sucedida
    if (!$new_image) {
        imagedestroy($source_image);
        return false;
    }

    // Definir fundo branco
    $white = imagecolorallocate($new_image, 255, 255, 255);
    imagefill($new_image, 0, 0, $white);

    // Preservar transparência para PNGs
    if ($mime_type == 'image/png') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
    }

    // Calcular posição para centralizar a imagem original
    $dest_x = (int)(($new_width - $original_width) / 2);
    $dest_y = (int)(($new_height - $original_height) / 2);

    // Copiar imagem original centralizada no novo canvas
    imagecopy($new_image, $source_image, $dest_x, $dest_y, 0, 0, $original_width, $original_height);

    // Liberar memória da imagem original
    imagedestroy($source_image);

    return $new_image;
}

// Obter ID do post via parâmetro GET
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($post_id <= 0) {
    http_response_code(400);
    exit("ID de post inválido");
}

// // Caminho para cache da imagem processada
// $cache_filename = $cache_dir . '/thumb_' . $post_id . '_' . md5($post_id) . '.jpg';

// // Se já existe versão no cache e não está expirado (24h), usa direto
// if (file_exists($cache_filename) && (time() - filemtime($cache_filename) < 86400)) {
//     readfile($cache_filename);
//     exit;
// }

// Obter a imagem destacada do post
$image_path = get_featured_image_path($post_id);
if (!$image_path) {
    http_response_code(404);
    exit("Imagem destacada não encontrada para o post #$post_id");
}

// Processar a imagem (adicionando bordas se necessário)
$processed_image = process_image_to_16_9($image_path);
if (!$processed_image) {
    http_response_code(500);
    exit("Erro ao processar a imagem");
}

// // Salvar no cache
// $cache_result = imagejpeg($processed_image, $cache_filename, 90);

// Exibir a imagem processada
imagejpeg($processed_image, null, 90);

// Liberar memória
imagedestroy($processed_image);
exit;
?>