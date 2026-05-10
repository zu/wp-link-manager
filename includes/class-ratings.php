<?php
namespace LinkManager;

defined( 'ABSPATH' ) || exit;

/**
 * Bewertungssystem: Besucher können einen Link hoch- oder runtervoten.
 * Eine Stimme pro IP und Link. Gespeichert in {prefix}lm_ratings.
 */
class Ratings {

    public function __construct() {
        add_action( 'wp_ajax_lm_vote',        [ $this, 'handle_vote' ] );
        add_action( 'wp_ajax_nopriv_lm_vote', [ $this, 'handle_vote' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    public function enqueue_scripts(): void {
        if ( ! is_singular( Post_Type::SLUG ) && ! is_post_type_archive( Post_Type::SLUG ) ) {
            return;
        }

        wp_enqueue_script(
            'lm-public',
            LM_PLUGIN_URL . 'public/js/lm-public.js',
            [ 'jquery' ],
            LM_VERSION,
            true
        );

        wp_localize_script( 'lm-public', 'LM', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'lm_vote' ),
            'i18n'     => [
                'already_voted' => __( 'Du hast bereits abgestimmt.',    'link-manager' ),
                'vote_error'    => __( 'Fehler beim Speichern der Stimme.', 'link-manager' ),
            ],
        ] );

        wp_enqueue_style(
            'lm-public',
            LM_PLUGIN_URL . 'public/css/lm-public.css',
            [],
            LM_VERSION
        );
    }

    // -------------------------------------------------------------------------
    // AJAX Handler
    // -------------------------------------------------------------------------

    public function handle_vote(): void {
        check_ajax_referer( 'lm_vote', 'nonce' );

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $value   = absint( $_POST['value']   ?? 1 );   // 1 = up, 0 = down

        if ( ! $post_id || get_post_type( $post_id ) !== Post_Type::SLUG ) {
            wp_send_json_error( [ 'message' => __( 'Ungültiger Link.', 'link-manager' ) ] );
        }

        $voter_ip = $this->get_voter_ip();
        $result   = $this->save_vote( $post_id, $voter_ip, $value );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $counts = $this->get_counts( $post_id );
        wp_send_json_success( [
            'up'    => $counts->up,
            'down'  => $counts->down,
            'score' => $counts->up - $counts->down,
        ] );
    }

    // -------------------------------------------------------------------------
    // DB-Operationen
    // -------------------------------------------------------------------------

    private function save_vote( int $post_id, string $ip, int $rating ): bool|\WP_Error {
        global $wpdb;

        $table     = $wpdb->prefix . 'lm_ratings';
        $existing  = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id = %d AND voter_ip = %s",
            $post_id, $ip
        ) );

        if ( $existing ) {
            return new \WP_Error( 'already_voted', __( 'Du hast bereits abgestimmt.', 'link-manager' ) );
        }

        $inserted = $wpdb->insert( $table, [
            'post_id'    => $post_id,
            'voter_ip'   => $ip,
            'rating'     => $rating,
            'created_at' => current_time( 'mysql' ),
        ], [ '%d', '%s', '%d', '%s' ] );

        if ( ! $inserted ) {
            return new \WP_Error( 'db_error', __( 'Datenbankfehler.', 'link-manager' ) );
        }

        return true;
    }

    public function get_counts( int $post_id ): object {
        global $wpdb;
        $table = $wpdb->prefix . 'lm_ratings';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                SUM(rating = 1) AS up,
                SUM(rating = 0) AS down
             FROM {$table}
             WHERE post_id = %d",
            $post_id
        ) );

        return (object) [
            'up'   => (int) ( $row->up   ?? 0 ),
            'down' => (int) ( $row->down ?? 0 ),
        ];
    }

    public function has_voted( int $post_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'lm_ratings';

        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id = %d AND voter_ip = %s",
            $post_id,
            $this->get_voter_ip()
        ) );
    }

    // -------------------------------------------------------------------------
    // Hilfsfunktionen
    // -------------------------------------------------------------------------

    private function get_voter_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
    }

    /**
     * Gibt HTML-Widget für eine Link-Einzelseite zurück.
     */
    public function render_widget( int $post_id ): string {
        $counts     = $this->get_counts( $post_id );
        $has_voted  = $this->has_voted( $post_id );
        $score      = $counts->up - $counts->down;
        $disabled   = $has_voted ? 'disabled' : '';

        ob_start(); ?>
        <div class="lm-ratings" data-post-id="<?php echo esc_attr( $post_id ); ?>">
            <button class="lm-vote lm-vote-up <?php echo $disabled; ?>"
                    data-value="1" <?php echo $disabled; ?>>
                👍 <span class="lm-count-up"><?php echo esc_html( $counts->up ); ?></span>
            </button>
            <span class="lm-score" title="<?php esc_attr_e( 'Score', 'link-manager' ); ?>">
                <?php echo esc_html( $score ); ?>
            </span>
            <button class="lm-vote lm-vote-down <?php echo $disabled; ?>"
                    data-value="0" <?php echo $disabled; ?>>
                👎 <span class="lm-count-down"><?php echo esc_html( $counts->down ); ?></span>
            </button>
            <?php if ( $has_voted ) : ?>
                <span class="lm-voted-note"><?php esc_html_e( '(Du hast abgestimmt)', 'link-manager' ); ?></span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
