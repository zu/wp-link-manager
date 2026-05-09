<?php
namespace LinkManager;

defined( 'ABSPATH' ) || exit;

/**
 * Registriert den Custom Post Type „link" sowie alle zugehörigen Meta-Felder.
 */
class Post_Type {

    const SLUG = 'link';

    public function __construct() {
        add_action( 'init',                  [ $this, 'register' ] );
        add_action( 'add_meta_boxes',        [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_' . self::SLUG, [ $this, 'save_meta' ], 10, 2 );
        add_action( 'save_post_' . self::SLUG, [ $this, 'maybe_generate_screenshot' ], 20, 2 );

        // Kommentare für den CPT erlauben
        add_filter( 'comments_open', [ $this, 'force_comments_open' ], 10, 2 );
    }

    public function register(): void {
        $labels = [
            'name'               => __( 'Links',                  'link-manager' ),
            'singular_name'      => __( 'Link',                   'link-manager' ),
            'add_new'            => __( 'Neu',                    'link-manager' ),
            'add_new_item'       => __( 'Neuen Link hinzufügen',  'link-manager' ),
            'edit_item'          => __( 'Link bearbeiten',        'link-manager' ),
            'new_item'           => __( 'Neuer Link',             'link-manager' ),
            'view_item'          => __( 'Link ansehen',           'link-manager' ),
            'search_items'       => __( 'Links suchen',           'link-manager' ),
            'not_found'          => __( 'Keine Links gefunden',   'link-manager' ),
            'not_found_in_trash' => __( 'Papierkorb ist leer',    'link-manager' ),
            'menu_name'          => __( 'Links',                  'link-manager' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,   // Gutenberg-Support
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'links', 'with_front' => false ],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-admin-links',
            'supports'           => [
                'title',
                'editor',       // Beschreibung
                'thumbnail',    // Beitragsbild (= Screenshot)
                'comments',
                'author',
                'excerpt',
            ],
        ];

        register_post_type( self::SLUG, $args );

        // Meta-Felder im REST-API exposieren
        register_post_meta( self::SLUG, '_lm_url', [
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => fn() => current_user_can( 'edit_posts' ),
        ] );

        register_post_meta( self::SLUG, '_lm_screenshot_url', [
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => fn() => current_user_can( 'edit_posts' ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Meta Boxes
    // -------------------------------------------------------------------------

    public function add_meta_boxes(): void {
        add_meta_box(
            'lm_link_details',
            __( 'Link-Details', 'link-manager' ),
            [ $this, 'render_meta_box' ],
            self::SLUG,
            'normal',
            'high'
        );
    }

    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'lm_save_meta', 'lm_meta_nonce' );
        $url            = get_post_meta( $post->ID, '_lm_url',            true );
        $screenshot_url = get_post_meta( $post->ID, '_lm_screenshot_url', true );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="lm_url"><?php esc_html_e( 'Ziel-URL', 'link-manager' ); ?></label></th>
                <td>
                    <input type="url" id="lm_url" name="lm_url"
                           value="<?php echo esc_attr( $url ); ?>"
                           class="large-text" placeholder="https://example.com" required>
                    <p class="description">
                        <?php esc_html_e( 'Die vollständige URL des verlinkten Angebots.', 'link-manager' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Screenshot', 'link-manager' ); ?></label></th>
                <td>
                    <?php if ( $screenshot_url ) : ?>
                        <img src="<?php echo esc_url( $screenshot_url ); ?>"
                             style="max-width:300px;height:auto;border:1px solid #ddd;" alt="Screenshot"><br><br>
                    <?php endif; ?>
                    <label>
                        <input type="checkbox" name="lm_regen_screenshot" value="1">
                        <?php esc_html_e( 'Screenshot beim Speichern neu erstellen', 'link-manager' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'Das Beitragsbild hat Vorrang. Ist keines gesetzt, wird der Screenshot als Beitragsbild verwendet.', 'link-manager' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta( int $post_id, \WP_Post $post ): void {
        if (
            ! isset( $_POST['lm_meta_nonce'] ) ||
            ! wp_verify_nonce( $_POST['lm_meta_nonce'], 'lm_save_meta' ) ||
            defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
            ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        if ( isset( $_POST['lm_url'] ) ) {
            update_post_meta( $post_id, '_lm_url', esc_url_raw( wp_unslash( $_POST['lm_url'] ) ) );
        }
    }

    public function maybe_generate_screenshot( int $post_id, \WP_Post $post ): void {
        if (
            defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
            $post->post_status === 'auto-draft'
        ) {
            return;
        }

        $url         = get_post_meta( $post_id, '_lm_url', true );
        $regen       = isset( $_POST['lm_regen_screenshot'] );
        $has_thumb   = has_post_thumbnail( $post_id );
        $has_shot    = (bool) get_post_meta( $post_id, '_lm_screenshot_url', true );

        // Screenshot neu erstellen wenn: URL vorhanden UND (noch kein Screenshot ODER explizit angefordert)
        if ( $url && ( ! $has_shot || $regen ) ) {
            lm_plugin()->screenshot->generate( $post_id, $url );
        }
    }

    public function force_comments_open( bool $open, int $post_id ): bool {
        if ( get_post_type( $post_id ) === self::SLUG ) {
            return true;
        }
        return $open;
    }
}
