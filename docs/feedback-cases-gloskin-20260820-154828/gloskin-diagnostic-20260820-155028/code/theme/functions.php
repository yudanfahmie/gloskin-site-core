<?php
/**
 * Gloskin Id Child - Functions.
 *
 * @package gloskin-id-child
 */

defined( 'ABSPATH' ) || exit;

function gloskin_id_child_enqueue_styles() {
    wp_enqueue_style(
        'gloskin-id-parent-style',
        get_parent_theme_file_uri( 'style.css' ),
        array(),
        wp_get_theme()->parent()->get( 'Version' )
    );
    wp_enqueue_style(
        'gloskin-id-child-style',
        get_stylesheet_uri(),
        array( 'gloskin-id-parent-style' ),
        wp_get_theme()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', 'gloskin_id_child_enqueue_styles' );

// Add your child-theme customisations below.
