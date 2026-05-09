<?php
namespace LinkManager;

defined( 'ABSPATH' ) || exit;

/**
 * Erstellt / entfernt die benötigten Datenbanktabellen.
 *
 * Tabellen:
 *   {prefix}lm_ratings    – eine Bewertung pro Besucher (IP) und Link
 *   {prefix}lm_submissions – von Besuchern vorgeschlagene Links (Moderationsqueue)
 */
class Installer {

    public static function activate(): void {
        self::create_tables();

        // Custom Post Type muss einmal registriert sein, bevor wir flushen.
        ( new Post_Type() )->register();
        flush_rewrite_rules();

        update_option( 'lm_version', LM_VERSION );
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $ratings_table = $wpdb->prefix . 'lm_ratings';
        $sql_ratings   = "CREATE TABLE {$ratings_table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id     BIGINT UNSIGNED NOT NULL,
            voter_ip    VARCHAR(45)     NOT NULL,
            rating      TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=up, 0=down',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_vote (post_id, voter_ip),
            KEY idx_post (post_id)
        ) {$charset};";

        $submissions_table = $wpdb->prefix . 'lm_submissions';
        $sql_submissions   = "CREATE TABLE {$submissions_table} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title        VARCHAR(255)    NOT NULL,
            url          TEXT            NOT NULL,
            description  TEXT,
            category_ids TEXT            COMMENT 'Kommagetrennte Term-IDs',
            submitter_name  VARCHAR(100),
            submitter_email VARCHAR(100),
            status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at  DATETIME,
            reviewed_by  BIGINT UNSIGNED COMMENT 'User-ID des Redakteurs',
            post_id      BIGINT UNSIGNED COMMENT 'Wird nach Freigabe gesetzt',
            PRIMARY KEY (id),
            KEY idx_status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_ratings );
        dbDelta( $sql_submissions );
    }
}
