<?php

/**
 * Plugin Name: WP Store Marketplace
 * Description: Addon untuk fitur marketplace pada WP Store. Setiap user bisa membuat toko dan upload produk dari frontend.
 * Version:     0.1.0
 * Author:      Aditya Kristyanto
 * Text Domain: wp-store-mp
 */
if (!defined('ABSPATH')) {
  exit;
}
define('WP_STORE_MP_VERSION', '0.1.0');
define('WP_STORE_MP_PATH', plugin_dir_path(__FILE__));
define('WP_STORE_MP_URL', plugin_dir_url(__FILE__));
spl_autoload_register(function ($class) {
  $prefix = 'WpStoreMp\\';
  $base_dir = WP_STORE_MP_PATH . 'src/';
  $len = strlen($prefix);
  if (strncmp($prefix, $class, $len) !== 0) {
    return;
  }
  $relative_class = substr($class, $len);
  $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
  if (file_exists($file)) {
    require $file;
  }
});
function wp_store_mp_init()
{
  if (!defined('WP_STORE_VERSION')) {
    return;
  }
  $plugin = new \WpStoreMp\Core\Plugin();
  $plugin->run();
}
add_action('plugins_loaded', 'wp_store_mp_init');
register_activation_hook(__FILE__, function () {
  add_role('store_vendor', 'Store Vendor', [
    'read' => true,
    'upload_files' => true,
    'edit_posts' => true,
    'publish_posts' => true,
    'delete_posts' => true,
    'edit_published_posts' => true,
    'delete_published_posts' => true,
  ]);
  wp_store_mp_init();
  flush_rewrite_rules();
});
