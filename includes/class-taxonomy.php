<?php
namespace LinkManager;

defined( 'ABSPATH' ) || exit;

/**
 * Registriert die Taxonomie „link_category" für den CPT „link".
 */
class Taxonomy {

    const SLUG = 'link_category';

    public function __construct() {
        add_action( 'init', [ $this, 'register' ] );
    }

    public function register(): void {
        $labels = [
            'name'              => __( 'Link-Kategorien',           'link-manager' ),
            'singular_name'     => __( 'Link-Kategorie',            'link-manager' ),
            'search_items'      => __( 'Kategorien suchen',         'link-manager' ),
            'all_items'         => __( 'Alle Kategorien',           'link-manager' ),
            'parent_item'       => __( 'Übergeordnete Kategorie',   'link-manager' ),
            'parent_item_colon' => __( 'Übergeordnete Kategorie:',  'link-manager' ),
            'edit_item'         => __( 'Kategorie bearbeiten',      'link-manager' ),
            'update_item'       => __( 'Kategorie aktualisieren',   'link-manager' ),
            'add_new_item'      => __( 'Neue Kategorie hinzufügen', 'link-manager' ),
            'new_item_name'     => __( 'Name der neuen Kategorie',  'link-manager' ),
            'menu_name'         => __( 'Kategorien',                'link-manager' ),
        ];

        register_taxonomy( self::SLUG, Post_Type::SLUG, [
            'hierarchical'      => true,   // wie Kategorien, nicht Tags
            'labels'            => $labels,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'link-kategorie' ],
            'capabilities'      => [
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ],
        ] );
    }
}
