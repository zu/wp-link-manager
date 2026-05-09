<?php
namespace LinkManager;

defined( 'ABSPATH' ) || exit;

/**
 * Erstellt automatisch Screenshots via WordPress mShots-Service
 * und speichert das Bild als WordPress-Attachment (= Beitragsbild).
 *
 * mShots ist kostenlos und benötigt keinen API-Key.
 * Endpoint: https://s.wordpress.com/mshots/v1/{encoded_url}?w=1200&h=675
 *
 * Das Bild wird heruntergeladen und in der WordPress-Mediathek gespeichert,
 * damit es als Beitragsbild verwendet – und durch den Redakteur ersetzt –
 * werden kann.
 */
class Screenshot {

    const MSHOTS_BASE = 'https://s.wordpress.com/mshots/v1/';
    const WIDTH       = 1200;
    const HEIGHT      = 675;

    public function __construct() {
        // Kein Hook nötig – wird direkt aufgerufen
    }

    /**
     * Generiert einen Screenshot der URL und setzt ihn als Beitragsbild.
     *
     * @param int    $post_id WordPress-Post-ID
     * @param string $url     Ziel-URL
     * @return bool  true bei Erfolg
     */
    public function generate( int $post_id, string $url ): bool {
        if ( empty( $url ) ) {
            return false;
        }

        $screenshot_url = $this->build_mshots_url( $url );

        // mShots gibt beim ersten Aufruf oft ein Platzhalter-Bild zurück.
        // Wir speichern trotzdem die mShots-URL im Meta für die Vorschau
        // und laden das Bild in die Mediathek.
        update_post_meta( $post_id, '_lm_screenshot_url', $screenshot_url );

        // Bild herunterladen und als Attachment anlegen (asynchron via WP-Cron,
        // weil mShots beim ersten Aufruf noch rendert)
        if ( ! wp_next_scheduled( 'lm_import_screenshot', [ $post_id ] ) ) {
            wp_schedule_single_event(
                time() + 30,   // 30 Sekunden warten, damit mShots rendern kann
                'lm_import_screenshot',
                [ $post_id ]
            );
        }

        add_action( 'lm_import_screenshot', [ $this, 'import_to_media_library' ] );

        return true;
    }

    /**
     * Wird via WP-Cron aufgerufen: lädt das Screenshot-Bild herunter
     * und setzt es als Beitragsbild.
     */
    public function import_to_media_library( int $post_id ): void {
        $url            = get_post_meta( $post_id, '_lm_url',            true );
        $screenshot_url = get_post_meta( $post_id, '_lm_screenshot_url', true );

        if ( ! $url || ! $screenshot_url ) {
            return;
        }

        // Beitragsbild bereits manuell gesetzt? Dann nicht überschreiben.
        if ( has_post_thumbnail( $post_id ) ) {
            $thumb_id       = get_post_thumbnail_id( $post_id );
            $thumb_meta_url = get_post_meta( $thumb_id, '_lm_auto_screenshot', true );
            if ( ! $thumb_meta_url ) {
                // Manuell gesetzt → nicht anfassen
                return;
            }
        }

        // Bild herunterladen
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $screenshot_url, 30 );
        if ( is_wp_error( $tmp ) ) {
            error_log( '[LinkManager] Screenshot download failed: ' . $tmp->get_error_message() );
            return;
        }

        $file_array = [
            'name'     => 'screenshot-' . $post_id . '-' . time() . '.jpg',
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload( $file_array, $post_id );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            error_log( '[LinkManager] Screenshot sideload failed: ' . $attachment_id->get_error_message() );
            return;
        }

        // Markieren als automatisch erstellt (damit wir es später ersetzen können)
        update_post_meta( $attachment_id, '_lm_auto_screenshot', $screenshot_url );

        // Als Beitragsbild setzen (nur wenn noch kein manuelles gesetzt)
        if ( ! has_post_thumbnail( $post_id ) ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }
    }

    /**
     * Baut die mShots-URL auf.
     */
    public function build_mshots_url( string $url ): string {
        return self::MSHOTS_BASE
            . rawurlencode( $url )
            . '?w=' . self::WIDTH
            . '&h=' . self::HEIGHT;
    }

    /**
     * Gibt die aktuelle Screenshot-URL zurück (mShots oder Attachment-URL).
     */
    public function get_screenshot_url( int $post_id ): string {
        if ( has_post_thumbnail( $post_id ) ) {
            return get_the_post_thumbnail_url( $post_id, 'large' );
        }
        return (string) get_post_meta( $post_id, '_lm_screenshot_url', true );
    }
}
