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

        switch ( $params['action'] ) {
            case 'start_migration':
                if ( ! isset( $params['data'] ) ) {
                    return new WP_Error( __METHOD__, "Missing parameters", [ 'status' => 400 ] );
                }

                $result = $this->start_migration( $params['data'] );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }

                return [
                    'next_action' => 'install_contacts',
                    'message' => 'Successfully transferred and stored data!',
                    'data' => $result,
                ];

            case 'install_contacts':
                $result = $this->install( 'contacts' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed contacts!',
                        'next_action' => 'install_meta_contacts',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'install_contacts',
                    ];
                }

            case 'install_meta_contacts':
                $result = $this->install_meta( 'contacts' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed details for contacts!',
                        'next_action' => 'install_comments_contacts',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'install_meta_contacts',
                    ];
                }


            case 'install_comments_contacts':
                $result = $this->install_comments( 'contacts' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed comments for contacts!',
                        'next_action' => 'install_groups',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'install_comments_contacts',
                    ];
                }




            case 'install_groups':
                $result = $this->install( 'groups' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed groups!',
                        'next_action' => 'install_meta_groups',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'install_groups',
                    ];
                }

            case 'install_meta_groups':
                $result = $this->install_meta( 'groups' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed details for groups!',
                        'next_action' => 'install_comments_groups',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'install_meta_groups',
                    ];
                }

            case 'install_comments_groups':
                $result = $this->install_comments( 'groups' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed comments for groups!',
                        'next_action' => 'install_contacts_to_contacts',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'install_comments_groups',
                    ];
                }

            case 'install_contacts_to_contacts':
                $result = $this->install_post_connections( 'contacts' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed generational connections for contacts!',
                        'next_action' => 'install_groups_to_groups',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'install_contacts_to_contacts',
                    ];
                }

            case 'install_groups_to_groups':
                $result = $this->install_post_connections( 'groups' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed generational connections for groups!',
                        'next_action' => 'install_contacts_to_groups',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'install_groups_to_groups',
                    ];
                }


            case 'install_contacts_to_groups':
                $result = $this->install_cross_connections( 'contacts' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed connections from contacts to groups!',
                        'next_action' => 'install_groups_to_contacts',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'install_contacts_to_groups',
                    ];
                }

            case 'install_groups_to_contacts':
                $result = $this->install_cross_connections( 'groups' );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed connections for groups to contacts!',
                        'next_action' => false,
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'install_groups_to_contacts',
                    ];
                }

            case 'csv_upload':
                $result = $this->csv_upload( $params['data'] );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully saved csv!',
                        'next_action' => 'csv_install',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'csv_upload',
                    ];
                }

            case 'csv_install':
                $result = $this->csv_install();
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed records!',
                        'next_action' => 'csv_meta',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'csv_install',
                    ];
                }

            case 'csv_meta':
                $result = $this->csv_meta();
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed csv record details!',
                        'next_action' => 'csv_location',
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'csv_meta',
                    ];
                }

            case 'csv_location':
                $result = $this->csv_location();
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
                else if ( $result ) {
                    return [
                        'message' => 'Successfully installed csv locations!',
                        'next_action' => false,
                    ];
                }
                else {
                    return [
                        'message' => 'Loop',
                        'next_action' => 'csv_location',
                    ];
                }

            default:
                return new WP_Error( __METHOD__, "Missing action param", [ 'status' => 400 ] );
        }
    }

    public function start_migration( $data ) {
        if ( ! isset( $data['url'] ) ) {
            return new WP_Error( __METHOD__, "Missing url parameters", [ 'status' => 400 ] );
        }

        // Development override
        $arr = array(
            "ssl" =>array(
                "verify_peer" =>false,
                "verify_peer_name" =>false,
            ),
        );

        $json_package = file_get_contents( $data['url'], false, stream_context_create( $arr ) );
        if ( ! $json_package ) {
            sleep( 2 ); // try again, hopefully response is now transient cached and faster.
            $json_package = file_get_contents( $data['url'], false, stream_context_create( $arr ) );
            if ( ! $json_package ) {
                return new WP_Error( __METHOD__, "Failed to retrieve data from JSON url", [ 'status' => 400 ] );
            }
        }

        $json = json_decode( $json_package, true );
        set_transient( 'dt_personal_migration_' . get_current_user_id(), $json, HOUR_IN_SECONDS );

        return [
            'next_action' => 'install_contacts',
            'message' => 'Successfully installed data!',
            'data' => $json,
        ];
    }

    public function install( $post_type ){
        $data = get_transient( 'dt_personal_migration_' . get_current_user_id() );
        dt_write_log( $data );
        if ( ! isset( $data[$post_type]['source_posts'] ) || empty( $data[$post_type]['source_posts'] ) ) {
            return true;
        }
        global $wpdb;
        $loop_limit = 100;

        $already_transferred = $wpdb->get_col( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'pm_transfer_key'" );

        $total = count( $data[$post_type]['source_posts'] );
        $loop = 0;
        foreach ( $data[$post_type]['source_posts'] as $index => $contact ) {
            $pm_transfer_key = hash( 'sha256', $data['source'] . $contact['ID'] );

            if ( ! in_array( $pm_transfer_key, $already_transferred ) ) { // if not already created
                // create contact
                $fields = [
                    "name" => $contact['name'],
                    "assigned_to" => get_current_user_id(),
                    "pm_transfer_key" => $pm_transfer_key
                ];

                // adjust for post type status
                if ( 'contacts' === $post_type) {
                    $fields['overall_status'] = $contact['overall_status']['key'] ?? 'active';
                    $fields['type'] = 'personal';
                }
                else if ( 'groups' === $post_type){
                    $fields['group_status'] = $contact['group_status']['key'] ?? 'active';
                }
                else {
                    $fields['status'] = $contact['status']['key'] ?? 'active';
                }

                // transfer contact data to new post_id
                $new_contact = DT_Posts::create_post( $post_type, $fields, true, false );

                if ( is_wp_error( $new_contact ) && empty( $new_contact ) ) { // if error or already added
                    dt_write_log( $new_contact );
                }
                else {
                    // move source to transferred
                    $data['map'][$contact['ID']] = $new_contact['ID'];
                    $data[$post_type]['transferred_posts'][$new_contact['ID']] = $contact;
                }
            }

            // unset source
            unset( $data[$post_type]['source_posts'][$index] );

            $loop++;
            if ( $loop > $loop_limit ) {
                break;
            }
        }

        set_transient( 'dt_personal_migration_' . get_current_user_id(), $data, HOUR_IN_SECONDS ); // save modified array

        return ! ( $total > $loop_limit );
    }

    public function install_meta( $post_type ){
        $data = get_transient( 'dt_personal_migration_' . get_current_user_id() );

        if ( ! isset( $data[$post_type]['transferred_posts'] ) || empty( $data[$post_type]['transferred_posts'] ) ) {
            return [
                'message' => 'Successfully installed install_meta_'.$post_type.'!',
                'next_action' => 'install_comments_'.$post_type,
            ];
        }
        $loop_limit = 50;

        $post_type_fields = DT_Posts::get_post_field_settings( $post_type );

        $total = count( $data[$post_type]['transferred_posts'] );
        $loop = 0;
        foreach ( $data[$post_type]['transferred_posts']  as $post_id => $contact ) {
            // set variables
            $fields = [];
            if ( ! isset( $data[$post_type]['not_transferred'][$post_id] ) ) {
                $data[$post_type]['not_transferred'][$post_id] = [];
            }
            if ( ! isset( $data['transferred_connections'][$post_id] ) ) {
                $data[$post_type]['transferred_connections'][$post_id] = [];
            }

            // loop fields
            foreach ( $contact as $k => $v ) {

                if ( ! isset( $post_type_fields[$k] ) ) {
                    continue;
                }
                if ( in_array( $k, [ 'name', 'post_title' ] ) ) {
                    continue;
                }
                if ( in_array( $post_type_fields[$k]['type'], [ 'connection' ] ) ) {
                    continue;
                }
                if ( in_array( $post_type_fields[$k]['type'], [ 'user_select' ] ) ) {
                    $data[$post_type]['not_transferred'][$post_id][$k] = $v;
                    continue;
                }

                // build field for update
                if ( in_array( $post_type_fields[$k]['type'], [ 'tags', 'multi_select' ] ) ) {
                    $fields[$k] = [];
                    $fields[$k]['values'] = [];
                    foreach ($v as $item ) {
                        $fields[$k]['values'][] = [ 'value' => $item ];
                    }
                }
                else if ( $post_type_fields[$k]['type'] === 'communication_channel' ) {
                    $fields[$k] = [];
                    $fields[$k]['values'] = [];
                    foreach ($v as $item ) {
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
                        foreach ($v as $item ) {
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
                        foreach ($v as $item ) {
                            $fields[$k]['values'][] = [ 'value' => $item['id'] ];
                        }
                    }
                }
                else if ( in_array( $post_type_fields[$k]['type'], [ 'text', 'number', 'boolean' ] ) ) {
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
                dt_write_log( $updated_contact );
            }
            else {
                $data[$post_type]['connection_posts'][$post_id] = $data[$post_type]['transferred_posts'][$post_id];
                unset( $data[$post_type]['transferred_posts'][$post_id] );
            }

            $loop++;
            if ( $loop > $loop_limit ) {
                break;
            }
        }

        set_transient( 'dt_personal_migration_' . get_current_user_id(), $data, HOUR_IN_SECONDS ); // save modified array

        return ! ( $total > $loop_limit );
    }

    public function install_comments( $post_type ){
        global $wpdb;

        $data = get_transient( 'dt_personal_migration_' . get_current_user_id() );
        if ( ! isset( $data[$post_type]['source_comments'] ) || empty( $data[$post_type]['source_comments'] ) ) {
            return true;
        }

        $map = $data['map'];

        $loop_limit = 100;
        $user = wp_get_current_user();

        $total = count( $data[$post_type]['source_comments'] );
        $loop = 0;
        foreach ( $data[$post_type]['source_comments'] as $index => $comment_set ) {

            foreach ($comment_set as $comment_data) {
                // map new post id
                if ( !isset( $map[$comment_data['comment_post_ID']] )) {
                    continue;
                }
                $comment_post_id = $map[$comment_data['comment_post_ID']];

                // clean PII
                $comment_data['comment_author_IP'] = '';
                $comment_data['comment_author_url'] = '';
                $comment_data['comment_author_email'] = $user->user_email;
                $comment_data['user_id'] = $user->ID;

                $comment_author = $comment_data['comment_author_IP'];
                $comment_author_email = $user->user_email;
                $comment_author_url = '';
                $comment_author_ip = '';
                $comment_date = $comment_data['comment_date'];
                $comment_date_gmt = $comment_data['comment_date_gmt'];
                $comment_content = $comment_data['comment_content'];
                $comment_karma = $comment_data['comment_karma'];
                $comment_approved = $comment_data['comment_approved'];
                $comment_agent = $comment_data['comment_agent'];
                $comment_type = $comment_data['comment_type'];
                $comment_parent = $comment_data['comment_parent'];
                $user_id = $user->ID;

                // insert comment
                $new_comment_result = $wpdb->query($wpdb->prepare(
                    "INSERT INTO $wpdb->comments
                    (
                    comment_post_ID,
                    comment_author,
                    comment_author_email,
                    comment_author_url,
                    comment_author_IP,
                    comment_date,
                    comment_date_gmt,
                    comment_content,
                    comment_karma,
                    comment_approved,
                    comment_agent,
                    comment_type,
                    comment_parent,
                    user_id
                    )
                    VALUES (
                      %s,
                      %s,
                      %s,
                      %s,
                      %s,
                      %s,
                      %s,
                      %s,
                      %s,
                      %s,
                      %s,
                      %s,
                      %s,
                      %s
                    )
                ",
                    $comment_post_id,
                    $comment_author,
                    $comment_author_email,
                    $comment_author_url,
                    $comment_author_ip,
                    $comment_date,
                    $comment_date_gmt,
                    $comment_content,
                    $comment_karma,
                    $comment_approved,
                    $comment_agent,
                    $comment_type,
                    $comment_parent,
                    $user_id
                ));

            }

            // move source to transferred
            $data[$post_type]['transferred_comments'][$index] = $comment_set;

            // unset source
            unset( $data[$post_type]['source_comments'][$index] );

            $loop++;
            if ( $loop > $loop_limit ) {
                break;
            }
        }

        set_transient( 'dt_personal_migration_' . get_current_user_id(), $data, HOUR_IN_SECONDS ); // save modified array

        return ! ( $total > $loop_limit );
    }

    public function install_post_connections( $post_type ) {
        $data = get_transient( 'dt_personal_migration_' . get_current_user_id() );
        if ( ! isset( $data[$post_type]['connection_posts'] ) || empty( $data[$post_type]['connection_posts'] ) ) {
            return true;
        }

        $loop_limit = 50;

        $map = $data['map'];

        $total = count( $data[$post_type]['connection_posts'] );
        $loop = 0;
        foreach ( $data[$post_type]['connection_posts'] as $post_id => $contact ) {

            // set variables
            $fields = [];

            // CONTACTS

            // relation
            // baptized by
            // baptized
            // coaching
            // coached_by
            if ( isset( $contact['relation'] ) && ! empty( $contact['relation'] ) ) {
                $fields['relation'] = [];
                $fields['relation']['values'] = [];
                foreach ($contact['relation'] as $item ) {
                    if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                        $fields['relation']['values'][] = [ 'value' => $map[$item['ID']] ];
                    }
                }
            }
            if ( isset( $contact['coaching'] ) && ! empty( $contact['coaching'] ) ) {
                $fields['coaching'] = [];
                $fields['coaching']['values'] = [];
                foreach ($contact['coaching'] as $item ) {
                    if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                        $fields['coaching']['values'][] = [ 'value' => $map[$item['ID']] ];
                    }
                }
            }
            if ( isset( $contact['coached_by'] ) && ! empty( $contact['coached_by'] ) ) {
                $fields['coached_by'] = [];
                $fields['coached_by']['values'] = [];
                foreach ($contact['coached_by'] as $item ) {
                    if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                        $fields['coached_by']['values'][] = [ 'value' => $map[$item['ID']] ];
                    }
                }
            }
            if ( isset( $contact['baptized_by'] ) && ! empty( $contact['baptized_by'] ) ) {
                $fields['baptized_by'] = [];
                $fields['baptized_by']['values'] = [];
                foreach ($contact['baptized_by'] as $item ) {
                    if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                        $fields['baptized_by']['values'][] = [ 'value' => $map[$item['ID']] ];
                    }
                }
            }
            if ( isset( $contact['baptized'] ) && ! empty( $contact['baptized'] ) ) {
                $fields['baptized'] = [];
                $fields['baptized']['values'] = [];
                foreach ($contact['baptized'] as $item ) {
                    if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                        $fields['baptized']['values'][] = [ 'value' => $map[$item['ID']] ];
                    }
                }
            }

            // GROUPS

            // parent_groups
            // peer_groups
            // child_groups
            if ( isset( $contact['parent_groups'] ) && ! empty( $contact['parent_groups'] ) ) {
                $fields['parent_groups'] = [];
                $fields['parent_groups']['values'] = [];
                foreach ($contact['parent_groups'] as $item ) {
                    if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                        $fields['parent_groups']['values'][] = [ 'value' => $map[$item['ID']] ];
                    }
                }
            }
            if ( isset( $contact['peer_groups'] ) && ! empty( $contact['peer_groups'] ) ) {
                $fields['peer_groups'] = [];
                $fields['peer_groups']['values'] = [];
                foreach ($contact['peer_groups'] as $item ) {
                    if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                        $fields['peer_groups']['values'][] = [ 'value' => $map[$item['ID']] ];
                    }
                }
            }
            if ( isset( $contact['child_groups'] ) && ! empty( $contact['child_groups'] ) ) {
                $fields['child_groups'] = [];
                $fields['child_groups']['values'] = [];
                foreach ($contact['child_groups'] as $item ) {
                    if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                        $fields['child_groups']['values'][] = [ 'value' => $map[$item['ID']] ];
                    }
                }
            }

            // transfer contact data to new post_id
            $updated_contact = DT_Posts::update_post( $post_type, $post_id, $fields, true, false );
            if ( is_wp_error( $updated_contact ) ) {
                dt_write_log( $updated_contact );
            }
            else {

                $data[$post_type]['cross_connection_posts'][$post_id] = $data[$post_type]['connection_posts'][$post_id];
                unset( $data[$post_type]['connection_posts'][$post_id] );
            }

            $loop++;
            if ( $loop > $loop_limit ) {
                break;
            }
        } // end loop 1

        set_transient( 'dt_personal_migration_' . get_current_user_id(), $data, HOUR_IN_SECONDS ); // save modified array

        return ! ( $total > $loop_limit );
    }

    public function install_cross_connections( $post_type ) {

        $data = get_transient( 'dt_personal_migration_' . get_current_user_id() );
        if ( ! isset( $data[$post_type]['cross_connection_posts'] ) || empty( $data[$post_type]['cross_connection_posts'] ) ) {
            return true;
        }

        $loop_limit = 50;

        $map = $data['map'];

        $total = count( $data[$post_type]['cross_connection_posts'] );
        $loop = 0;
        foreach ( $data[$post_type]['cross_connection_posts'] as $post_id => $contact ) {
            $fields = [];

            if ( 'contacts' === $contact['post_type'] ) {
                // CONTACTS
                // groups
                // group_leaders
                if ( isset( $contact['groups'] ) && ! empty( $contact['groups'] ) ) {
                    $fields['groups'] = [];
                    $fields['groups']['values'] = [];
                    foreach ($contact['groups'] as $item ) {
                        if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                            $fields['groups']['values'][] = [ 'value' => $map[$item['ID']] ];
                        }
                    }
                }
                if ( isset( $contact['group_leaders'] ) && ! empty( $contact['group_leaders'] ) ) {
                    $fields['group_leaders'] = [];
                    $fields['group_leaders']['values'] = [];
                    foreach ($contact['group_leaders'] as $item ) {
                        if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                            $fields['group_leaders']['values'][] = [ 'value' => $map[$item['ID']] ];
                        }
                    }
                }
            }

            // set variables
            if ( 'groups' === $contact['post_type'] ) {
                // coaches
                // members
                // leaders
                if ( isset( $contact['coaches'] ) && ! empty( $contact['coaches'] ) ) {
                    $fields['coaches'] = [];
                    $fields['coaches']['values'] = [];
                    foreach ($contact['coaches'] as $item ) {
                        if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                            $fields['coaches']['values'][] = [ 'value' => $map[$item['ID']] ];
                        }
                    }
                }
                if ( isset( $contact['members'] ) && ! empty( $contact['members'] ) ) {
                    $fields['members'] = [];
                    $fields['members']['values'] = [];
                    foreach ($contact['members'] as $item ) {
                        if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                            $fields['members']['values'][] = [ 'value' => $map[$item['ID']] ];
                        }
                    }
                }
                if ( isset( $contact['leaders'] ) && ! empty( $contact['leaders'] ) ) {
                    $fields['leaders'] = [];
                    $fields['leaders']['values'] = [];
                    foreach ($contact['leaders'] as $item ) {
                        if ( isset( $map[$item['ID']] ) && ! empty( $map[$item['ID']] ) ) {
                            $fields['leaders']['values'][] = [ 'value' => $map[$item['ID']] ];
                        }
                    }
                }
            }

            // transfer contact data to new post_id
            $updated_contact = DT_Posts::update_post( $post_type, $post_id, $fields, true, false );
            if ( is_wp_error( $updated_contact ) ) {
                dt_write_log( $updated_contact );
            }
            else {

//                $data[$post_type]['cross_connection_posts'][$post_id] = $data[$post_type]['connection_posts'][$post_id];
                unset( $data[$post_type]['cross_connection_posts'][$post_id] );
            }

            $loop++;
            if ( $loop > $loop_limit ) {
                break;
            }
        } // end loop 1

        set_transient( 'dt_personal_migration_' . get_current_user_id(), $data, HOUR_IN_SECONDS ); // save modified array

        return ! ( $total > $loop_limit );
    }

    public function install_connections( $post_type ){
        $data = get_transient( 'dt_personal_migration_' . get_current_user_id() );

        $connections = $data[$post_type]['transferred_connections'];
        dt_write_log( $connections );




        // clean up finished post type
        unset( $data[$post_type] );
        set_transient( 'dt_personal_migration_' . get_current_user_id(), $data, HOUR_IN_SECONDS );

        // determine next series of actions or finish
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

    public function csv_upload( $csv_data ) {
        if ( ! isset( $csv_data['headers'], $csv_data['data'], $csv_data['post_type'] ) ) {
            return new WP_Error( __METHOD__, 'Missing expected params.', [ 'status' => 400 ] );
        }

        // @todo test headers for including a title field. Fail if no title field.

        // @todo test headers to match post_type fields. Fail if mismatch expected fields.

        $source_posts = [];
        foreach ( $csv_data['data'] as $index => $values ) {
            $source_posts[$index] = [];
            foreach ( $values as $i => $v ) {
                $source_posts[$index][$csv_data['headers'][$i]] = $v;
            }
        }

        $data = [
            'post_type' => $csv_data['post_type'],
            'source_posts' => $source_posts,
            'transferred_posts' => [],
            'location_posts' => [],
            'not_transferred' => []
        ];

        set_transient( 'dt_personal_migration_csv_' . get_current_user_id(), $data, HOUR_IN_SECONDS );

        return true;
    }

    public function csv_install(){
        $data = get_transient( 'dt_personal_migration_csv_' . get_current_user_id() );
        if ( ! isset( $data['source_posts'] ) || empty( $data['source_posts'] ) ) {
            return true;
        }
        $post_type = $data['post_type'];
        $loop_limit = 100;

        $total = count( $data['source_posts'] );
        $loop = 0;
        foreach ( $data['source_posts'] as $index => $record ) {
            $fields = [];

            // Name (required)
            if ( !isset( $record['name'] ) ) {
                continue;
            }
            $fields['name'] = $record['name'];
            unset( $record['name'] );


            /**
             * Assigned To
             * Supports assigned_to int (user_id), or (user_email), assigns to current user
             */
            if ( isset( $record['assigned_to'] ) && is_numeric( $record['assigned_to'] ) ) {
                $fields['assigned_to'] = $record['assigned_to'];

            }
            else if ( isset( $record['assigned_to'] ) && is_email( $record['assigned_to'] ) ) {
                $user = get_user_by( 'email', $record['assigned_to'] );
                if ( $user ) {
                    $fields['assigned_to'] = $user->ID;
                }
            }
            if ( ! isset( $fields['assigned_to'] ) ) {
                $fields['assigned_to'] = get_current_user_id();
            }
            else {
                unset( $record['assigned_to'] );
            }

            /**
             * Status
             */
            if ( isset( $record['overall_status'], $record['group_status'], $record['status'] ) ) {
                if ( 'contacts' === $post_type) {
                    $fields['overall_status'] = $record['overall_status'] ?? 'new';
                    unset( $record['overall_status'] );
                }
                else if ( 'groups' === $post_type){
                    $fields['group_status'] = $record['group_status'] ?? 'active';
                    unset( $record['group_status'] );
                }
                else {
                    $fields['status'] = $record['status'] ?? 'active';
                    unset( $record['status'] );
                }
            }

            /**
             * Set type
             */
            if ( 'contacts' === $post_type ) {
                if ( isset( $record['type'] ) && ! empty( $record['type'] ) ) {
                    $fields['type'] = $record['type'];
                    unset( $record['type'] );
                } else {
                    $fields['type'] = 'personal';
                }
            }
            else if ( 'groups' === $post_type ) {
                if ( isset( $record['group_type'] ) && ! empty( $record['group_type'] ) ) {
                    $fields['group_type'] = $record['group_type'];
                    unset( $record['group_type'] );
                } else {
                    $fields['group_type'] = 'group';
                }
            }

            // transfer contact data to new post_id
            $new_contact = DT_Posts::create_post( $post_type, $fields, true, false );

            if ( is_wp_error( $new_contact ) && empty( $new_contact ) ) { // if error or already added
                dt_write_log( $new_contact );
            }
            else {
                // move source to transferred
                $data['transferred_posts'][$new_contact['ID']] = $record;
            }

            // unset source
            unset( $data['source_posts'][$index] );

            $loop++;
            if ( $loop > $loop_limit ) {
                break;
            }
        }

        set_transient( 'dt_personal_migration_csv_' . get_current_user_id(), $data, HOUR_IN_SECONDS ); // save modified array

        return ! ( $total > $loop_limit );
    }

    public function csv_meta(){
        $data = get_transient( 'dt_personal_migration_csv_' . get_current_user_id() );
        $post_type = $data['post_type'];
        if ( ! isset( $data['transferred_posts'] ) || empty( $data['transferred_posts'] ) ) {
            return [
                'message' => 'Successfully installed csv_meta.!',
                'next_action' => false,
            ];
        }
        $loop_limit = 100;

        $post_type_fields = DT_Posts::get_post_field_settings( $post_type );

        $total = count( $data['transferred_posts'] );
        $loop = 0;
        foreach ( $data['transferred_posts'] as $post_id => $record ) {
            // set variables
            $fields = [];
            if ( ! isset( $data['not_transferred'][$post_id] ) ) {
                $data['not_transferred'][$post_id] = [];
            }
            if ( ! isset( $data['transferred_connections'][$post_id] ) ) {
                $data['transferred_connections'][$post_id] = [];
            }

            // loop fields
            foreach ( $record as $k => $v ) {

                if ( ! isset( $post_type_fields[$k] ) ) {
                    continue;
                }
                if ( in_array( $k, [ 'name', 'post_title' ] ) ) {
                    continue;
                }
                if ( in_array( $k, [ 'location_grid', 'location_grid_meta', 'contact_address', 'lnglat' ] ) ) {
                    $data['location_posts'][$post_id] = $data['transferred_posts'][$post_id];
                    continue;
                }
                if ( in_array( $post_type_fields[$k]['type'], [ 'connection' ] ) ) {
                    continue;
                }
                if ( in_array( $post_type_fields[$k]['type'], [ 'user_select' ] ) ) {
                    $data['not_transferred'][$post_id][$k] = $v;
                    continue;
                }

                // build field for update
                if ( in_array( $post_type_fields[$k]['type'], [ 'tags', 'multi_select' ] ) ) {
                    $values = explode( ';', $v );
                    $fields[$k] = [];
                    $fields[$k]['values'] = [];
                    foreach ($values as $item ) {
                        $fields[$k]['values'][] = [ 'value' => $item ];
                    }
                }
                else if ( $post_type_fields[$k]['type'] === 'communication_channel' ) {
                    $fields[$k] = [];
                    $fields[$k] = [
                        [ 'value' => $v ]
                    ];
                }
                else if ( in_array( $post_type_fields[$k]['type'], [ 'text', 'number', 'boolean' ] ) ) {
                    $fields[$k] = $v;
                }
                else if ( in_array( $post_type_fields[$k]['type'], [ 'key_select' ] ) ) {
                    $fields[$k] = $v;
                }
                else {
                    $data['not_transferred'][$post_id][$k] = $v;
                }
            }

            // transfer contact data to new post_id
            $updated_contact = DT_Posts::update_post( $post_type, $post_id, $fields, true, false );
            if ( is_wp_error( $updated_contact ) ) {
                dt_write_log( $updated_contact );
            }
            else {
                unset( $data['transferred_posts'][$post_id] );
            }

            $loop++;
            if ( $loop > $loop_limit ) {
                break;
            }
        }

        set_transient( 'dt_personal_migration_csv_' . get_current_user_id(), $data, HOUR_IN_SECONDS ); // save modified array

        return ! ( $total > $loop_limit );
    }

    public function csv_location(){
        $data = get_transient( 'dt_personal_migration_csv_' . get_current_user_id() );
        $post_type = $data['post_type'];
        if ( ! isset( $data['location_posts'] ) || empty( $data['location_posts'] ) ) {
            return [
                'message' => 'Successfully added locations from csv!',
                'next_action' => false,
            ];
        }
        $loop_limit = 25;

        $post_type_fields = DT_Posts::get_post_field_settings( $post_type );

        $total = count( $data['location_posts'] );
        $loop = 0;
        foreach ( $data['location_posts'] as $post_id => $record ) {
            // set variables
            $fields = [];

            // loop fields
            foreach ( $record as $k => $v ) {

                if ( ! isset( $post_type_fields[$k] ) ) {
                    continue;
                }
                if ( in_array( $k, [ 'name', 'post_title' ] ) ) {
                    continue;
                }
                if ( in_array( $post_type_fields[$k]['type'], [ 'connection', 'tags', 'multi_select', 'user_select', 'text', 'number', 'boolean', 'key_select' ] ) ) {
                    continue;
                }
                if ( in_array( $post_type_fields[$k]['type'], [ 'communication_channel' ] ) && ! 'contact_address' === $k ) {
                    continue;
                }

                // build field for update
                if ( $post_type_fields[$k]['type'] === 'communication_channel' && 'contact_address' === $k ) {

                    if ( DT_Mapbox_API::get_key() ) {

                        $result = DT_Mapbox_API::forward_lookup( $v );
                        if ( false !== $result ) {

                            $lng = DT_Mapbox_API::parse_raw_result( $result, 'lng', true );
                            $lat = DT_Mapbox_API::parse_raw_result( $result, 'lat', true );

                            $geocoder = new Location_Grid_Geocoder();
                            $grid_row = $geocoder->get_grid_id_by_lnglat( $lng, $lat );

                            if ( isset( $grid_row['grid_id'] ) ) {
                                $grid_id = $grid_row['grid_id'];

                                $level = '';
                                $label = $v;

                                $location_meta_grid = [];
                                Location_Grid_Meta::validate_location_grid_meta( $location_meta_grid );

                                $location_meta_grid['post_id'] = $post_id;
                                $location_meta_grid['post_type'] = $post_type;
                                $location_meta_grid['grid_id'] = $grid_id;
                                $location_meta_grid['lng'] = $lng;
                                $location_meta_grid['lat'] = $lat;
                                $location_meta_grid['level'] = $level;
                                $location_meta_grid['label'] = $label;

                                $potential_error = Location_Grid_Meta::add_location_grid_meta( $post_id, $location_meta_grid );
                            } // success grid id
                        } // valid result
                    } // mapbox key exists
                    else {
                        $fields[$k] = [];
                        $fields[$k] = [
                            [ 'value' => $v ]
                        ];
                    }
                }
                else if ( 'lnglat' === $k ) {
                    // must be lng,lat
                    // single field with the comma delimiter and order is longitude then latitude.
                    $lnglat = explode( ',', $v );
                    $lng = $lnglat[0] ?? false;
                    $lat = $lnglat[1] ?? false;

                    if ( $lng && $lat ) {
                        $geocoder = new Location_Grid_Geocoder();
                        $grid_row = $geocoder->get_grid_id_by_lnglat( $lng, $lat );

                        if ( isset( $grid_row['grid_id'] ) ) {
                            $grid_id = $grid_row['grid_id'];

                            $level = 'address';
                            $label = $v;

                            $location_meta_grid = [];
                            Location_Grid_Meta::validate_location_grid_meta( $location_meta_grid );

                            $location_meta_grid['post_id'] = $post_id;
                            $location_meta_grid['post_type'] = $post_type;
                            $location_meta_grid['grid_id'] = $grid_id;
                            $location_meta_grid['lng'] = $lng;
                            $location_meta_grid['lat'] = $lat;
                            $location_meta_grid['level'] = $level;
                            $location_meta_grid['label'] = $label;

                            $potential_error = Location_Grid_Meta::add_location_grid_meta( $post_id, $location_meta_grid );
                        } // success grid id
                    }
                }
                else if ( 'location_grid' === $k ) {
                    if ( DT_Mapbox_API::get_key() ) {
                        $grid_row = Disciple_Tools_Mapping_Queries::get_by_grid_id( $v );
                        if ( $grid_row ) {
                            $grid_full_name = Disciple_Tools_Mapping_Queries::get_full_name_by_grid_id( $v );

                            $location_meta_grid = [];
                            Location_Grid_Meta::validate_location_grid_meta( $location_meta_grid );

                            $location_meta_grid['post_id'] = $post_id;
                            $location_meta_grid['post_type'] = $post_type;
                            $location_meta_grid['grid_id'] = $grid_row['grid_id'];
                            $location_meta_grid['lng'] = $grid_row['longitude'];
                            $location_meta_grid['lat'] = $grid_row['latitude'];
                            $location_meta_grid['level'] = $grid_row['level_name'];
                            $location_meta_grid['label'] = $grid_full_name;

                            $potential_error = Location_Grid_Meta::add_location_grid_meta( $post_id, $location_meta_grid );
                        }
                    } else {
                        $grid_ids = explode( ';', $v );
                        $fields[$k] = [];
                        $fields[$k]['values'] = [];
                        foreach ($grid_ids as $item ) {
                            $fields[$k]['values'][] = [ 'value' => $item ];
                        }
                    }
                }
                else {
                    $data['not_transferred'][$post_id][$k] = $v;
                }
            }

            // transfer contact data to new post_id
            $updated_contact = DT_Posts::update_post( $post_type, $post_id, $fields, true, false );
            if ( is_wp_error( $updated_contact ) ) {
                dt_write_log( $updated_contact );
            }
            else {
                unset( $data['location_posts'][$post_id] );
            }

            $loop++;
            if ( $loop > $loop_limit ) {
                break;
            }
        }

        set_transient( 'dt_personal_migration_csv_' . get_current_user_id(), $data, HOUR_IN_SECONDS ); // save modified array

        return ! ( $total > $loop_limit );
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
