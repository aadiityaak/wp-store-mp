<?php

namespace WpStoreMp\Api;

use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WpStore\Admin\ProductMetaBoxes as AdminProductMetaBoxes;

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
        register_rest_route('wp-store-mp/v1', '/products/(?P<id>\d+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_product'],
                'permission_callback' => function (\WP_REST_Request $req) {
                    $id = (int) $req['id'];
                    return $id > 0 && current_user_can('edit_post', $id);
                },
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_product'],
                'permission_callback' => function (\WP_REST_Request $req) {
                    $id = (int) $req['id'];
                    return $id > 0 && current_user_can('delete_post', $id);
                },
            ],
        ]);
        register_rest_route('wp-store-mp/v1', '/products/schema', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_schema'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ],
        ]);
        register_rest_route('wp-store-mp/v1', '/products/categories', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_categories'],
                'permission_callback' => function () {
                    return is_user_logged_in();
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

    public function get_schema(WP_REST_Request $request)
    {
        if (!class_exists(AdminProductMetaBoxes::class)) {
            return new WP_REST_Response(['tabs' => []], 200);
        }
        $schema = AdminProductMetaBoxes::get_schema();
        return new WP_REST_Response(['tabs' => $schema], 200);
    }

    public function get_categories(WP_REST_Request $request)
    {
        $terms = get_terms([
            'taxonomy' => 'store_product_cat',
            'hide_empty' => false,
        ]);
        $items = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $items[] = [
                    'id' => (int) $t->term_id,
                    'name' => (string) $t->name,
                    'count' => (int) $t->count,
                ];
            }
        }
        return new WP_REST_Response(['items' => $items], 200);
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
        $sale_price = $request->get_param('sale_price');
        $sku = (string) $request->get_param('sku');
        $stock = $request->get_param('stock');
        $min_order = $request->get_param('min_order');
        $weight_kg = $request->get_param('weight_kg');
        $product_type = (string) $request->get_param('product_type');
        $label = (string) $request->get_param('label');
        $option_name = (string) $request->get_param('option_name');
        $options = $request->get_param('options');
        $option2_name = (string) $request->get_param('option2_name');
        $advanced_options = $request->get_param('advanced_options');
        $gallery_ids = $request->get_param('gallery_ids');
        $flashsale_until = (string) $request->get_param('flashsale_until');
        $digital_file = (int) $request->get_param('digital_file');
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
        $meta_payload = (array) $request->get_param('meta');
        $meta_map = [
            '_store_product_type' => $product_type ?: 'physical',
            '_store_price' => $price,
            '_store_sale_price' => $sale_price,
            '_store_flashsale_until' => $flashsale_until,
            '_store_digital_file' => $digital_file,
            '_store_sku' => $sku,
            '_store_stock' => $stock,
            '_store_min_order' => $min_order,
            '_store_weight_kg' => $weight_kg,
            '_store_label' => $label,
            '_store_option_name' => $option_name,
            '_store_options' => $options,
            '_store_option2_name' => $option2_name,
            '_store_advanced_options' => $advanced_options,
            '_store_gallery_ids' => $gallery_ids,
        ];
        $allowed_meta = $this->get_allowed_meta_keys();
        $filtered_payload = array_intersect_key($meta_payload, array_flip($allowed_meta));
        $this->save_meta_fields($post_id, array_merge($meta_map, $filtered_payload));
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

    public function update_product(WP_REST_Request $request)
    {
        $id = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== 'store_product') {
            return new WP_REST_Response(['message' => 'Produk tidak ditemukan'], 404);
        }
        $title = (string) $request->get_param('title');
        $content = (string) $request->get_param('content');
        $status = (string) $request->get_param('status');
        if ($title !== '') {
            wp_update_post(['ID' => $id, 'post_title' => $title]);
        }
        if ($content !== '') {
            wp_update_post(['ID' => $id, 'post_content' => $content]);
        }
        if (in_array($status, ['draft', 'pending', 'publish'], true)) {
            wp_update_post(['ID' => $id, 'post_status' => $status]);
        }
        $image_id = (int) $request->get_param('image_id');
        if ($image_id > 0) {
            set_post_thumbnail($id, $image_id);
        }
        $cats = $request->get_param('categories');
        if (is_array($cats)) {
            $term_ids = array_map('intval', $cats);
            wp_set_object_terms($id, $term_ids, 'store_product_cat', false);
        }
        $meta_payload = (array) $request->get_param('meta');
        $meta_map = [
            '_store_product_type' => (string) $request->get_param('product_type'),
            '_store_price' => $request->get_param('price'),
            '_store_sale_price' => $request->get_param('sale_price'),
            '_store_flashsale_until' => (string) $request->get_param('flashsale_until'),
            '_store_digital_file' => $request->get_param('digital_file'),
            '_store_sku' => (string) $request->get_param('sku'),
            '_store_stock' => $request->get_param('stock'),
            '_store_min_order' => $request->get_param('min_order'),
            '_store_weight_kg' => $request->get_param('weight_kg'),
            '_store_label' => (string) $request->get_param('label'),
            '_store_option_name' => (string) $request->get_param('option_name'),
            '_store_options' => $request->get_param('options'),
            '_store_option2_name' => (string) $request->get_param('option2_name'),
            '_store_advanced_options' => $request->get_param('advanced_options'),
            '_store_gallery_ids' => $request->get_param('gallery_ids'),
        ];
        $allowed_meta = $this->get_allowed_meta_keys();
        $filtered_payload = array_intersect_key($meta_payload, array_flip($allowed_meta));
        $this->save_meta_fields($id, array_merge($meta_map, $filtered_payload));
        return new WP_REST_Response(['success' => true, 'item' => $this->format_product($id)], 200);
    }

    public function delete_product(WP_REST_Request $request)
    {
        $id = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== 'store_product') {
            return new WP_REST_RESPONSE(['message' => 'Produk tidak ditemukan'], 404);
        }
        $r = wp_trash_post($id);
        if ($r === false) {
            return new WP_REST_Response(['message' => 'Gagal menghapus produk'], 500);
        }
        return new WP_REST_Response(['success' => true], 200);
    }

    private function format_product($id)
    {
        $price = get_post_meta($id, '_store_price', true);
        $sale_price = get_post_meta($id, '_store_sale_price', true);
        $stock = get_post_meta($id, '_store_stock', true);
        $image = get_the_post_thumbnail_url($id, 'medium');
        $gallery_meta = get_post_meta($id, '_store_gallery_ids', true);
        $gallery_ids = [];
        if (is_array($gallery_meta)) {
            foreach ($gallery_meta as $aid => $url) {
                $gallery_ids[] = (int) $aid;
            }
        }
        $terms = wp_get_object_terms($id, 'store_product_cat', ['fields' => 'ids']);
        return [
            'id' => $id,
            'title' => get_the_title($id),
            'slug' => get_post_field('post_name', $id),
            'content' => get_post_field('post_content', $id),
            'excerpt' => wp_trim_words(get_post_field('post_content', $id), 20),
            'price' => $price !== '' ? (float) $price : null,
            'sale_price' => $sale_price !== '' ? (float) $sale_price : null,
            'stock' => $stock !== '' ? (int) $stock : null,
            'image' => $image ? $image : null,
            'link' => get_permalink($id),
            'status' => get_post_status($id),
            'product_type' => get_post_meta($id, '_store_product_type', true) ?: 'physical',
            'flashsale_until' => get_post_meta($id, '_store_flashsale_until', true) ?: '',
            'digital_file' => get_post_meta($id, '_store_digital_file', true) ?: '',
            'sku' => get_post_meta($id, '_store_sku', true) ?: '',
            'min_order' => ($m = get_post_meta($id, '_store_min_order', true)) !== '' ? (int) $m : null,
            'weight_kg' => ($w = get_post_meta($id, '_store_weight_kg', true)) !== '' ? (float) $w : null,
            'label' => get_post_meta($id, '_store_label', true) ?: '',
            'option_name' => get_post_meta($id, '_store_option_name', true) ?: '',
            'options' => (array) get_post_meta($id, '_store_options', true),
            'option2_name' => get_post_meta($id, '_store_option2_name', true) ?: '',
            'advanced_options' => (array) get_post_meta($id, '_store_advanced_options', true),
            'gallery_ids' => $gallery_ids,
            'categories' => is_wp_error($terms) ? [] : array_map('intval', $terms),
        ];
    }

    private function save_meta_fields($post_id, $fields)
    {
        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($key === '_store_options' || $key === '_store_advanced_options' || $key === '_store_gallery_ids') {
                if (is_string($value)) {
                    $try = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $try;
                    }
                }
            }
            if ($key === '_store_price' || $key === '_store_sale_price') {
                $value = is_numeric($value) ? (float) $value : $value;
            }
            if ($key === '_store_stock' || $key === '_store_min_order') {
                $value = is_numeric($value) ? (int) $value : $value;
            }
            if ($key === '_store_weight_kg') {
                $value = is_numeric($value) ? (float) $value : $value;
            }
            if ($key === '_store_digital_file') {
                if (is_numeric($value) && (int) $value > 0) {
                    $url = wp_get_attachment_url((int) $value);
                    if ($url) {
                        $value = $url;
                    }
                }
            }
            if ($key === '_store_gallery_ids') {
                if (is_array($value)) {
                    $out = [];
                    foreach ($value as $aid) {
                        $aid = (int) $aid;
                        if ($aid > 0) {
                            $url = wp_get_attachment_url($aid);
                            if ($url) {
                                $out[$aid] = $url;
                            }
                        }
                    }
                    $value = $out;
                }
            }
            update_post_meta($post_id, $key, $value);
        }
    }

    private function get_allowed_meta_keys()
    {
        $keys = [
            '_store_product_type',
            '_store_price',
            '_store_sale_price',
            '_store_flashsale_until',
            '_store_digital_file',
            '_store_sku',
            '_store_stock',
            '_store_min_order',
            '_store_weight_kg',
            '_store_label',
            '_store_option_name',
            '_store_options',
            '_store_option2_name',
            '_store_advanced_options',
            '_store_gallery_ids',
        ];
        if (class_exists(AdminProductMetaBoxes::class)) {
            $schema = AdminProductMetaBoxes::get_schema();
            $keys = [];
            foreach ($schema as $tab) {
                foreach ($tab['fields'] as $f) {
                    $keys[] = $f['id'];
                }
            }
        }
        return array_values(array_unique($keys));
    }
}
