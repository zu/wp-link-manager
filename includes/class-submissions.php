<?php
namespace LinkManager;

defined( 'ABSPATH' ) || exit;

/**
 * Erlaubt Besuchern, neue Links vorzuschlagen.
 * Vorschläge landen in der Datenbank mit Status „pending"
 * und müssen von einem Redakteur freigegeben werden.
 */
class Submissions {

    public function __construct() {
        add_shortcode( 'link_submit_form', [ $this, 'render_shortcode' ] );

        add_action( 'wp_ajax_lm_submit_link',        [ $this, 'handle_submission' ] );
        add_action( 'wp_ajax_nopriv_lm_submit_link', [ $this, 'handle_submission' ] );
    }

    // -------------------------------------------------------------------------
    // Shortcode [link_submit_form]
    // -------------------------------------------------------------------------

    public function render_shortcode( array $atts ): string {
        ob_start();
        $categories = get_terms( [
            'taxonomy'   => Taxonomy::SLUG,
            'hide_empty' => false,
        ] );
        ?>
        <div class="lm-submit-wrap">
            <div class="lm-notice" id="lm-submit-notice" style="display:none;"></div>
            <form id="lm-submit-form" class="lm-form" novalidate>
                <?php wp_nonce_field( 'lm_submit_link', 'lm_submit_nonce' ); ?>

                <div class="lm-field">
                    <label for="lm_s_title"><?php esc_html_e( 'Titel *', 'link-manager' ); ?></label>
                    <input type="text" id="lm_s_title" name="lm_s_title" required maxlength="255">
                </div>

                <div class="lm-field">
                    <label for="lm_s_url"><?php esc_html_e( 'URL *', 'link-manager' ); ?></label>
                    <input type="url" id="lm_s_url" name="lm_s_url" required placeholder="https://">
                </div>

                <div class="lm-field">
                    <label for="lm_s_desc"><?php esc_html_e( 'Kurzbeschreibung', 'link-manager' ); ?></label>
                    <textarea id="lm_s_desc" name="lm_s_desc" rows="3" maxlength="1000"></textarea>
                </div>

                <?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
                <div class="lm-field">
                    <label><?php esc_html_e( 'Kategorien', 'link-manager' ); ?></label>
                    <div class="lm-checkboxes">
                    <?php foreach ( $categories as $cat ) : ?>
                        <label class="lm-check-label">
                            <input type="checkbox" name="lm_s_categories[]"
                                   value="<?php echo esc_attr( $cat->term_id ); ?>">
                            <?php echo esc_html( $cat->name ); ?>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="lm-field">
                    <label for="lm_s_name"><?php esc_html_e( 'Dein Name', 'link-manager' ); ?></label>
                    <input type="text" id="lm_s_name" name="lm_s_name" maxlength="100">
                </div>

                <div class="lm-field">
                    <label for="lm_s_email"><?php esc_html_e( 'E-Mail (optional, nicht öffentlich)', 'link-manager' ); ?></label>
                    <input type="email" id="lm_s_email" name="lm_s_email" maxlength="100">
                </div>

                <button type="submit" class="lm-btn lm-btn-primary">
                    <?php esc_html_e( 'Link vorschlagen', 'link-manager' ); ?>
                </button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX Handler
    // -------------------------------------------------------------------------

    public function handle_submission(): void {
        check_ajax_referer( 'lm_submit_link', 'lm_submit_nonce' );

        // Spam-Schutz: einfaches Honeypot-Feld
        if ( ! empty( $_POST['lm_hp'] ) ) {
            wp_send_json_error( [ 'message' => 'Spam erkannt.' ] );
        }

        $title = sanitize_text_field( wp_unslash( $_POST['lm_s_title'] ?? '' ) );
        $url   = esc_url_raw( wp_unslash( $_POST['lm_s_url']   ?? '' ) );
        $desc  = sanitize_textarea_field( wp_unslash( $_POST['lm_s_desc']  ?? '' ) );
        $name  = sanitize_text_field( wp_unslash( $_POST['lm_s_name']  ?? '' ) );
        $email = sanitize_email( $_POST['lm_s_email'] ?? '' );
        $cats  = array_map( 'absint', $_POST['lm_s_categories'] ?? [] );

        if ( ! $title || ! $url ) {
            wp_send_json_error( [ 'message' => __( 'Titel und URL sind Pflichtfelder.', 'link-manager' ) ] );
        }

        if ( ! wp_http_validate_url( $url ) ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige URL.', 'link-manager' ) ] );
        }

        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'lm_submissions',
            [
                'title'          => $title,
                'url'            => $url,
                'description'    => $desc,
                'category_ids'   => implode( ',', $cats ),
                'submitter_name' => $name,
                'submitter_email'=> $email,
                'status'         => 'pending',
                'created_at'     => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( ! $inserted ) {
            wp_send_json_error( [ 'message' => __( 'Datenbankfehler. Bitte versuche es später.', 'link-manager' ) ] );
        }

        // Admin-Benachrichtigung
        $this->notify_admin( $title, $url, $name );

        wp_send_json_success( [
            'message' => __( 'Danke! Dein Vorschlag wurde eingereicht und wird geprüft.', 'link-manager' ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Admin-Mail
    // -------------------------------------------------------------------------

    private function notify_admin( string $title, string $url, string $name ): void {
        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );

        $subject = sprintf(
            /* translators: %s = site name */
            __( '[%s] Neuer Link-Vorschlag wartet auf Freigabe', 'link-manager' ),
            $site_name
        );

        $message = sprintf(
            __( "Ein neuer Link wurde vorgeschlagen:\n\nTitel: %s\nURL: %s\nEingereicht von: %s\n\nZur Verwaltung: %s", 'link-manager' ),
            $title,
            $url,
            $name ?: __( 'Unbekannt', 'link-manager' ),
            admin_url( 'admin.php?page=lm-submissions' )
        );

        wp_mail( $admin_email, $subject, $message );
    }

    // -------------------------------------------------------------------------
    // Hilfsmethoden für Admin
    // -------------------------------------------------------------------------

    public function get_pending( int $per_page = 20, int $paged = 1 ): array {
        global $wpdb;
        $table  = $wpdb->prefix . 'lm_submissions';
        $offset = ( $paged - 1 ) * $per_page;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );
    }

    public function count_pending(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lm_submissions WHERE status = 'pending'"
        );
    }

    /**
     * Genehmigt einen Vorschlag und erstellt daraus einen Link-Post.
     */
    public function approve( int $submission_id ): int|\WP_Error {
        global $wpdb;
        $table = $wpdb->prefix . 'lm_submissions';
        $sub   = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $submission_id
        ) );

        if ( ! $sub ) {
            return new \WP_Error( 'not_found', __( 'Vorschlag nicht gefunden.', 'link-manager' ) );
        }

        // Post erstellen
        $post_id = wp_insert_post( [
            'post_title'   => $sub->title,
            'post_content' => $sub->description,
            'post_type'    => Post_Type::SLUG,
            'post_status'  => 'publish',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        update_post_meta( $post_id, '_lm_url', $sub->url );

        // Kategorien zuweisen
        if ( $sub->category_ids ) {
            $term_ids = array_filter( array_map( 'intval', explode( ',', $sub->category_ids ) ) );
            if ( $term_ids ) {
                wp_set_object_terms( $post_id, $term_ids, Taxonomy::SLUG );
            }
        }

        // Screenshot starten
        lm_plugin()->screenshot->generate( $post_id, $sub->url );

        // Submission als genehmigt markieren
        $wpdb->update(
            $table,
            [
                'status'      => 'approved',
                'reviewed_at' => current_time( 'mysql' ),
                'reviewed_by' => get_current_user_id(),
                'post_id'     => $post_id,
            ],
            [ 'id' => $submission_id ],
            [ '%s', '%s', '%d', '%d' ],
            [ '%d' ]
        );

        // Einreicher benachrichtigen (falls E-Mail vorhanden)
        if ( $sub->submitter_email ) {
            wp_mail(
                $sub->submitter_email,
                sprintf( __( '[%s] Dein Link-Vorschlag wurde genehmigt!', 'link-manager' ), get_bloginfo( 'name' ) ),
                sprintf( __( "Hallo %s,\n\nDein vorgeschlagener Link \"%s\" wurde veröffentlicht:\n%s", 'link-manager' ),
                    $sub->submitter_name ?: __( 'Besucher', 'link-manager' ),
                    $sub->title,
                    get_permalink( $post_id )
                )
            );
        }

        return $post_id;
    }

    public function reject( int $submission_id ): bool {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'lm_submissions',
            [
                'status'      => 'rejected',
                'reviewed_at' => current_time( 'mysql' ),
                'reviewed_by' => get_current_user_id(),
            ],
            [ 'id' => $submission_id ],
            [ '%s', '%s', '%d' ],
            [ '%d' ]
        );
    }
}
