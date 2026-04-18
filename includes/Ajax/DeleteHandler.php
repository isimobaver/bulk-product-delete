<?php
namespace BPD\Ajax;

defined( 'ABSPATH' ) || exit;

use BPD\Excel\Reader;

/**
 * Handles two AJAX actions:
 *   bpd_analyze  – parse the uploaded Excel file, return matched/unmatched products
 *   bpd_delete   – permanently delete a list of product IDs
 */
class DeleteHandler {

    public function __construct() {
        add_action( 'wp_ajax_bpd_analyze', [ $this, 'analyze' ] );
        add_action( 'wp_ajax_bpd_delete',  [ $this, 'delete' ] );
    }

    /* --------------------------------------------------------
       ANALYZE  –  parse Excel → match products in DB
       -------------------------------------------------------- */
    public function analyze(): void {
        $this->verify_nonce();

        if ( empty( $_FILES['excel_file'] ) ) {
            wp_send_json_error( [ 'message' => 'لم يتم رفع أي ملف.' ], 400 );
        }

        $file  = $_FILES['excel_file'];
        $mode  = sanitize_key( $_POST['mode']      ?? 'sku' );   // 'sku' | 'name'
        $col   = max( 1, intval( $_POST['col_index'] ?? 1 ) ) - 1; // 0-based

        // Validate file type
        $allowed = [ 'xlsx', 'xls' ];
        $ext     = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, $allowed, true ) ) {
            wp_send_json_error( [ 'message' => 'نوع الملف غير مسموح. استخدم .xlsx أو .xls فقط.' ], 422 );
        }

        // Move to a temp location
        $tmp = wp_tempnam( $file['name'] );
        if ( ! move_uploaded_file( $file['tmp_name'], $tmp ) ) {
            wp_send_json_error( [ 'message' => 'فشل رفع الملف.' ], 500 );
        }

        // Read values from Excel
        try {
            $values = Reader::read_column( $tmp, $col );
        } catch ( \Exception $e ) {
            @unlink( $tmp );
            wp_send_json_error( [ 'message' => 'خطأ في قراءة الملف: ' . $e->getMessage() ], 422 );
        }
        @unlink( $tmp );

        if ( empty( $values ) ) {
            wp_send_json_error( [ 'message' => 'لم يتم العثور على بيانات في العمود المحدد.' ], 422 );
        }

        // Match against WooCommerce products
        $found     = [];
        $not_found = [];

        foreach ( $values as $val ) {
            $val = trim( (string) $val );
            if ( $val === '' ) continue;

            $product_id = $this->find_product( $val, $mode );
            if ( $product_id ) {
                $product  = wc_get_product( $product_id );
                $found[]  = [
                    'id'   => $product_id,
                    'name' => $product ? $product->get_name() : '',
                    'sku'  => $product ? $product->get_sku()  : '',
                ];
            } else {
                $not_found[] = $val;
            }
        }

        wp_send_json_success( [
            'found'     => $found,
            'not_found' => $not_found,
        ] );
    }

    /* --------------------------------------------------------
       DELETE  –  permanently delete product IDs
       -------------------------------------------------------- */
    public function delete(): void {
        $this->verify_nonce();

        $ids = array_map( 'intval',
                          (array) ( $_POST['product_ids'] ?? [] ) );
        $ids = array_filter( $ids );

        if ( empty( $ids ) ) {
            wp_send_json_error( [ 'message' => 'لم يتم تحديد أي منتج للحذف.' ], 400 );
        }

        $deleted = 0;
        $failed  = [];

        foreach ( $ids as $id ) {
            // Use WooCommerce's delete to also clean up meta & stock
            $product = wc_get_product( $id );
            if ( ! $product ) {
                $failed[] = $id;
                continue;
            }
            //  delete ( trash)
            $result = wp_trash_post( $id );
            ( $result !== false ) ? $deleted++ : ( $failed[] = $id );
        }

        // Clear transients / caches
        wc_delete_product_transients();
        delete_transient( 'wc_featured_products' );

        wp_send_json_success( [
            'deleted' => $deleted,
            'failed'  => $failed,
            'message' => sprintf(
                'تم نقل %d منتج إلى سلة المهملات.%s',
                $deleted,
                $failed ? ' فشل حذف: ' . implode( ', ', $failed ) : ''
            ),
        ] );
    }

    /* --------------------------------------------------------
       HELPERS
       -------------------------------------------------------- */

    private function verify_nonce(): void {
        if ( ! current_user_can( 'delete_products' ) ) {
            wp_send_json_error( [ 'message' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.' ], 403 );
        }
        if ( ! check_ajax_referer( 'bpd_delete_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'رمز الأمان غير صالح.' ], 403 );
        }
    }

    private function find_product( string $value, string $mode ): int {
        global $wpdb;

        if ( $mode === 'sku' ) {
            // Try wc_get_product_id_by_sku first (most reliable)
            $id = wc_get_product_id_by_sku( $value );
            return (int) $id;
        }

        // Mode = name: search post_title
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type IN ('product','product_variation')
               AND post_status != 'trash'
               AND post_title = %s
             LIMIT 1",
            $value
        ) );

        return (int) $id;
    }
}
