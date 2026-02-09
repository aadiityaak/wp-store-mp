<?php

namespace WpStoreMp\Admin;

class Menu
{
    public function register()
    {
        add_action('admin_menu', [$this, 'add_menus']);
    }

    public function add_menus()
    {
        add_menu_page('WP Store MP', 'WP Store MP', 'manage_options', 'wp-store-mp', [$this, 'render_dashboard'], 'dashicons-store', 58);
        add_submenu_page('wp-store-mp', 'Pengajuan Vendor', 'Pengajuan Vendor', 'manage_options', 'wp-store-mp-requests', [$this, 'render_requests']);
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
        echo '<div class="wrap"><h1>WP Store MP</h1>';
        echo '<p>Ringkasan marketplace:</p>';
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;">';
        echo '<div style="padding:12px 16px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;min-width:180px;"><div style="font-size:12px;color:#6b7280;">Pending Requests</div><div style="font-size:20px;font-weight:600;color:#111827;">' . esc_html($pending_count) . '</div></div>';
        echo '<div style="padding:12px 16px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;min-width:180px;"><div style="font-size:12px;color:#6b7280;">Approved Requests</div><div style="font-size:20px;font-weight:600;color:#111827;">' . esc_html($approved_count) . '</div></div>';
        echo '<div style="padding:12px 16px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;min-width:180px;"><div style="font-size:12px;color:#6b7280;">Active Vendors</div><div style="font-size:20px;font-weight:600;color:#111827;">' . esc_html($vendors_count) . '</div></div>';
        echo '</div>';
        echo '</div>';
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
        echo '<div class="wrap"><h1>Pengajuan Vendor</h1>';
        if ($q->have_posts()) {
            echo '<table class="widefat"><thead><tr><th>Judul</th><th>User</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
            while ($q->have_posts()) {
                $q->the_post();
                $pid = get_the_ID();
                $uid = (int) get_post_meta($pid, '_mp_vendor_user_id', true);
                $status = (string) get_post_meta($pid, '_mp_vendor_status', true) ?: 'pending';
                $user = $uid ? get_userdata($uid) : null;
                $uname = $user ? $user->user_login : '-';
                $approve_url = wp_nonce_url(admin_url('admin-post.php?action=mp_vendor_approve&rid=' . $pid), 'mp_vendor_approve_' . $pid);
                echo '<tr>';
                echo '<td><a href="' . esc_url(get_edit_post_link($pid)) . '">' . esc_html(get_the_title()) . '</a></td>';
                echo '<td>' . esc_html($uname) . '</td>';
                echo '<td>' . esc_html(ucfirst($status)) . '</td>';
                echo '<td>';
                if ($status !== 'approved') {
                    echo '<a class="button button-primary" href="' . esc_url($approve_url) . '">Terima</a>';
                } else {
                    echo '<span class="button disabled">Sudah diterima</span>';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            wp_reset_postdata();
        } else {
            echo '<p>Tidak ada pengajuan.</p>';
        }
        echo '</div>';
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
