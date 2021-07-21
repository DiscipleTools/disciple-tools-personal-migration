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
                    'message' => 'Successfully transferred and stored data!'
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
            'message' => 'Successfully installed data!'
        ];
    }

    public function install( $post_type ){
        $data = get_transient( 'dt_personal_migration_' . get_current_user_id() );
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
