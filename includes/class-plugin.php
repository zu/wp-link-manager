<?php
namespace LinkManager;

defined( 'ABSPATH' ) || exit;

/**
 * Zentrale Plugin-Klasse.
 * Initialisiert alle Sub-Komponenten in der richtigen Reihenfolge.
 */
final class Plugin {

    private static ?Plugin $instance = null;

    public Post_Type   $post_type;
    public Taxonomy    $taxonomy;
    public Ratings     $ratings;
    public Submissions $submissions;
    public Screenshot  $screenshot;
    public Admin       $admin;
    public Frontend    $frontend;

    private function __construct() {
        Installer::maybe_upgrade();
        $this->load_textdomain();
        $this->init_components();
    }

    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load_textdomain(): void {
        load_plugin_textdomain(
            'link-manager',
            false,
            dirname( plugin_basename( LM_PLUGIN_FILE ) ) . '/languages'
        );
    }

    private function init_components(): void {
        $this->post_type   = new Post_Type();
        $this->taxonomy    = new Taxonomy();
        $this->ratings     = new Ratings();
        $this->submissions = new Submissions();
        $this->screenshot  = new Screenshot();
        $this->frontend    = new Frontend();

        if ( is_admin() ) {
            $this->admin = new Admin();
        }
    }
}
