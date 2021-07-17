<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Personal_Migration_Fields {
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()
    public function __construct() {
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 10, 2 );
    }

    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === 'contacts' || $post_type === 'groups' ){
            $fields["pm_transfer_key"] = [
                'name'        => __( 'Personal Migration Post ID', 'disciple_tools' ),
                'type'        => 'text',
                'default'     => '',
                'hidden'      => true
            ];
        }

        return $fields;
    }
}
DT_Personal_Migration_Fields::instance();
