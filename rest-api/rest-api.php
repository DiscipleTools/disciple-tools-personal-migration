<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Personal_Migration_Endpoints
{
    public $permissions = [ 'access_contacts', 'dt_all_access_contacts', 'view_project_metrics' ];

    //See https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
    public function add_api_routes() {
        $namespace = 'dt_personal_migration/v1';

        register_rest_route(
            $namespace, '/endpoint', [
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => [ $this, 'private_endpoint' ],
                'permission_callback' => function( WP_REST_Request $request ) {
                    return $this->has_permission();
                },
            ]
        );
    }

    public function private_endpoint( WP_REST_Request $request ) {
        $params = $request->get_params();

        $params = dt_recursive_sanitize_array( $params );

        if ( ! isset( $params['action'] ) ) {
            return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
        }

        switch( $params['action'] ) {
            case 'start_migration':
                if ( ! isset( $params['data'] ) ) {
                    return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
                }
                return $this->start_migration( $params['data'] );
            case 'install_contacts':
                return $this->install( 'contacts' );
            case 'install_meta_contacts':
                return $this->install_meta( 'contacts' );
            case 'install_comments_contacts':
                return $this->install_comments( 'contacts' );
            case 'install_connections_contacts':
                return $this->install_connections( 'contacts' );
            case 'install_groups':
                return $this->install( 'groups' );
            case 'install_meta_groups':
                return $this->install_meta( 'groups' );
            case 'install_comments_groups':
                return $this->install_comments( 'groups' );
            case 'install_connections_groups':
                return $this->install_connections( 'groups' );

            default:
                return new WP_Error( __METHOD__, "Missing action param", [ 'status' => 400 ] );
        }
    }

    public function start_migration( $data ) {
        if ( ! isset( $data['url'] ) ) {
            return new WP_Error( __METHOD__, "Missing url parameters", [ 'status' => 400 ] );
        }

        // @todo remove after DEV
        $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );

        $json_package = file_get_contents( $data['url'], false, stream_context_create($arrContextOptions));
        if ( ! $json_package ) {
            sleep(2 ); // try again, hopefully response is now transient cached and faster.
            $json_package = file_get_contents( $data['url'], false, stream_context_create($arrContextOptions));
            if ( ! $json_package ) {
                return new WP_Error( __METHOD__, "Failed to retrieve data from JSON url", [ 'status' => 400 ] );
            }
        }

        $json = json_decode( $json_package, true );
        set_transient('dt_personal_migration_' . get_current_user_id(), $json, HOUR_IN_SECONDS );

        return [
            'next_action' => 'install_contacts',
            'message' => 'Successfully installed data!'
        ];
    }

    public function install( $post_type ){
        $data = get_transient('dt_personal_migration_' . get_current_user_id() );
        if ( ! isset( $data[$post_type]['source_posts'] ) || empty( $data[$post_type]['source_posts'] ) ) {
            return [
                'message' => 'Finished install '. $post_type,
                'next_action' => 'install_comments_'.$post_type,
            ];
        }
        $loop_limit = 100;

        $total = count( $data[$post_type]['source_posts'] );
        $loop = 0;
        foreach( $data[$post_type]['source_posts'] as $index => $contact ) {


            // create contact
            $fields = [
                "name" => $contact['name'],
                "assigned_to" => get_current_user_id(),
                "pm_post_id" => $contact['ID']
            ];

            if ( 'contacts'===$post_type) {
                $fields['overall_status'] = $contact['overall_status']['key'] ?? 'active';
            }
            else if ( 'groups'===$post_type){
                $fields['group_status'] = $contact['group_status']['key'] ?? 'active';
            }
            else {
                $fields['status'] = $contact['status']['key'] ?? 'active';
            }

            // transfer contact data to new post_id
            $new_contact = DT_Posts::create_post( $post_type, $fields, true, false );
            if ( is_wp_error( $new_contact ) ) {
                dt_write_log($new_contact);
            } else {
                // move source to transferred
                $data[$post_type]['transferred_posts'][$new_contact['ID']] = $contact;

                // unset source
                unset( $data[$post_type]['source_posts'][$index] );
            }

            $loop++;
            if ( $loop > $loop_limit ) {
                break;
            }
        }

        set_transient('dt_personal_migration_' . get_current_user_id(), $data, HOUR_IN_SECONDS ); // save modified array

        if ( $total > $loop_limit ) {
            return [
                'message' => 'Loop',
                'next_action' => 'install_'.$post_type,
            ];
        } else {
            return [
                'message' => 'Successfully installed install '.$post_type.'!',
                'next_action' => 'install_meta_'.$post_type,
            ];
        }
    }

    public function install_meta( $post_type ){
        $data = get_transient('dt_personal_migration_' . get_current_user_id() );

        if ( ! isset( $data[$post_type]['transferred_posts'] ) || empty( $data[$post_type]['transferred_posts'] ) ) {
            return [
                'message' => 'Successfully installed install_meta_'.$post_type.'!',
                'next_action' => 'install_comments_'.$post_type,
            ];
        }
        $loop_limit = 50;

        $post_type_fields = DT_Posts::get_post_field_settings( $post_type );

        $total = count( $data[$post_type]['transferred_posts']  );
        $loop = 0;
        foreach( $data[$post_type]['transferred_posts']  as $post_id => $contact ) {
            $fields = [];
            if ( ! isset( $data[$post_type]['not_transferred'][$post_id] ) ) {
                $data[$post_type]['not_transferred'][$post_id] = [];
            }
            if ( ! isset( $data['transferred_connections'][$post_id] ) ) {
                $data[$post_type]['transferred_connections'][$post_id] = [];
            }

            foreach( $contact as $k => $v ) {

                if ( ! isset( $post_type_fields[$k] ) ) {
                    continue;
                }
                if ( in_array( $k, [ 'name', 'post_title' ] ) ) {
                    continue;
                }
                if ( in_array( $post_type_fields[$k]['type'], ['connection'] ) ) {
                    $data[$post_type]['transferred_connections'][$post_id][$k] = $v;
                    continue;
                }
                if ( in_array( $post_type_fields[$k]['type'], ['user_select'] ) ) {
                    $data[$post_type]['not_transferred'][$post_id][$k] = $v;
                    continue;
                }

                // build field for update
                if ( in_array( $post_type_fields[$k]['type'], [ 'tags', 'multi_select' ] ) ) {
                    $fields[$k] = [];
                    $fields[$k]['values'] = [];
                    foreach($v as $item ) {
                        $fields[$k]['values'][] = [ 'value' => $item ];
                    }
                }
                else if ( $post_type_fields[$k]['type'] === 'communication_channel' ) {
                    $fields[$k] = [];
                    $fields[$k]['values'] = [];
                    foreach($v as $item ) {
                        $fields[$k]['values'][] = [ 'value' => $item['value'] ];
                    }
                }
                else if ( in_array( $k, [ 'location_grid', 'location_grid_meta' ] ) ) {
                    if ( $k === 'location_grid' && in_array( 'location_grid_meta', $contact ) ) {
                        continue; // skip if location_grid_meta is present
                    }
                    if ( $k === 'location_grid_meta' ) {
                        $fields[$k] = [];
                        $fields[$k]['values'] = [];
                        foreach($v as $item ) {
                            if ( ! isset( $item['grid_id'], $item['lng'], $item['lat'] ) ) {
                                continue;
                            }

                            $location_meta_grid = [];
                            Location_Grid_Meta::validate_location_grid_meta( $location_meta_grid );

                            $location_meta_grid['post_id'] = $post_id;
                            $location_meta_grid['post_type'] = $post_type;
                            $location_meta_grid['grid_id'] = $item['grid_id'];
                            $location_meta_grid['lng'] = $item['lng'];
                            $location_meta_grid['lat'] = $item['lat'];
                            $location_meta_grid['level'] = $item['level'];
                            $location_meta_grid['label'] = $item['label'];

                            $potential_error = Location_Grid_Meta::add_location_grid_meta( $post_id, $location_meta_grid );
                        }
                    }
                    else if ( $k === 'location_grid' ) { // if only location_grid
                        $fields[$k] = [];
                        $fields[$k]['values'] = [];
                        foreach($v as $item ) {
                            $fields[$k]['values'][] = [ 'value' => $item['id'] ];
                        }
                    }
                }
                else if ( in_array( $post_type_fields[$k]['type'], [  'text', 'number', 'boolean' ] ) ) {
                    $fields[$k] = $v;
                }
                else if ( in_array( $post_type_fields[$k]['type'], [ 'key_select' ] ) ) {
                    $fields[$k] = $v['key'];
                }
                else {
                    $data[$post_type]['not_transferred'][$post_id][$k] = $v;
                }
            }

            // transfer contact data to new post_id
            $updated_contact = DT_Posts::update_post( $post_type, $post_id, $fields, true, false );
            if ( is_wp_error( $updated_contact ) ) {
                dt_write_log($updated_contact);
            }
            else {
                unset( $data[$post_type]['transferred_posts'][$post_id] );
            }

            $loop++;
            if ( $loop > $loop_limit ) {
                break;
            }
        }

        set_transient('dt_personal_migration_' . get_current_user_id(), $data, HOUR_IN_SECONDS ); // save modified array

        if ( $total > $loop_limit ) {
            return [
                'message' => 'Loop',
                'next_action' => 'install_meta_'.$post_type,
            ];
        } else {
            return [
                'message' => 'Successfully installed install_meta_'.$post_type.'!',
                'next_action' => 'install_comments_'.$post_type,
            ];
        }

    }

    public function install_comments( $post_type ){
        $data = get_transient('dt_personal_migration_' . get_current_user_id() );

        set_transient('dt_personal_migration_' . get_current_user_id(), $data, HOUR_IN_SECONDS );

        return [
            'message' => 'Successfully installed install_comments_'.$post_type.'!',
            'next_action' => 'install_connections_'.$post_type,
        ];
    }

    public function install_connections( $post_type ){
        $data = get_transient('dt_personal_migration_' . get_current_user_id() );

        unset($data[$post_type]);

        set_transient('dt_personal_migration_' . get_current_user_id(), $data, HOUR_IN_SECONDS );

        if ( isset( $data['contacts'] ) ) {
            return [
                'message' => 'Successfully installed install_connections_'.$post_type.'!',
                'next_action' => 'install_contacts',
            ];
        }
        else if ( isset( $data['groups'] ) ) {
            return [
                'message' => 'Successfully installed install_connections_'.$post_type.'!',
                'next_action' => 'install_groups',
            ];
        }
        else {
            return [
                'message' => 'Successfully installed install_connections_'.$post_type.'!',
                'next_action' => false,
            ];
        }
    }

    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === 'contacts' || $post_type === 'groups' ){
            $fields["pm_post_id"] = [
                'name'        => __( 'Personal Migration Post ID', 'disciple_tools' ),
                'type'        => 'text',
                'default'     => '',
                'hidden'      => true
            ];
        }

        return $fields;
    }

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 10, 2 );
    }
    public function has_permission(){
        $pass = false;
        foreach ( $this->permissions as $permission ){
            if ( current_user_can( $permission ) ){
                $pass = true;
            }
        }
        return $pass;
    }
}
DT_Personal_Migration_Endpoints::instance();
