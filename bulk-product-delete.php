<?php
/**
 * Plugin Name:  Bulk Product Delete via Excel
 * Plugin URI:   https://your-site.com
 * Description:  حذف منتجات WooCommerce بشكل جماعي عبر رفع ملف Excel يحتوي على أسماء المنتجات أو رموز SKU.
 * Version:      1.0.0
 * Author:       Your Name
 * License:      GPL-2.0+
 * Text Domain:  bulk-product-delete
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'BPD_VERSION',  '1.0.0' );
define( 'BPD_DIR',      plugin_dir_path( __FILE__ ) );
define( 'BPD_URL',      plugin_dir_url( __FILE__ ) );
define( 'BPD_BASENAME', plugin_basename( __FILE__ ) );

/* ============================================================
   AUTO-LOAD  (simple PSR-4-style loader for this plugin)
   ============================================================ */
spl_autoload_register( function ( $class ) {
    $prefix = 'BPD\\';
    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) return;
    $file = BPD_DIR . 'includes/' .
            str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
    if ( file_exists( $file ) ) require $file;
} );

/* ============================================================
   BOOT
   ============================================================ */
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
               . '<strong>Bulk Product Delete:</strong> يتطلب هذا البرنامج المساعد تفعيل WooCommerce.'
               . '</p></div>';
        } );
        return;
    }
    \BPD\Plugin::instance();
} );
