<?php

namespace WpStoreMp\Core;

class Plugin
{
    public function run()
    {
        $this->load_api();
        $this->load_admin();
        $this->load_frontend();
    }

    private function load_api()
    {
        $products = new \WpStoreMp\Api\ProductController();
        add_action('rest_api_init', [$products, 'register_routes']);
        $vendor = new \WpStoreMp\Api\VendorController();
        add_action('rest_api_init', [$vendor, 'register_routes']);
    }

    private function load_admin()
    {
        if (!is_admin()) {
            return;
        }
        $pt = new \WpStoreMp\Admin\PostTypes();
        add_action('init', [$pt, 'register_vendor_request']);
        $menu = new \WpStoreMp\Admin\Menu();
        $menu->register();
    }
    private function load_frontend()
    {
        $shortcode = new \WpStoreMp\Frontend\Shortcode();
        $shortcode->register();
        $hooks = new \WpStoreMp\Frontend\ProfileHooks();
        $hooks->register();
    }
}
