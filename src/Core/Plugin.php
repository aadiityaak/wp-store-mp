<?php

namespace WpStoreMp\Core;

class Plugin
{
    public function run()
    {
        $this->load_api();
        $this->load_frontend();
    }

    private function load_api()
    {
        $products = new \WpStoreMp\Api\ProductController();
        add_action('rest_api_init', [$products, 'register_routes']);
    }

    private function load_frontend()
    {
        $shortcode = new \WpStoreMp\Frontend\Shortcode();
        $shortcode->register();
    }
}
