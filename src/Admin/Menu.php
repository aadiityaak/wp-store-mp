<?php

namespace WpStoreMp\Admin;

class Menu
{
    public function register()
    {
        add_action('admin_menu', [$this, 'add_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_post_mp_vendor_approve', [$this, 'handle_approve']);
    }

    public function add_menus()
    {
        add_menu_page('WP Store MP', 'WP Store MP', 'manage_options', 'wp-store-mp', [$this, 'render_dashboard'], 'dashicons-store', 58);
        add_submenu_page('wp-store-mp', 'Pengajuan Vendor', 'Pengajuan Vendor', 'manage_options', 'wp-store-mp-requests', [$this, 'render_requests']);
    }

    public function enqueue()
    {
        $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
        if (($page === 'wp-store-mp' || $page === 'wp-store-mp-requests') && defined('WP_STORE_URL') && defined('WP_STORE_VERSION')) {
            wp_enqueue_style('wp-store-admin', WP_STORE_URL . 'assets/admin/css/admin.css', [], WP_STORE_VERSION);
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
        echo '<div class="wrap wp-store-wrapper">';
        echo '<div class="wp-store-header"><div>';
        echo '<h1 class="wp-store-title">WP Store MP</h1>';
        echo '<p class="wp-store-helper">Ringkasan marketplace</p>';
        echo '</div></div>';
        echo '<div class="wp-store-card">';
        echo '<div class="wp-store-dashboard-grid">';
        echo '<div class="wp-store-card"><div class="wp-store-card-title">Pending Requests</div><div class="wp-store-card-value">' . esc_html($pending_count) . '</div><div class="wp-store-card-desc">Menunggu persetujuan</div></div>';
        echo '<div class="wp-store-card"><div class="wp-store-card-title">Approved Requests</div><div class="wp-store-card-value">' . esc_html($approved_count) . '</div><div class="wp-store-card-desc">Sudah disetujui</div></div>';
        echo '<div class="wp-store-card"><div class="wp-store-card-title">Active Vendors</div><div class="wp-store-card-value">' . esc_html($vendors_count) . '</div><div class="wp-store-card-desc">Vendor aktif</div></div>';
        echo '</div>';
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
        echo '<div class="wrap wp-store-wrapper">';
        echo '<div class="wp-store-header"><div>';
        echo '<h1 class="wp-store-title">Pengajuan Vendor</h1>';
        echo '<p class="wp-store-helper">Tinjau dan setujui pengajuan vendor</p>';
        echo '</div></div>';
        $nonce = wp_create_nonce('wp_rest');
        $rest_base = esc_url_raw(rest_url('wp-store-mp/v1/vendor/requests/'));
        if ($q->have_posts()) {
            echo '<div class="wp-store-card wp-store-p-0">';
            echo '<div class="wp-store-table-wrapper">';
            echo '<table class="wp-store-table"><thead><tr><th>Judul</th><th>User</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
            while ($q->have_posts()) {
                $q->the_post();
                $pid = get_the_ID();
                $uid = (int) get_post_meta($pid, '_mp_vendor_user_id', true);
                $status = (string) get_post_meta($pid, '_mp_vendor_status', true) ?: 'pending';
                $user = $uid ? get_userdata($uid) : null;
                $uname = $user ? $user->user_login : '-';
                echo '<tr>';
                echo '<td><a href="' . esc_url(get_edit_post_link($pid)) . '">' . esc_html(get_the_title()) . '</a></td>';
                echo '<td>' . esc_html($uname) . '</td>';
                echo '<td class="js-status" data-id="' . esc_attr($pid) . '">' . esc_html(ucfirst($status)) . '</td>';
                echo '<td>';
                if ($status !== 'approved') {
                    echo '<button type="button" class="wp-store-btn wp-store-btn-primary js-approve-vendor" data-id="' . esc_attr($pid) . '">Terima</button>';
                } else {
                    echo '<span class="wp-store-btn wp-store-btn-secondary">Sudah diterima</span>';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
            echo '<script>(function(){';
            echo 'var nonce="' . esc_js($nonce) . '";';
            echo 'var base="' . esc_js($rest_base) . '";';
            echo 'function approve(id, btn){';
            echo '  fetch(base + id + "/approve",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":nonce}}).then(function(r){return r.json().then(function(d){return{ok:r.ok,data:d};});}).then(function(res){';
            echo '    if(res.ok&&res.data&&res.data.success){';
            echo "      var st=document.querySelector('td.js-status[data-id=\"'+id+'\"]');";
            echo '      if(st){st.textContent="Approved";}';
            echo '      if(btn){btn.outerHTML=\'<span class="wp-store-btn wp-store-btn-secondary">Sudah diterima</span>\';}';
            echo '    }else{alert((res.data&&res.data.message)||"Gagal menyetujui");}';
            echo '  }).catch(function(){alert("Gagal menyetujui");});';
            echo '}';
            echo 'document.addEventListener("click",function(e){var t=e.target; if(t&&t.classList&&t.classList.contains("js-approve-vendor")){e.preventDefault(); var id=t.getAttribute("data-id"); if(id){approve(id,t);} }});';
            echo '})();</script>';
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<div class="wp-store-card" style="padding:16px;margin-top:16px;"><div class="wp-store-helper">Tidak ada pengajuan.</div></div>';
        }
        echo '</div>';
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
