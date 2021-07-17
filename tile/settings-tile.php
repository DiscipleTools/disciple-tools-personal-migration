<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Personal_Migration_Settings_Tile
{
    public $root = "personal_migration_app";
    public $type = 'export';

    private static $_instance = null;
    public static function instance() {
        if (is_null( self::$_instance )) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        if ( 'settings' === dt_get_url_path() ) {
            add_action( 'dt_profile_settings_page_menu', [ $this, 'dt_profile_settings_page_menu' ], 100, 4 );
            add_action( 'dt_profile_settings_page_sections', [ $this, 'dt_profile_settings_page_sections' ], 100, 4 );
            add_action( 'dt_modal_help_text', [ $this, 'dt_modal_help_text' ], 100 );
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
    public function dt_profile_settings_page_sections( $dt_user, $dt_user_meta, $dt_user_contact_id, $contact_fields ) {
        global $wpdb;
        $app_key = 'personal_migration_app_export';
        $app_url_base = trailingslashit( trailingslashit( site_url() ) . 'personal_migration_app/export' );
        $dt_personal_migration_is_enabled = isset( $dt_user_meta[$wpdb->prefix . $app_key][0] ) ? $dt_user_meta[$wpdb->prefix . $app_key][0] : false;
        $app_url = '';
        if ( $dt_personal_migration_is_enabled ) {
            $app_url = $app_url_base . $dt_personal_migration_is_enabled;
        }
        ?>
        <style>
            .dt_personal_migration_hide {
                display:none;
            }
        </style>
        <div class="cell bordered-box" id="dt_personal_migration_settings_id" data-magellan-target="dt_personal_migration_settings_id">
            <button class="help-button float-right" data-section="disciple-tools-personal-migration-help-text">
                <img class="help-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/help.svg' ) ?>"/>
            </button>

            <span class="section-header"><?php esc_html_e( 'Personal Migration', 'disciple_tools' )?></span>

            <hr/>

            <button type="button" class="button" id="dt_personal_migration_import_button">Import</button>
            <button type="button" class="button" id="dt_personal_migration_export_button"><?php echo empty( $dt_personal_migration_is_enabled ) ? esc_html( 'Enable Export' ) : esc_html( 'Disable Export' ); ?></button>
            <span class="loading-spinner"></span>
            <div id="dt_personal_migration_export_link" class="<?php echo empty( $dt_personal_migration_is_enabled ) ? 'dt_personal_migration_hide' : ''; ?>">
                <div class="input-group">
                    <span class="input-group-label">Current Export Link</span>
                    <input class="input-group-field" type="text" id="dt_personal_migration_export_input" value="<?php echo empty( $dt_personal_migration_is_enabled ) ? '' : esc_url( $app_url ); ?>">
                    <div class="input-group-button">
                        <input type="button" class="button" id="dt_personal_migration_export_copy" value="Copy Link">
                    </div>
                </div>
            </div>
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
                                      <input class="input-group-field" type="text" id="dt-personal-migration-migration-initiate-input" placeholder="add JSON url">
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

                            jQuery('#dt-personal-migration-migration-initiate-button').prop('disabled', true )

                            /* @todo check for compliant url https, etc. */

                            progress.append(
                                `<div class="cell" id="dt-personal-migration-start_migration"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_contacts"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_meta_contacts"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_comments_contacts"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_groups"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_meta_groups"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_comments_groups"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_contacts_to_contacts"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_groups_to_groups"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_contacts_to_groups"><span class="loading-spinner active"></span></div>
                                <div class="cell" id="dt-personal-migration-install_groups_to_contacts"><span class="loading-spinner active"></span></div>
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
                                    progress.html(`Data failed in collection from other system!<br><br> ` + err.responseText)
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

                    jQuery('#dt_personal_migration_export_button').on('click', function(){
                        let spinner = jQuery('.loading-spinner')
                        spinner.addClass('active')
                        let button = jQuery('#dt_personal_migration_export_button')
                        let input = jQuery('#dt_personal_migration_export_input')
                        let input_section = jQuery('#dt_personal_migration_export_link')
                        let app_url_base = '<?php echo esc_url( $app_url_base ) ?>'
                        makeRequest('post', 'users/app_switch', { app_key: '<?php echo esc_attr( $app_key ) ?>'})
                            .done(function(data) {
                                console.log(data)
                                if ('removed' === data) {
                                    button.html('Enable Export')
                                    input.empty()
                                    input_section.addClass('dt_personal_migration_hide')
                                } else {
                                    button.html('Disable Export')
                                    input.val( app_url_base + data)
                                    input_section.removeClass('dt_personal_migration_hide')
                                }
                                spinner.removeClass('active')
                            })
                            .fail(function (err) {
                                console.log("error");
                                console.log(err);
                                // a.empty().html(`error`)
                            });
                    })

                    jQuery('#dt_personal_migration_export_copy').on('click', function(){
                        let str = jQuery('#dt_personal_migration_export_input').val()
                        const el = document.createElement('textarea');
                        el.value = str;
                        el.setAttribute('readonly', '');
                        el.style.position = 'absolute';
                        el.style.left = '-9999px';
                        document.body.appendChild(el);
                        const selected =
                            document.getSelection().rangeCount > 0
                                ? document.getSelection().getRangeAt(0)
                                : false;
                        el.select();
                        document.execCommand('copy');
                        document.body.removeChild(el);
                        if (selected) {
                            document.getSelection().removeAllRanges();
                            document.getSelection().addRange(selected);
                        }
                        alert('Copied')
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
