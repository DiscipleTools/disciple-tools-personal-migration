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

        $parts = explode( '/', $url );
        if ( isset( $parts[2] ) && ! empty( $parts[2] ) ) {

            // test for enabled key
            $this->public_key = $url_public_key = $parts[2];
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT *
                        FROM $wpdb->usermeta
                        WHERE meta_key = %s
                          AND meta_value = %s;",
            $wpdb->prefix . 'personal_migration_app_export', $this->public_key ), ARRAY_A );
            if ( empty( $row ) ) {
                return;
            }
            $this->user_id = $row['user_id'];

            add_filter( 'dt_templates_for_urls', [ $this, 'register_url' ], 199, 1 );
            add_filter( 'dt_allow_non_login_access', function (){
                return true;
            }, 100, 1 );
            add_action( 'dt_json_access', [ $this, 'dt_json_access' ] );
            add_action( 'dt_json_content', [ $this, 'dt_json_content' ] ); // body for no post key
        }

    }


    public function dt_json_access( $access ) {
        return true;
    }
    public function dt_json_download() {
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
        $contact_limit = 2000;
        $groups_limit = 1000;

        $data = get_transient( __METHOD__ . $this->user_id );
        if ( $data ) {
            return $data;
        }

        global $wpdb;
        $list_contacts = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.post_id, p.post_type
                    FROM $wpdb->dt_share s
                    LEFT JOIN $wpdb->posts p ON s.post_id = p.ID
                    LEFT JOIN $wpdb->postmeta pm ON pm.post_id = s.post_id AND pm.meta_key = 'type'
                    WHERE s.user_id = %s
                        AND p.post_type = 'contacts'
                        AND pm.meta_value != 'access'
                    ORDER BY s.post_id DESC
                    LIMIT %d
                    ",
        $this->user_id, $contact_limit ), ARRAY_A );
        $list_groups = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.post_id, p.post_type
                    FROM $wpdb->dt_share s
                    LEFT JOIN $wpdb->posts p ON s.post_id = p.ID
                    WHERE s.user_id = %s
                        AND p.post_type = 'groups'
                    ORDER BY s.post_id DESC
                    LIMIT %d",
        $this->user_id, $groups_limit ), ARRAY_A );

        $list = array_merge( $list_contacts, $list_groups );
        $data = [];
        $data['contacts'] = [
            'source_posts' => [],
            'source_posts_total' => 0,
            'source_comments' => [],
            'source_fields' => DT_Posts::get_post_field_settings( 'contacts' ),
            'transferred_posts' => [],
            'transferred_comments' => [],
            'connection_posts' => [],
            'cross_connection_posts' => [],
            'not_transferred' => [],
        ];
        $data['groups'] = [
            'source_posts' => [],
            'source_posts_total' => 0,
            'source_comments' => [],
            'source_fields' => DT_Posts::get_post_field_settings( 'groups' ),
            'transferred_posts' => [],
            'transferred_comments' => [],
            'connection_posts' => [],
            'cross_connection_posts' => [],
            'not_transferred' => [],
        ];
        $data['map'] = [];
        $data['source'] = site_url();
        foreach ( $list as $row ) {
            $data[$row['post_type']]['source_posts'][$row['post_id']] = DT_Posts::get_post( $row['post_type'], $row['post_id'], true, false );
            $data[$row['post_type']]['source_comments'][$row['post_id']] = get_comments( ['post_id' => (int) $row['post_id'], 'post_type' => $row['post_type'] ] );
            $data[$row['post_type']]['source_posts_total']++;
        }

        set_transient( __METHOD__ . $this->user_id, $data, MINUTE_IN_SECONDS * 5 );

        return $data;
    }
}

