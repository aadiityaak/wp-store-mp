<?php

namespace WpStoreMp\Frontend;

class ProfileHooks
{
    public function register()
    {
        add_action('wp_store_profile_additional_tabs', [$this, 'render_vendor_tab_button']);
        add_action('wp_store_profile_additional_panels', [$this, 'render_vendor_tab_panel']);
    }

    public function render_vendor_tab_button()
    {
?>
        <button @click="tab = 'vendor'" :class="{ 'active': tab === 'vendor' }" class="wps-tab">
            <?php echo \wps_icon(['name' => 'store', 'size' => 16, 'class' => 'wps-mr-2']); ?>Vendor
        </button>
    <?php
    }

    public function render_vendor_tab_panel()
    {
        if (!is_user_logged_in()) {
            return;
        }
        $uid = get_current_user_id();
        $user = get_userdata($uid);
        if ($user && in_array('store_vendor', (array) $user->roles, true)) {
            return;
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
        $has_pending = false;
        if (!empty($existing)) {
            $rid = (int) $existing[0];
            $status = (string) get_post_meta($rid, '_mp_vendor_status', true);
            $has_pending = ($status !== 'approved');
        }
        $nonce = wp_create_nonce('wp_rest');
    ?>
        <div x-show="tab === 'vendor'" class="wps-card">
            <div class="wps-p-6 wps-pb-0 border-b border-gray-200">
                <h2 class="wps-text-lg wps-font-medium wps-text-gray-900">Pengajuan Vendor</h2>
                <p class="wps-mt-1 wps-text-sm wps-text-gray-500">Ajukan untuk membuka toko dan menjual produk Anda.</p>
            </div>
            <div class="wps-p-6">
                <?php if ($has_pending) : ?>
                    <div class="wps-alert wps-alert-info">Pengajuan Anda sedang diproses.</div>
                <?php else : ?>
                    <form id="mp-vendor-apply-form">
                        <div class="wps-form-group">
                            <label class="wps-label">Nama Toko</label>
                            <input type="text" name="store_name" class="wps-input" required>
                        </div>
                        <div class="wps-form-group">
                            <label class="wps-label">Deskripsi</label>
                            <textarea name="description" class="wps-input"></textarea>
                        </div>
                        <div class="wps-flex" style="justify-content: flex-end; margin-top: 1rem;">
                            <button type="submit" class="wps-btn wps-btn-primary">Ajukan</button>
                        </div>
                    </form>
                    <script>
                        (function() {
                            var form = document.getElementById('mp-vendor-apply-form');
                            if (!form) return;
                            form.addEventListener('submit', function(e) {
                                e.preventDefault();
                                var data = new FormData(form);
                                var body = {
                                    store_name: data.get('store_name') || '',
                                    description: data.get('description') || ''
                                };
                                fetch('<?php echo esc_url_raw(rest_url('wp-store-mp/v1/vendor/apply')); ?>', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-WP-Nonce': '<?php echo esc_js($nonce); ?>'
                                    },
                                    body: JSON.stringify(body)
                                }).then(function(r) {
                                    return r.json();
                                }).then(function(d) {
                                    if (d && d.success) {
                                        alert('Pengajuan terkirim');
                                        window.location.reload();
                                    } else {
                                        alert(d && d.message ? d.message : 'Gagal mengirim pengajuan');
                                    }
                                }).catch(function() {
                                    alert('Gagal mengirim pengajuan');
                                });
                            });
                        })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
<?php
    }
}
