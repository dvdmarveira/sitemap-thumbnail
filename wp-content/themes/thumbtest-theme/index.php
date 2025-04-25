<?php get_header(); ?>

<main>
    <h1>Testando Thumbnails</h1>

    <?php
    $query = new WP_Query([
        'post_type' => 'post',
        'posts_per_page' => 5,
    ]);

    if ($query->have_posts()):
        while ($query->have_posts()): $query->the_post();
            ?>
            <article>
                <h2><?php the_title(); ?></h2>
                <p><?php the_excerpt(); ?></p>

                <!-- Exibe a imagem gerada pelo thumbnail.php -->
                <img src="<?php echo get_the_post_thumbnail_url(get_the_ID(), 'full'); ?>" alt="Thumbnail">
                </article>
            <hr>
            <?php
        endwhile;
    else:
        echo "<p>Sem posts</p>";
    endif;

    wp_reset_postdata();
    ?>
</main>

<?php get_footer(); ?>
