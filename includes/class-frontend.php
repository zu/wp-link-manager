<?php
namespace LinkManager;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend: Bindet Bewertungs-Widget, URL-Box und Shortcodes ins Template ein.
 */
class Frontend {

    public function __construct() {
        add_filter( 'the_content', [ $this, 'append_link_meta' ] );
        add_shortcode( 'link_archive', [ $this, 'render_archive_shortcode' ] );
    }

    /**
     * Hängt URL-Box und Bewertungswidget an den Content eines Link-Posts.
     */
    public function append_link_meta( string $content ): string {
        if (
            ! is_singular( Post_Type::SLUG ) ||
            ! in_the_loop() ||
            ! is_main_query()
        ) {
            return $content;
        }

        $post_id = get_the_ID();
        $url     = get_post_meta( $post_id, '_lm_url', true );

        ob_start();

        if ( $url ) : ?>
        <div class="lm-url-box">
            <span class="lm-url-label"><?php esc_html_e( 'Link:', 'link-manager' ); ?></span>
            <a href="<?php echo esc_url( $url ); ?>"
               target="_blank" rel="noopener noreferrer" class="lm-url-link">
                <?php echo esc_html( $url ); ?>
            </a>
            <a href="<?php echo esc_url( $url ); ?>"
               target="_blank" rel="noopener noreferrer" class="lm-btn lm-btn-visit">
                <?php esc_html_e( '→ Seite besuchen', 'link-manager' ); ?>
            </a>
        </div>
        <?php endif; ?>

        <div class="lm-meta-bar">
            <?php
            $terms = get_the_terms( $post_id, Taxonomy::SLUG );
            if ( $terms && ! is_wp_error( $terms ) ) :
            ?>
            <div class="lm-categories">
                <?php foreach ( $terms as $term ) : ?>
                    <a href="<?php echo esc_url( get_term_link( $term ) ); ?>"
                       class="lm-cat-badge">
                        <?php echo esc_html( $term->name ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php echo lm_plugin()->ratings->render_widget( $post_id ); ?>
        </div>

        <?php
        $extra = ob_get_clean();

        return $content . $extra;
    }

    /**
     * Shortcode [link_archive] – zeigt Links mit Filter nach Kategorie.
     */
    public function render_archive_shortcode( array $atts ): string {
        $atts = shortcode_atts( [
            'per_page' => 12,
            'category' => '',
        ], $atts );

        $args = [
            'post_type'      => Post_Type::SLUG,
            'post_status'    => 'publish',
            'posts_per_page' => (int) $atts['per_page'],
            'paged'          => max( 1, absint( get_query_var( 'paged' ) ) ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( $atts['category'] ) {
            $args['tax_query'] = [ [
                'taxonomy' => Taxonomy::SLUG,
                'field'    => 'slug',
                'terms'    => sanitize_key( $atts['category'] ),
            ] ];
        }

        $query = new \WP_Query( $args );

        ob_start(); ?>

        <div class="lm-archive-grid">
        <?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post();
            $pid    = get_the_ID();
            $url    = get_post_meta( $pid, '_lm_url', true );
            $counts = lm_plugin()->ratings->get_counts( $pid );
            $score  = $counts->up - $counts->down;
            ?>
            <article class="lm-card">
                <?php if ( has_post_thumbnail() ) : ?>
                <a href="<?php the_permalink(); ?>" class="lm-card-thumb">
                    <?php the_post_thumbnail( 'medium' ); ?>
                </a>
                <?php endif; ?>
                <div class="lm-card-body">
                    <h3 class="lm-card-title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>
                    <?php if ( $url ) : ?>
                    <p class="lm-card-url">
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html( wp_parse_url( $url, PHP_URL_HOST ) ); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                    <p class="lm-card-excerpt"><?php the_excerpt(); ?></p>
                    <div class="lm-card-footer">
                        <?php
                        $terms = get_the_terms( $pid, Taxonomy::SLUG );
                        if ( $terms && ! is_wp_error( $terms ) ) : ?>
                            <span class="lm-card-cats">
                            <?php foreach ( $terms as $t ) : ?>
                                <a href="<?php echo esc_url( get_term_link( $t ) ); ?>"
                                   class="lm-cat-badge"><?php echo esc_html( $t->name ); ?></a>
                            <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                        <span class="lm-card-score" title="<?php esc_attr_e( 'Score', 'link-manager' ); ?>">
                            <?php echo esc_html( $score >= 0 ? '+' . $score : $score ); ?>
                        </span>
                    </div>
                </div>
            </article>
        <?php endwhile; wp_reset_postdata();
        else : ?>
            <p><?php esc_html_e( 'Noch keine Links vorhanden.', 'link-manager' ); ?></p>
        <?php endif; ?>
        </div>

        <?php if ( $query->max_num_pages > 1 ) :
            echo paginate_links( [
                'total'   => $query->max_num_pages,
                'current' => max( 1, absint( get_query_var( 'paged' ) ) ),
            ] );
        endif; ?>

        <?php
        return ob_get_clean();
    }
}
