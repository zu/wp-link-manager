<?php
namespace LinkManager;

defined( 'ABSPATH' ) || exit;

/**
 * Admin-Bereich: Submissions-Moderationsseite + Admin-Bar-Badge.
 */
class Admin {

    public function __construct() {
        add_action( 'admin_menu',          [ $this, 'add_menu_pages' ] );
        add_action( 'admin_post_lm_approve',[ $this, 'handle_approve' ] );
        add_action( 'admin_post_lm_reject', [ $this, 'handle_reject' ] );
        add_action( 'admin_bar_menu',      [ $this, 'admin_bar_badge' ], 100 );
        add_filter( 'parent_file',         [ $this, 'highlight_menu' ] );
    }

    // -------------------------------------------------------------------------
    // Menü
    // -------------------------------------------------------------------------

    public function add_menu_pages(): void {
        $pending = lm_plugin()->submissions->count_pending();
        $badge   = $pending
            ? ' <span class="awaiting-mod">' . $pending . '</span>'
            : '';

        add_submenu_page(
            'edit.php?post_type=' . Post_Type::SLUG,
            __( 'Link-Vorschläge', 'link-manager' ),
            __( 'Vorschläge', 'link-manager' ) . $badge,
            'edit_posts',
            'lm-submissions',
            [ $this, 'render_submissions_page' ]
        );
    }

    public function highlight_menu( string $parent ): string {
        global $plugin_page;
        if ( $plugin_page === 'lm-submissions' ) {
            $parent = 'edit.php?post_type=' . Post_Type::SLUG;
        }
        return $parent;
    }

    // -------------------------------------------------------------------------
    // Admin-Bar-Badge
    // -------------------------------------------------------------------------

    public function admin_bar_badge( \WP_Admin_Bar $bar ): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $count = lm_plugin()->submissions->count_pending();
        if ( ! $count ) {
            return;
        }

        $bar->add_node( [
            'id'     => 'lm-pending',
            'title'  => sprintf(
                /* translators: %d = Anzahl */
                __( 'Links (%d ausstehend)', 'link-manager' ),
                $count
            ),
            'href'   => admin_url( 'edit.php?post_type=' . Post_Type::SLUG . '&page=lm-submissions' ),
            'parent' => 'top-secondary',
        ] );
    }

    // -------------------------------------------------------------------------
    // Moderationsseite
    // -------------------------------------------------------------------------

    public function render_submissions_page(): void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'link-manager' ) );
        }

        $paged       = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $submissions = lm_plugin()->submissions->get_pending( 20, $paged );
        $total       = lm_plugin()->submissions->count_pending();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Link-Vorschläge', 'link-manager' ); ?>
                <span class="title-count"><?php echo esc_html( $total ); ?></span>
            </h1>

            <?php settings_errors( 'lm_submissions' ); ?>

            <?php if ( empty( $submissions ) ) : ?>
                <p><?php esc_html_e( 'Keine ausstehenden Vorschläge. 🎉', 'link-manager' ); ?></p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Titel',          'link-manager' ); ?></th>
                        <th><?php esc_html_e( 'URL',            'link-manager' ); ?></th>
                        <th><?php esc_html_e( 'Kategorien',     'link-manager' ); ?></th>
                        <th><?php esc_html_e( 'Eingereicht von','link-manager' ); ?></th>
                        <th><?php esc_html_e( 'Datum',          'link-manager' ); ?></th>
                        <th><?php esc_html_e( 'Aktion',         'link-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $submissions as $sub ) :
                    $cats = [];
                    if ( $sub->category_ids ) {
                        foreach ( explode( ',', $sub->category_ids ) as $tid ) {
                            $term = get_term( (int) $tid, Taxonomy::SLUG );
                            if ( $term && ! is_wp_error( $term ) ) {
                                $cats[] = esc_html( $term->name );
                            }
                        }
                    }
                    $approve_url = wp_nonce_url(
                        admin_url( 'admin-post.php?action=lm_approve&id=' . $sub->id ),
                        'lm_approve_' . $sub->id
                    );
                    $reject_url = wp_nonce_url(
                        admin_url( 'admin-post.php?action=lm_reject&id=' . $sub->id ),
                        'lm_reject_' . $sub->id
                    );
                ?>
                <tr>
                    <td><strong><?php echo esc_html( $sub->title ); ?></strong>
                        <?php if ( $sub->description ) : ?>
                            <br><small><?php echo esc_html( wp_trim_words( $sub->description, 15 ) ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?php echo esc_url( $sub->url ); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html( $sub->url ); ?></a>
                    </td>
                    <td><?php echo implode( ', ', $cats ) ?: '—'; ?></td>
                    <td>
                        <?php echo esc_html( $sub->submitter_name  ?: '—' ); ?><br>
                        <small><?php echo esc_html( $sub->submitter_email ?: '' ); ?></small>
                    </td>
                    <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $sub->created_at ) ) ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( $approve_url ); ?>"
                           class="button button-primary"
                           onclick="return confirm('<?php esc_attr_e( 'Link genehmigen und veröffentlichen?', 'link-manager' ); ?>')">
                            <?php esc_html_e( '✓ Genehmigen', 'link-manager' ); ?>
                        </a>
                        &nbsp;
                        <a href="<?php echo esc_url( $reject_url ); ?>"
                           class="button"
                           onclick="return confirm('<?php esc_attr_e( 'Vorschlag ablehnen?', 'link-manager' ); ?>')">
                            <?php esc_html_e( '✗ Ablehnen', 'link-manager' ); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Formular-Handler
    // -------------------------------------------------------------------------

    public function handle_approve(): void {
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'lm_approve_' . $id );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'link-manager' ) );
        }

        $result = lm_plugin()->submissions->approve( $id );

        if ( is_wp_error( $result ) ) {
            add_settings_error( 'lm_submissions', 'approve_error', $result->get_error_message(), 'error' );
        }

        wp_redirect( admin_url( 'edit.php?post_type=' . Post_Type::SLUG . '&page=lm-submissions&msg=approved' ) );
        exit;
    }

    public function handle_reject(): void {
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'lm_reject_' . $id );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'link-manager' ) );
        }

        lm_plugin()->submissions->reject( $id );

        wp_redirect( admin_url( 'edit.php?post_type=' . Post_Type::SLUG . '&page=lm-submissions&msg=rejected' ) );
        exit;
    }
}
