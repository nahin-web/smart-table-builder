<?php
/**
 * Plugin Name: Smart Table Builder
 * Description: Convert excel sheets to an embeddable table 
 * Plugin URI: https://designful.ca
 * Author: Designful Inc.
 * Version: 1.0.3
 * License: GPL2
 * Text Domain: smart-table-builder
 * Domain Path: /languages/
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

// don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Smart_Table_Builder class
 *
 * @class Smart_Table_Builder The class that holds the entire Smart_Table_Builder plugin
 */
final class Smart_Table_Builder {

    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '0.1.0';

    /**
     * Holds various class instances
     *
     * @var array
     */
    private $container = array();

    /**
     * Constructor for the Smart_Table_Builder class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     */
    public function __construct() {

        $this->define_constants();

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        add_action( 'smart_table_builder_load_preview', array( $this, 'display_table_preview' ) );
    }

    /**
     * Initializes the Smart_Table_Builder() class
     *
     * Checks for an existing Smart_Table_Builder() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new Smart_Table_Builder();
        }

        return $instance;
    }

    /**
     * Magic getter to bypass referencing plugin.
     *
     * @param $prop
     *
     * @return mixed
     */
    public function __get( $prop ) {
        if ( array_key_exists( $prop, $this->container ) ) {
            return $this->container[ $prop ];
        }

        return $this->{$prop};
    }

    /**
     * Magic isset to bypass referencing plugin.
     *
     * @param $prop
     *
     * @return mixed
     */
    public function __isset( $prop ) {
        return isset( $this->{$prop} ) || isset( $this->container[ $prop ] );
    }

    /**
     * Define the constants
     *
     * @return void
     */
    public function define_constants() {
        define( 'SMART_TABLE_BUILDER_VERSION', $this->version );
        define( 'SMART_TABLE_BUILDER_FILE', __FILE__ );
        define( 'SMART_TABLE_BUILDER_PATH', dirname( SMART_TABLE_BUILDER_FILE ) );
        define( 'SMART_TABLE_BUILDER_INCLUDES', SMART_TABLE_BUILDER_PATH . '/includes' );
        define( 'SMART_TABLE_BUILDER_URL', plugins_url( '', SMART_TABLE_BUILDER_FILE ) );
        define( 'SMART_TABLE_BUILDER_ASSETS_URL', SMART_TABLE_BUILDER_URL . '/assets' );
        define( 'SMART_TABLE_BUILDER_ASSETS_PATH', SMART_TABLE_BUILDER_PATH . '/assets' );
    }

    /**
     * Load the plugin after all plugis are loaded
     *
     * @return void
     */
    public function init_plugin() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Placeholder for activation function
     *
     * Nothing being called here yet.
     */
    public function activate() {

        $installed = get_option( 'SMART_TABLE_BUILDER_installed' );

        if ( ! $installed ) {
            update_option( 'SMART_TABLE_BUILDER_installed', time() );
        }

        update_option( 'SMART_TABLE_BUILDER_version', SMART_TABLE_BUILDER_VERSION );
    }

    /**
     * Placeholder for deactivation function
     *
     * Nothing being called here yet.
     */
    public function deactivate() {

    }

    /**
     * Include the required files
     *
     * @return void
     */
    public function includes() {

        require_once SMART_TABLE_BUILDER_INCLUDES . '/Assets.php';

        if ( $this->is_request( 'admin' ) ) {
            require_once SMART_TABLE_BUILDER_INCLUDES . '/Admin.php';
        }

        if ( $this->is_request( 'frontend' ) ) {
            require_once SMART_TABLE_BUILDER_INCLUDES . '/Frontend.php';
        }

        if ( $this->is_request( 'ajax' ) ) {
            require_once SMART_TABLE_BUILDER_INCLUDES . '/Ajax/AjaxAction.php';
            require_once SMART_TABLE_BUILDER_INCLUDES . '/Ajax/AjaxCallbacks.php';
        }

        require_once SMART_TABLE_BUILDER_INCLUDES . '/Api.php';
        require_once SMART_TABLE_BUILDER_INCLUDES . '/Utils.php';
        require_once SMART_TABLE_BUILDER_INCLUDES . '/GoogleFontsCache.php';
    }

    /**
     * Initialize the hooks
     *
     * @return void
     */
    public function init_hooks() {

        add_action( 'init', array( $this, 'init_classes' ) );

        // Localize our plugin
        add_action( 'init', array( $this, 'localization_setup' ) );
        // register post type
        add_action('init', array($this, 'spreadsheet_table_type_init'), 0);
    }

    public function output_index() {

    }

    public function display_table_preview() {
        // enqueue script
        wp_enqueue_script( 'smart-table-builder-preview', SMART_TABLE_BUILDER_ASSETS_URL . '/js/frontend.js' , array(  ), SMART_TABLE_BUILDER_VERSION, array(
            'in_footer' => true,
            'strategy'  => 'defer',
        ) );
        wp_script_add_data( 'smart-table-builder-preview', 'defer', true );
        load_template( SMART_TABLE_BUILDER_PATH . '/templates/base.php' , true );
        die();
    }

    /**
     * Instantiate the required classes
     *
     * @return void
     */
    public function init_classes() {
        // phpcs:ignore
        $key = ! empty( $_GET['smart_table_builder_action'] ) ? sanitize_key( $_GET['smart_table_builder_action'] ) : false;
        if ( ! empty( $key ) ) {
            do_action( "smart_table_builder_{$key}", [] );
        }
        if ( $this->is_request( 'admin' ) ) {
            $this->container['admin'] = new Smart_Table_Builder_Lib\Admin();
        }

        if ( $this->is_request( 'frontend' ) ) {
            $this->container['frontend'] = new Smart_Table_Builder_Lib\Frontend();
        }
        // phpcs:ignore
        if ( $this->is_request( 'ajax' ) && !isset($_GET['wc-ajax']) ) {
            $this->container['ajax'] =  new Smart_Table_Builder_Lib\Ajax\AjaxAction();
        }

        $this->container['api'] = new Smart_Table_Builder_Lib\Api();
        $this->container['fonts'] = new Smart_Table_Builder_Lib\GoogleFontsCache("a");
        $this->container['assets'] = new Smart_Table_Builder_Lib\Assets();
    }

    /**
     * Initialize plugin for localization
     *
     * @uses load_plugin_textdomain()
     */
    public function localization_setup() {
        load_plugin_textdomain( 'smart-table-builder', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * What type of request is this?
     *
     * @param  string $type admin, ajax, cron or frontend.
     *
     * @return bool
     */
    private function is_request( $type ) {
        switch ( $type ) {
            case 'admin' :
                return is_admin();

            case 'ajax' :
                return defined( 'DOING_AJAX' );

            case 'rest' :
                return defined( 'REST_REQUEST' );

            case 'cron' :
                return defined( 'DOING_CRON' );

            case 'frontend' :
                return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
        }
    }

    public function spreadsheet_table_type_init() {
        $post_types = $this->spreadsheet_table_post_types();
    
        foreach ($post_types as $post_type => $post_type_info) {
            $add_args = (!empty($post_type_info['args'])) ? $post_type_info['args'] : array();
            $args = $this->spreadsheet_table_post_type_args(
                $this->spreadsheet_table_post_types_labels(
                    $post_type_info['single'],
                    $post_type_info['plural']
                ),
                $post_type,
                $add_args
            );
    
            register_post_type($post_type, $args);
        }
    }

    function spreadsheet_table_post_types_labels($singular, $plural, $admin_bar_name = '')
    {
        $admin_bar_name = (!empty($admin_bar_name)) ? $admin_bar_name : $plural;
        return array(
            'name'               => sprintf('%s', $plural), 'post type general name', 'df-smart-table-builder',
            'singular_name'      => sprintf('Calc', 'post type singular name', 'df-smart-table-builder'),
            'menu_name'          => sprintf('%s', $plural), 'admin menu', 'df-smart-table-builder',
            'name_admin_bar'     => sprintf('%s', 'Admin bar ' . $singular . ' name', 'df-smart-table-builder'),
            'add_new_item'       => sprintf(__('Add New %s', 'df-smart-table-builder'), $singular),
            'new_item'           => sprintf(__('New %s', 'df-smart-table-builder'), $singular),
            'edit_item'          => sprintf(__('Edit %s', 'df-smart-table-builder'), $singular),
            'view_item'          => sprintf(__('View %s', 'df-smart-table-builder'), $singular),
            'all_items'          => sprintf('%s', 'Admin bar ' . $singular . ' name', 'df-smart-table-builder'),
            'search_items'       => sprintf(__('Search %s', 'df-smart-table-builder'), $plural),
            'parent_item_colon'  => sprintf(__('Parent %s:', 'df-smart-table-builder'), $plural),
            'not_found'          => sprintf(__('No %s found.', 'df-smart-table-builder'), $plural),
            'not_found_in_trash' => sprintf(__('No %s found in Trash.', 'df-smart-table-builder'), $plural),
        );
    }

    private function spreadsheet_table_post_types() {
        return array(
            'sprdsh_table' => array(
                'single'         => 'Smart Table Builder',
                'plural'         => 'Smart Table Builder',
            ),
        );
    }

    private function spreadsheet_table_post_type_args($labels, $slug, $args = array())
    {
        $default_args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => false,
            'rewrite'            => array('slug' => $slug),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title')
        );

        return wp_parse_args($args, $default_args);
    }

} // Smart_Table_Builder

$smart_table_builder = Smart_Table_Builder::init();
