<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

DT_Personal_Migration_Magic_Link::instance();

class DT_Personal_Migration_Magic_Link  {


    public $page_title = 'Personal Migration';
    public $root = "dt_personal_migration_app";
    public $type = 'export';
    public $public_key = '';
    public $type_name = 'Personal Migration Export';
    public $post_type = 'contacts';
    public $type_actions = [
        '' => "Manage",
    ];

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
            $this->public_key = $url_public_key = $parts[2];

            $current_user_public_key = hash('sha256', serialize( get_current_user() ) );
            if ( $url_public_key !== $current_user_public_key ) {
                return;
            }

            add_filter( 'dt_templates_for_urls', [ $this, 'register_url' ], 199, 1 );
            add_filter( 'dt_json_download', [ $this, 'dt_json_download'] );

            // load if valid url
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
        $c = DT_Posts::list_posts( 'contacts', [ 'assigned_to' => [ 'me' ] ] );
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
        $g = DT_Posts::list_posts( 'groups', [ 'assigned_to' => [ 'me' ] ] );
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

        return [
            'contacts' => $contacts,
            'contacts_total' => $contacts_total,
            'contact_comments' => $contact_comments,
            'contact_comments_total' => $contact_comments_total,
            'groups' => $groups,
            'groups_total' => $groups_total,
            'group_comments' => $group_comments,
            'group_comments_total' => $group_comments_total,
            'connections' => [],
        ];
    }


}

