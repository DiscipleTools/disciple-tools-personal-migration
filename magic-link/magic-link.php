<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

DT_Personal_Migration_Magic_Link::instance();

class DT_Personal_Migration_Magic_Link  {


    public $page_title = 'Personal Migration';
    public $root = "personal_migration_app";
    public $type = 'export';
    public $public_key = '';
    public $type_name = 'Personal Migration Export';
    public $post_type = 'contacts';
    public $type_actions = [
        '' => "Manage",
    ];
    public $user_id = 0;

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        $url = dt_get_url_path();
        if ( strpos( $url, $this->root . '/' . $this->type ) === false ) {
            return;
        }

        $parts = explode( '/', $url);
        if ( isset( $parts[2] ) && ! empty( $parts[2] ) ) {

            // test for enabled key
            $this->public_key = $url_public_key = $parts[2];
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT *
                        FROM $wpdb->usermeta
                        WHERE meta_key = %s
                          AND meta_value = %s;"
                , $wpdb->prefix . 'personal_migration_app_export', $this->public_key ), ARRAY_A );
            if ( empty( $row ) ) {
                return;
            }
            $this->user_id = $row['user_id'];

            add_filter( 'dt_templates_for_urls', [ $this, 'register_url' ], 199, 1 );
            add_filter( 'dt_allow_non_login_access', function (){
                return true;
            }, 100, 1 );
            add_action( 'dt_json_access', [ $this, 'dt_json_access'] );
            add_action( 'dt_json_content', [ $this, 'dt_json_content' ] ); // body for no post key
        }

    }


    public function dt_json_access( $access ) {
        return true;
    }
    public function dt_json_download( ) {
        return $this->root . '_' . $this->type . '_' . time();
    }

    public function dt_magic_url_register_types( array $types ) : array {
        if ( ! isset( $types[$this->root] ) ) {
            $types[$this->root] = [];
        }
        $types[$this->root][$this->type] = [
            'name' => $this->type_name,
            'root' => $this->root,
            'type' => $this->type,
            'meta_key' => $this->root . '_' . $this->type . '_magic_key',
            'actions' => $this->type_actions,
            'post_type' => $this->post_type,
        ];
        return $types;
    }


    public function register_url( $template_for_url ){
        if ( empty( $parts['action'] ) ){ // only root public key requested
            $template_for_url[ $this->root . '/'. $this->type . '/' . $this->public_key ] = 'template-blank-json.php';
            return $template_for_url;
        }

        return $template_for_url;
    }

    public function dt_json_content(){

//        $data = get_transient( __METHOD__ . $this->user_id );
//        if ( $data ) {
//            return $data;
//        }

        $c = DT_Posts::list_posts(
            'contacts', [
            'assigned_to' => [ $this->user_id ],
//            'share_with' => $this->user_id,
//            'type' => [ 'personal' ]
        ], false );
        if ( is_wp_error( $c ) ) {
            return [
                'fail' => $c
            ];
        }
        $contacts_total = $c['total'];
        $contacts = [];
        foreach( $c['posts'] as $value ){
            $contacts[$value['ID']] = $value;
        }
        $contact_comments = [];
        $contact_comments_total = 0;
        foreach( $contacts as $contact ) {
            $contact_comments[$contact['ID']] = get_comments( ['post_id' => (int) $contact['ID'], 'post_type' => 'contacts' ] );;
            if ( ! empty( $contact_comments[$contact['ID']] ) ) {
                $contact_comments_total = $contact_comments_total + count( $contact_comments[$contact['ID']] );
            }
        }
        $g = DT_Posts::list_posts( 'groups', [ 'assigned_to' => [ $this->user_id ] ], false );
        $groups_total = $g['total'];
        $groups = [];
        foreach( $g['posts'] as $value ){
            $groups[$value['ID']] = $value;
        }
        $group_comments = [];
        $group_comments_total = 0;
        foreach( $groups as $group ) {
            $group_comments[$group['ID']] = get_comments( ['post_id' => (int) $group['ID'] , 'post_type' => 'groups' ]);
            if ( ! empty( $group_comments[$group['ID']] ) ) {
                $group_comments_total = $group_comments_total + count( $group_comments[$group['ID']] );
            }
        }

        $contact_fields = DT_Posts::get_post_field_settings( 'contacts' );
        $group_fields = DT_Posts::get_post_field_settings( 'groups' );

        $data = [
            'contacts' => [
                'source_posts' => $contacts,
                'source_total' => (int) $contacts_total,
                'source_comments' => $contact_comments,
                'source_comments_total' => $contact_comments_total,
                'source_fields' => $contact_fields,
                'transferred_posts' => [],
                'transferred_comments' => [],
                'transferred_connections' => [],
                'not_transferred' => [],
                'map' => [],
            ],
            'groups' => [
                'source_posts' => $groups,
                'source_total' => (int) $groups_total,
                'source_comments' => $group_comments,
                'source_comments_total' => $group_comments_total,
                'source_fields' => $group_fields,
                'transferred_posts' => [],
                'transferred_comments' => [],
                'transferred_connections' => [],
                'not_transferred' => [],
                'map' => [],
            ],
        ];

//        set_transient( __METHOD__ . $this->user_id, $data, MINUTE_IN_SECONDS * 5 );

        return $data;
    }


}

