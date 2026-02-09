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
        if (!is_user_logged_in()) {
            return;
        }
        if (current_user_can('manage_options')) {
            return;
        }
        $uid = get_current_user_id();
        $user = get_userdata($uid);
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
        if (current_user_can('manage_options')) {
            return;
        }
        $uid = get_current_user_id();
        $user = get_userdata($uid);
        $is_vendor = ($user && in_array('store_vendor', (array) $user->roles, true));
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
        <div x-show="tab === 'vendor'" class="wps-card" id="mp-vendor-panel">
            <div class="wps-p-6 wps-pb-0 border-b border-gray-200">
                <h2 class="wps-text-lg wps-font-medium wps-text-gray-900"><?php echo $is_vendor ? 'Dashboard Vendor' : 'Pengajuan Vendor'; ?></h2>
                <p class="wps-mt-1 wps-text-sm wps-text-gray-500">
                    <?php echo $is_vendor ? 'Kelola produk dan toko Anda di bawah ini.' : 'Ajukan untuk membuka toko dan menjual produk Anda.'; ?>
                </p>
            </div>
            <div class="wps-p-6">
                <?php if ($is_vendor) : ?>
                    <div x-data="mpVendorPanel()" x-init="init()" class="wps-grid wps-grid-cols-1 wps-md-grid-cols-4 wps-gap-4">
                        <div class="wps-card">
                            <div class="wps-p-4">
                                <div class="wps-text-sm wps-text-gray-500 wps-mb-2">Menu</div>
                                <div class="wps-flex wps-flex-col wps-gap-2">
                                    <button type="button" class="wps-btn" :class="{'wps-btn-secondary': vtab !== 'toko', 'wps-btn-primary': vtab === 'toko'}" @click="vtab='toko'">Toko</button>
                                    <button type="button" class="wps-btn" :class="{'wps-btn-secondary': vtab !== 'produk', 'wps-btn-primary': vtab === 'produk'}" @click="vtab='produk'">Produk</button>
                                    <button type="button" class="wps-btn" :class="{'wps-btn-secondary': vtab !== 'orders', 'wps-btn-primary': vtab === 'orders'}" @click="vtab='orders'">Order</button>
                                </div>
                            </div>
                        </div>
                        <div class="wps-card wps-md-col-span-3">
                            <div class="wps-p-4">
                                <template x-if="vtab==='toko'">
                                    <div>
                                        <div class="wps-text-sm wps-font-medium wps-mb-2">Profil Toko</div>
                                        <div class="wps-text-sm wps-text-gray-600">Pengaturan toko akan ditambahkan di sini.</div>
                                    </div>
                                </template>
                                <template x-if="vtab==='produk'">
                                    <div>
                                        <?php echo do_shortcode('[wp_store_mp_vendor_dashboard]'); ?>
                                    </div>
                                </template>
                                <template x-if="vtab==='orders'">
                                    <div>
                                        <div class="wps-text-sm wps-font-medium wps-mb-2">Order</div>
                                        <div class="wps-text-sm wps-text-gray-600">Daftar pesanan akan ditampilkan di sini.</div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <script>
                        function mpVendorPanel(){
                            return {
                                vtab: 'produk',
                                init(){
                                    const urlParams = new URLSearchParams(window.location.search);
                                    const v = urlParams.get('vtab');
                                    if (v && ['toko','produk','orders'].includes(v)) {
                                        this.vtab = v;
                                    }
                                    this.$watch('vtab', (val) => {
                                        const url = new URL(window.location);
                                        url.searchParams.set('vtab', val);
                                        window.history.replaceState({}, '', url);
                                    });
                                }
                            }
                        }
                    </script>
                <?php elseif ($has_pending) : ?>
                    <div class="wps-alert wps-alert-info">Pengajuan Anda sedang diproses.</div>
                <?php else : ?>
                    <div id="mp-vendor-toast" style="display:none;position:fixed;bottom:30px;right:30px;padding:12px 16px;background:#fff;box-shadow:0 3px 10px rgba(0,0,0,.1);border-left:4px solid #46b450;border-radius:4px;z-index:9999;">
                        <span class="wps-text-sm wps-text-gray-900" id="mp-vendor-toast-msg"></span>
                    </div>
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
                    <div id="mp-vendor-pending" class="wps-alert wps-alert-info" style="display:none;">Pengajuan Anda sedang diproses.</div>
                    <script>
                        (function() {
                            var form = document.getElementById('mp-vendor-apply-form');
                            var toast = document.getElementById('mp-vendor-toast');
                            var toastMsg = document.getElementById('mp-vendor-toast-msg');
                            var pendingBox = document.getElementById('mp-vendor-pending');
                            if (!form) return;

                            function showToast(msg, type) {
                                if (!toast || !toastMsg) return;
                                toastMsg.textContent = msg || '';
                                var color = (type === 'error') ? '#d63638' : '#46b450';
                                toast.style.borderLeftColor = color;
                                toast.style.display = 'block';
                                setTimeout(function() {
                                    toast.style.display = 'none';
                                }, 2000);
                            }
                            form.addEventListener('submit', function(e) {
                                e.preventDefault();
                                var data = new FormData(form);
                                var body = {
                                    store_name: data.get('store_name') || '',
                                    description: data.get('description') || ''
                                };
                                fetch('<?php echo esc_url_raw(rest_url('wp-store-mp/v1/vendor/apply')); ?>', {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-WP-Nonce': '<?php echo esc_js($nonce); ?>'
                                    },
                                    body: JSON.stringify(body)
                                }).then(function(r) {
                                    return r.json().then(function(d) {
                                        return {
                                            ok: r.ok,
                                            data: d
                                        };
                                    });
                                }).then(function(res) {
                                    if (res && res.ok && res.data && res.data.success) {
                                        showToast('Pengajuan terkirim', 'success');
                                        if (form) form.style.display = 'none';
                                        if (pendingBox) pendingBox.style.display = 'block';
                                    } else {
                                        var msg = (res && res.data && res.data.message) ? res.data.message : 'Gagal mengirim pengajuan';
                                        showToast(msg, 'error');
                                    }
                                }).catch(function() {
                                    showToast('Gagal mengirim pengajuan', 'error');
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
