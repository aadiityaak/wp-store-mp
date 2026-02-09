<?php

namespace WpStoreMp\Api;

use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

class ProductController
{
    public function register_routes()
    {
        register_rest_route('wp-store-mp/v1', '/products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_my_products'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_product'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ],
        ]);
    }

    public function get_my_products(WP_REST_Request $request)
    {
        $uid = get_current_user_id();
        if ($uid <= 0) {
            return new WP_REST_Response(['message' => 'Unauthorized'], 401);
        }
        $per_page = (int) $request->get_param('per_page');
        if ($per_page <= 0 || $per_page > 50) {
            $per_page = 12;
        }
        $paged = (int) $request->get_param('page');
        if ($paged <= 0) {
            $paged = 1;
        }
        $args = [
            'post_type' => 'store_product',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'post_status' => ['publish', 'draft', 'pending'],
            'author' => $uid,
        ];
        $query = new WP_Query($args);
        $items = [];
        foreach ($query->posts as $post) {
            $items[] = $this->format_product($post->ID);
        }
        $response = [
            'items' => $items,
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'page' => $paged,
        ];
        return new WP_REST_Response($response, 200);
    }

    public function create_product(WP_REST_Request $request)
    {
        $uid = get_current_user_id();
        if ($uid <= 0) {
            return new WP_REST_Response(['message' => 'Unauthorized'], 401);
        }
        $title = (string) $request->get_param('title');
        $content = (string) $request->get_param('content');
        $status = (string) $request->get_param('status');
        $price = $request->get_param('price');
        $stock = $request->get_param('stock');
        $image_id = (int) $request->get_param('image_id');
        $cats = $request->get_param('categories');
        if ($title === '') {
            return new WP_REST_Response(['message' => 'Judul wajib diisi'], 422);
        }
        if (!in_array($status, ['draft', 'pending', 'publish'], true)) {
            $status = 'draft';
        }
        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => 'store_product',
            'post_author'  => $uid,
        ], true);
        if (is_wp_error($post_id)) {
            return new WP_REST_Response(['message' => 'Gagal membuat produk'], 500);
        }
        if ($price !== null && $price !== '') {
            update_post_meta($post_id, '_store_price', (float) $price);
        }
        if ($stock !== null && $stock !== '') {
            update_post_meta($post_id, '_store_stock', (int) $stock);
        }
        if ($image_id > 0) {
            set_post_thumbnail($post_id, $image_id);
        }
        if (is_array($cats) && !empty($cats)) {
            $term_ids = array_map('intval', $cats);
            wp_set_object_terms($post_id, $term_ids, 'store_product_cat', false);
        }
        return new WP_REST_Response([
            'success' => true,
            'item' => $this->format_product($post_id),
        ], 201);
    }

    private function format_product($id)
    {
        $price = get_post_meta($id, '_store_price', true);
        $stock = get_post_meta($id, '_store_stock', true);
        $image = get_the_post_thumbnail_url($id, 'medium');
        return [
            'id' => $id,
            'title' => get_the_title($id),
            'slug' => get_post_field('post_name', $id),
            'excerpt' => wp_trim_words(get_post_field('post_content', $id), 20),
            'price' => $price !== '' ? (float) $price : null,
            'stock' => $stock !== '' ? (int) $stock : null,
            'image' => $image ? $image : null,
            'link' => get_permalink($id),
            'status' => get_post_status($id),
        ];
    }
}
