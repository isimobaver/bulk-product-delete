<?php
namespace BPD;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton — wires everything together.
 */
final class Plugin {

    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load();
    }

    private function load(): void {
        new Admin\ProductsPage();   // UI button + modal
        new Ajax\DeleteHandler();   // AJAX delete logic
    }
}
