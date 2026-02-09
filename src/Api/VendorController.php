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
        register_rest_route('wp-store-mp/v1', '/vendor/requests', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_requests'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ],
        ]);
        register_rest_route('wp-store-mp/v1', '/vendor/requests/(?P<id>\d+)/approve', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'approve_request'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
                'args' => [
                    'id' => [
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
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

    public function get_requests(WP_REST_Request $request)
    {
        $per_page = (int) $request->get_param('per_page');
        if ($per_page <= 0 || $per_page > 100) $per_page = 20;
        $paged = (int) $request->get_param('page');
        if ($paged <= 0) $paged = 1;
        $q = new \WP_Query([
            'post_type' => 'mp_vendor_request',
            'post_status' => ['publish', 'pending'],
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $items = [];
        foreach ($q->posts as $p) {
            $pid = $p->ID;
            $uid = (int) get_post_meta($pid, '_mp_vendor_user_id', true);
            $status = (string) get_post_meta($pid, '_mp_vendor_status', true) ?: 'pending';
            $user = $uid ? get_userdata($uid) : null;
            $items[] = [
                'id' => $pid,
                'title' => get_the_title($pid),
                'user_id' => $uid,
                'user_login' => $user ? $user->user_login : '',
                'status' => $status,
                'date' => get_the_date('d M Y', $pid),
            ];
        }
        return new WP_REST_Response([
            'items' => $items,
            'total' => (int) $q->found_posts,
            'pages' => (int) $q->max_num_pages,
            'page' => $paged,
        ], 200);
    }

    public function approve_request(WP_REST_Request $request)
    {
        $rid = (int) $request['id'];
        if ($rid <= 0) {
            return new WP_REST_Response(['message' => 'ID tidak valid'], 422);
        }
        $post = get_post($rid);
        if (!$post || $post->post_type !== 'mp_vendor_request') {
            return new WP_REST_Response(['message' => 'Pengajuan tidak ditemukan'], 404);
        }
        $uid = (int) get_post_meta($rid, '_mp_vendor_user_id', true);
        if ($uid > 0) {
            $u = get_userdata($uid);
            if ($u) {
                $u->add_role('store_vendor');
            }
        }
        update_post_meta($rid, '_mp_vendor_status', 'approved');
        return new WP_REST_Response(['success' => true], 200);
    }
}
