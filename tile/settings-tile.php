<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Personal_Migration_Settings_Tile
{
    private static $_instance = null;
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        if ( 'settings' === dt_get_url_path() ) {
            add_action( 'dt_profile_settings_page_menu', [ $this, 'dt_profile_settings_page_menu' ], 100, 4 );
            add_action( 'dt_profile_settings_page_sections', [ $this, 'dt_profile_settings_page_sections' ], 100, 4 );
            add_action( 'dt_modal_help_text', [ $this, 'dt_modal_help_text' ], 100 );
            add_filter( 'dt_settings_apps_list', [ $this, 'dt_settings_apps_list' ] , 50, 1 );
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

    public function dt_settings_apps_list( $apps_list ){
        $root = 'personal_migration_app';
        $type = 'export';
        $apps_list[$root.'_'.$type] = [
            'key' => $root.'_'.$type,
            'url_base' => $root. '/'. $type,
            'label' => __( 'Personal Migration', 'disciple_tools' ),
            'description' => __( 'Enable personal migration export link.', 'disciple_tools' ),
        ];
        return $apps_list;
    }

    /**
     * Adds menu item
     *
     * @param $dt_user WP_User object
     * @param $dt_user_meta array Full array of user meta data
     * @param $dt_user_contact_id bool/int returns either id for contact connected to user or false
     * @param $contact_fields array Array of fields on the contact record
     */
    public function dt_profile_settings_page_menu( $dt_user, $dt_user_meta, $dt_user_contact_id, $contact_fields ) {
        ?>
        <li><a href="#dt_personal_migration_settings_id"><?php esc_html_e( 'Personal Migration', 'disciple_tools' )?></a></li>
        <?php
    }

    /**
     * Adds custom tile
     *
     * @param $dt_user WP_User object
     * @param $dt_user_meta array Full array of user meta data
     * @param $dt_user_contact_id bool/int returns either id for contact connected to user or false
     * @param $contact_fields array Array of fields on the contact record
     */
    public function dt_profile_settings_page_sections( $dt_user, $dt_user_meta, $dt_user_contact_id, $contact_fields  ) {
//        $url = trailingslashit( site_url() ) . 'dt_personal_migration_app/export/' . $current_user_public_key = hash('sha256', serialize( get_current_user() ) );
        ?>
        <div class="cell bordered-box" id="dt_personal_migration_settings_id" data-magellan-target="dt_personal_migration_settings_id">
            <button class="help-button float-right" data-section="disciple-tools-personal-migration-help-text">
                <img class="help-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/help.svg' ) ?>"/>
            </button>

            <span class="section-header"><?php esc_html_e( 'Personal Migration', 'disciple_tools' )?></span>
            <hr/>

            <!--<a class="button small" href="<?php /*echo esc_url( $url ) */?>">Export</a>-->
            <button type="button" class="button small" id="dt_personal_migration_import_button">Import</button>
            <script>
                jQuery(document).ready(function(){
                    jQuery('#dt_personal_migration_import_button').on('click', function(){
                        let title = jQuery('#modal-large-title')
                        let content = jQuery('#modal-large-content')

                        title.empty().html(`Import Personal Contacts and Groups`)

                        content.empty().html(
                            `<div class="grid-x">
                                <div class="cell">
                                      Import JSON URL <br>
                                    <div class="input-group">
                                      <input class="input-group-field" type="text" id="dt-personal-migration-migration-initiate-input" placeholder="add JSON url" value="https://colorado.zume.community/personal_migration_app/export/2098e1218896d6a31edd705c951ffcd81db2a904b41cc4b317dfe9d572283b82">
                                      <div class="input-group-button">
                                        <input type="submit" class="button" value="Transfer" id="dt-personal-migration-migration-initiate-button">
                                      </div>
                                    </div>
                                </div>
                                <div id="dt-personal-migration-migration-progress"></div>
                            </div>`
                        )

                        jQuery('#modal-large').foundation('open')

                        let progress = jQuery('#dt-personal-migration-migration-progress')

                        jQuery('#dt-personal-migration-migration-initiate-button').on('click', () => {
                            let url = jQuery('#dt-personal-migration-migration-initiate-input').val()
                            if ( url === '' ){
                                return
                            }

                            /* @todo check for compliant url https, etc. */

                            progress.append(
                                `<div class="cell" id="dt-personal-migration-start_migration"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_contacts"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_meta_contacts"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_comments_contacts"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_connections_contacts"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_groups"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_meta_groups"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_comments_groups"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_connections_groups"><span class="loading-spinner active"></span></div>
                                `
                            )

                            makeRequest('post', 'endpoint', { action: 'start_migration', data: { url: url } }, 'dt_personal_migration/v1/')
                                .done(function(data) {
                                    jQuery('#dt-personal-migration-start_migration').html(data.message)

                                    if ( data.next_action ) {
                                        load_installer( data.next_action )
                                    }
                                })
                                .fail(function (err) {
                                    jQuery('#dt-personal-migration-load-data').html(`Data failed in collection from other system!`).append(stringify(err))
                                    console.log("error");
                                    console.log(err);
                                });
                        })
                        function load_installer( action ) {
                            makeRequest('post', 'endpoint', { action: action }, 'dt_personal_migration/v1/')
                                .done(function(data) {
                                    console.log(data)
                                    if ( data.message === 'Loop' ) {
                                        jQuery('#dt-personal-migration-'+action).append(`<span class="loading-spinner active"></span>`)
                                    }
                                    else {
                                        jQuery('#dt-personal-migration-'+action).html(data.message)
                                    }

                                    if ( data.next_action ) {
                                        load_installer( data.next_action )
                                    }
                                })
                                .fail(function (err) {
                                    jQuery('#dt-personal-migration-'+action).html(data.message).append(stringify(err))
                                    console.log("error");
                                    console.log(err);
                                });
                        }


                    })
                })
            </script>

        </div>
        <?php
    }

    /**
     * @see disciple-tools-theme/dt-assets/parts/modals/modal-help.php
     */
    public function dt_modal_help_text(){
        ?>
        <div class="help-section" id="disciple-tools-personal-migration-help-text" style="display: none">
            <h3><?php echo esc_html_x( "Personal Migration", 'Optional Documentation', 'disciple_tools' ) ?></h3>
            <p><?php echo esc_html_x( "Personal contacts and groups that you have access to can be exported and imported with this tool between Disciple Tools systems. Access contacts are controlled by the Disciple Tools administrators and can only be exported by them.", 'Optional Documentation', 'disciple_tools' ) ?></p>
        </div>
        <?php
    }
}

DT_Personal_Migration_Settings_Tile::instance();
