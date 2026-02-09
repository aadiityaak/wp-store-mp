<?php

namespace WpStoreMp\Admin;

class Menu
{
    public function register()
    {
        add_action('admin_menu', [$this, 'add_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function add_menus()
    {
        add_menu_page('WP Store MP', 'WP Store MP', 'manage_options', 'wp-store-mp', [$this, 'render_dashboard'], 'dashicons-store', 58);
        add_submenu_page('wp-store-mp', 'Pengajuan Vendor', 'Pengajuan Vendor', 'manage_options', 'wp-store-mp-requests', [$this, 'render_requests']);
    }

    public function enqueue()
    {
        $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
        if ($page === 'wp-store-mp' || $page === 'wp-store-mp-requests') {
            if (defined('WP_STORE_URL') && defined('WP_STORE_VERSION')) {
                wp_enqueue_style('wp-store-frontend-css', WP_STORE_URL . 'assets/frontend/css/style.css', [], WP_STORE_VERSION);
            }
            $css = '.wps-container{max-width:1100px;margin-left:auto;margin-right:auto;}';
            wp_add_inline_style('wp-store-frontend-css', $css);
        }
    }

    public function render_dashboard()
    {
        $pending = new \WP_Query([
            'post_type' => 'mp_vendor_request',
            'post_status' => ['publish', 'pending'],
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_mp_vendor_status',
                    'value' => 'pending',
                    'compare' => '='
                ]
            ]
        ]);
        $approved = new \WP_Query([
            'post_type' => 'mp_vendor_request',
            'post_status' => ['publish', 'pending'],
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_mp_vendor_status',
                    'value' => 'approved',
                    'compare' => '='
                ]
            ]
        ]);
        $vendors = get_users(['role' => 'store_vendor', 'fields' => ['ID']]);
        $pending_count = (int) $pending->found_posts;
        $approved_count = (int) $approved->found_posts;
        $vendors_count = is_array($vendors) ? count($vendors) : 0;
        wp_reset_postdata();
        echo '<div class="wrap"><div class="wps-container wps-my-6">';
        echo '<div class="wps-text-2xl wps-font-bold wps-text-gray-900">WP Store MP</div>';
        echo '<div class="wps-text-sm wps-text-gray-600 wps-mt-1">Ringkasan marketplace</div>';
        echo '<div class="wps-grid wps-grid-cols-1 wps-md-grid-cols-3 wps-gap-4 wps-mt-4">';
        echo '<div class="wps-card"><div class="wps-p-4"><div class="wps-text-xs wps-text-gray-500">Pending Requests</div><div class="wps-text-2xl wps-font-semibold wps-text-gray-900">' . esc_html($pending_count) . '</div></div></div>';
        echo '<div class="wps-card"><div class="wps-p-4"><div class="wps-text-xs wps-text-gray-500">Approved Requests</div><div class="wps-text-2xl wps-font-semibold wps-text-gray-900">' . esc_html($approved_count) . '</div></div></div>';
        echo '<div class="wps-card"><div class="wps-p-4"><div class="wps-text-xs wps-text-gray-500">Active Vendors</div><div class="wps-text-2xl wps-font-semibold wps-text-gray-900">' . esc_html($vendors_count) . '</div></div></div>';
        echo '</div>';
        echo '</div></div>';
    }

    public function render_requests()
    {
        $q = new \WP_Query([
            'post_type' => 'mp_vendor_request',
            'post_status' => ['publish', 'pending'],
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        echo '<div class="wrap"><div class="wps-container wps-my-6">';
        echo '<div class="wps-text-xl wps-font-bold wps-text-gray-900">Pengajuan Vendor</div>';
        if ($q->have_posts()) {
            echo '<div class="wps-card wps-mt-4"><div class="wps-p-0">';
            echo '<table class="wps-table"><thead><tr><th>Judul</th><th>User</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
            while ($q->have_posts()) {
                $q->the_post();
                $pid = get_the_ID();
                $uid = (int) get_post_meta($pid, '_mp_vendor_user_id', true);
                $status = (string) get_post_meta($pid, '_mp_vendor_status', true) ?: 'pending';
                $user = $uid ? get_userdata($uid) : null;
                $uname = $user ? $user->user_login : '-';
                $approve_url = wp_nonce_url(admin_url('admin-post.php?action=mp_vendor_approve&rid=' . $pid), 'mp_vendor_approve_' . $pid);
                echo '<tr>';
                echo '<td><a class="wps-text-blue-600" href="' . esc_url(get_edit_post_link($pid)) . '">' . esc_html(get_the_title()) . '</a></td>';
                echo '<td>' . esc_html($uname) . '</td>';
                echo '<td>' . esc_html(ucfirst($status)) . '</td>';
                echo '<td>';
                if ($status !== 'approved') {
                    echo '<a class="wps-btn wps-btn-primary" href="' . esc_url($approve_url) . '">Terima</a>';
                } else {
                    echo '<span class="wps-btn wps-btn-secondary">Sudah diterima</span>';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div></div>';
            wp_reset_postdata();
        } else {
            echo '<div class="wps-card wps-p-4 wps-mt-4"><div class="wps-text-sm wps-text-gray-500">Tidak ada pengajuan.</div></div>';
        }
        echo '</div></div>';
        add_action('admin_post_mp_vendor_approve', [$this, 'handle_approve']);
    }

    public function handle_approve()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        $rid = isset($_GET['rid']) ? (int) $_GET['rid'] : 0;
        if ($rid <= 0) {
            wp_die('ID tidak valid');
        }
        check_admin_referer('mp_vendor_approve_' . $rid);
        $uid = (int) get_post_meta($rid, '_mp_vendor_user_id', true);
        if ($uid > 0) {
            $u = get_userdata($uid);
            if ($u) {
                $u->add_role('store_vendor');
            }
        }
        update_post_meta($rid, '_mp_vendor_status', 'approved');
        wp_redirect(admin_url('admin.php?page=wp-store-mp-requests'));
        exit;
    }
}
