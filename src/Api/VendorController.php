<?php

namespace WpStoreMp\Api;

use WP_REST_Request;
use WP_REST_Response;

class VendorController
{
    public function register_routes()
    {
        register_rest_route('wp-store-mp/v1', '/vendor/apply', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'apply'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ],
        ]);
    }

    public function apply(WP_REST_Request $request)
    {
        $uid = get_current_user_id();
        if ($uid <= 0) {
            return new WP_REST_Response(['message' => 'Unauthorized'], 401);
        }
        $store_name = (string) $request->get_param('store_name');
        $desc = (string) $request->get_param('description');
        if ($store_name === '') {
            return new WP_REST_Response(['message' => 'Nama toko wajib diisi'], 422);
        }
        $existing = get_posts([
            'post_type' => 'mp_vendor_request',
            'post_status' => ['publish', 'pending'],
            'meta_query' => [
                [
                    'key' => '_mp_vendor_user_id',
                    'value' => $uid,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);
        if (!empty($existing)) {
            $rid = (int) $existing[0];
            $status = (string) get_post_meta($rid, '_mp_vendor_status', true);
            if ($status === 'approved') {
                return new WP_REST_Response(['message' => 'Pengajuan sudah disetujui'], 200);
            }
            return new WP_REST_Response(['message' => 'Pengajuan sedang diproses'], 200);
        }
        $pid = wp_insert_post([
            'post_type' => 'mp_vendor_request',
            'post_title' => $store_name,
            'post_content' => $desc,
            'post_status' => 'publish',
            'post_author' => $uid,
        ], true);
        if (is_wp_error($pid)) {
            return new WP_REST_Response(['message' => 'Gagal membuat pengajuan'], 500);
        }
        update_post_meta($pid, '_mp_vendor_user_id', $uid);
        update_post_meta($pid, '_mp_vendor_status', 'pending');
        return new WP_REST_Response(['success' => true, 'id' => $pid], 201);
    }
}
