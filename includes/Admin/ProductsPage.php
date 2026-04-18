<?php
namespace BPD\Admin;

defined( 'ABSPATH' ) || exit;

class ProductsPage {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_footer',          [ $this, 'render_modal' ] );
        // ← الطريقة الصحيحة لإضافة زر في صفحة المنتجات
        add_action( 'restrict_manage_posts', [ $this, 'render_button' ] );
    }

    private function is_products_screen(): bool {
        $screen = get_current_screen();
        return $screen && $screen->id === 'edit-product';
    }

    public function render_button(): void {
        if ( ! $this->is_products_screen() ) return;
        ?>
        <button id="bpd-open-btn" class="button bpd-trigger-btn" type="button">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                 stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6l-1 14H6L5 6"/>
                <path d="M10 11v6"/><path d="M14 11v6"/>
                <path d="M9 6V4h6v2"/>
            </svg>
            حذف منتجات عبر Excel
        </button>
        <?php
    }

    public function enqueue( string $hook ): void {
        if ( ! $this->is_products_screen() ) return;

        wp_enqueue_style(
            'bpd-admin',
            BPD_URL . 'assets/css/admin.css',
            [],
            BPD_VERSION
        );

        wp_enqueue_script(
            'bpd-admin',
            BPD_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            BPD_VERSION,
            true
        );

        wp_localize_script( 'bpd-admin', 'BPD', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bpd_delete_nonce' ),
            'i18n'    => [
                'uploading'      => 'جارٍ رفع الملف وتحليله…',
                'deleting'       => 'جارٍ النقل إلى سلة المهملات…',
                'confirm'        => 'هل أنت متأكد من نقل {count} منتج إلى سلة المهملات؟',
                'done'           => 'تم نقل المنتجات إلى سلة المهملات بنجاح.',
                'error'          => 'حدث خطأ. يرجى المحاولة مجدداً.',
                'no_file'        => 'الرجاء اختيار ملف Excel.',
                'invalid_type'   => 'الرجاء رفع ملف Excel صالح (.xlsx أو .xls).',
                'nothing_found'  => 'لم يُعثر على أي منتج مطابق في قاعدة البيانات.',
            ],
        ] );
    }

    public function render_modal(): void {
        if ( ! $this->is_products_screen() ) return;
        include BPD_DIR . 'includes/Admin/views/modal.php';
    }
}