<?php
// Habilita suporte a imagens destacadas
add_theme_support('post-thumbnails');

// // Modificar as meta tags Open Graph para usar imagens com proporção 16:9
// add_filter('wp_head', 'modify_og_image_for_google', 5);

// function modify_og_image_for_google() {
//     if (is_singular()) {
//         // Remover as tags Open Graph padrão do WordPress
//         remove_action('wp_head', 'jetpack_og_tags');          // Se estiver usando Jetpack
//         remove_action('wp_head', 'wpseo_head');               // Temporariamente remove Yoast SEO head
        
//         // Obter o ID do post atual
//         $post_id = get_the_ID();
        
//         // URL para a versão redimensionada 16:9 da imagem
//         $thumbnail_url = get_template_directory_uri() . '/thumbnail.php?id=' . $post_id;
        
//         // Adicionar meta tags Open Graph personalizadas
//         echo '<meta property="og:image" content="' . esc_url($thumbnail_url) . '" />' . "\n";
//         echo '<meta property="og:image:width" content="1200" />' . "\n";
//         echo '<meta property="og:image:height" content="675" />' . "\n"; // Proporção 16:9
        
//         // Adicionar novamente o Yoast SEO head após nossa modificação
//         add_action('wp_head', 'wpseo_head');
//     }
// }