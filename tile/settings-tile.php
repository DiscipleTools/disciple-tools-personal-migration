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
        }
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
        $url = trailingslashit( site_url() ) . 'dt_personal_migration_app/export/' . $current_user_public_key = hash('sha256', serialize( get_current_user() ) );
        ?>
        <div class="cell bordered-box" id="dt_personal_migration_settings_id" data-magellan-target="dt_personal_migration_settings_id">
            <button class="help-button float-right" data-section="disciple-tools-personal-migration-help-text">
                <img class="help-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/help.svg' ) ?>"/>
            </button>

            <span class="section-header"><?php esc_html_e( 'Personal Migration', 'disciple_tools' )?></span>
            <hr/>

            <a class="button small" href="<?php echo esc_url( $url ) ?>">Export</a>
            <button type="button" class="button small">Import</button>



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
