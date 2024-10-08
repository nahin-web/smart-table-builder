<?php
namespace Smart_Table_Builder_Lib;
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

class GoogleFontsCache {
    /**
     * Initialize the class
     *
     * @param AppSero\Client
     */
    public function __construct( $client, $name = null, $file = null ) {
        add_action( 'gfonts_metadata', array( $this, 'update_gfonts_metadata' ) );
        $this->schedule_event();
    }

    public function update_gfonts_metadata() {
        // Download the fonts metadata from google fonts, to keep the list of fonts updated
        $download_uri = 'https://fonts.google.com/metadata/fonts';

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $tmp_gfonts_metadata_path = download_url( esc_url_raw( $download_uri ) );

        if ( is_wp_error( $tmp_gfonts_metadata_path ) ) {
            return;
        }
        $wp_fs_direct = new \WP_Filesystem_Direct( new \StdClass() );
        $wp_fs_direct->move($tmp_gfonts_metadata_path, wp_upload_dir()['basedir'].'/fonts_meta.json');
    }

    public function get_tracking_data () {

    }

    /**
     * Schedule the event weekly
     *
     * @return void
     */
    private function schedule_event() {
        $hook_name = 'gfonts_metadata';

        if ( ! wp_next_scheduled( $hook_name ) ) {
            wp_schedule_event( time(), 'weekly', $hook_name );
        }
    }
}