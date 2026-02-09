<?php

namespace WpStoreMp\Frontend;

class Shortcode
{
    public function register()
    {
        add_shortcode('wp_store_mp_vendor_dashboard', [$this, 'render_vendor_dashboard']);
        add_shortcode('wp_store_mp_store', [$this, 'render_vendor_store']);
    }

    public function render_vendor_dashboard($atts = [])
    {
        if (!is_user_logged_in()) {
            return '<div class="wps-text-sm wps-text-gray-700">Silakan login untuk mengelola produk.</div>';
        }
        wp_enqueue_script('alpinejs');
        wp_enqueue_script('wp-store-frontend');
        wp_enqueue_style('wp-store-frontend-css');
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        ?>
        <div class="wps-container wps-mx-auto wps-my-8" x-data="wpStoreMpDashboard()">
            <div class="wps-text-lg wps-font-medium wps-text-gray-900 wps-mb-4">Dashboard Vendor</div>
            <div class="wps-card wps-mb-4">
                <div class="wps-p-4">
                    <div class="wps-text-sm wps-font-medium wps-mb-2">Produk Saya</div>
                    <template x-if="items.length === 0">
                        <div class="wps-text-sm wps-text-gray-500">Belum ada produk.</div>
                    </template>
                    <div class="wps-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));" x-show="items.length > 0">
                        <template x-for="it in items" :key="it.id">
                            <a class="wps-card" :href="it.link">
                                <div class="wps-p-2">
                                    <img class="wps-rounded" :src="it.image || '<?php echo esc_js(WP_STORE_URL . 'assets/frontend/img/noimg.webp'); ?>'" :alt="it.title" style="width:100%; aspect-ratio: 1 / 1; object-fit: cover;">
                                    <div class="wps-text-sm wps-text-gray-900 wps-font-medium" x-text="it.title"></div>
                                </div>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
            <div class="wps-card">
                <div class="wps-p-4">
                    <div class="wps-text-sm wps-font-medium wps-mb-2">Tambah Produk</div>
                    <form @submit.prevent="submit">
                        <div class="wps-grid wps-grid-cols-1 wps-md-grid-cols-2 wps-gap-4">
                            <div>
                                <div class="wps-form-group">
                                    <label class="wps-form-label">Judul</label>
                                    <input type="text" class="wps-form-input" x-model="form.title" required>
                                </div>
                                <div class="wps-form-group">
                                    <label class="wps-form-label">Deskripsi</label>
                                    <textarea class="wps-form-textarea" x-model="form.content"></textarea>
                                </div>
                                <div class="wps-grid wps-grid-cols-2 wps-gap-4">
                                    <div class="wps-form-group">
                                        <label class="wps-form-label">Harga</label>
                                        <input type="number" step="0.01" min="0" class="wps-form-input" x-model.number="form.price">
                                    </div>
                                    <div class="wps-form-group">
                                        <label class="wps-form-label">Stok</label>
                                        <input type="number" step="1" min="0" class="wps-form-input" x-model.number="form.stock">
                                    </div>
                                </div>
                                <div class="wps-form-group">
                                    <label class="wps-form-label">Status</label>
                                    <select class="wps-form-input" x-model="form.status">
                                        <option value="draft">Draft</option>
                                        <option value="pending">Pending</option>
                                        <option value="publish">Publish</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <div class="wps-form-group">
                                    <label class="wps-form-label">Gambar (Attachment ID)</label>
                                    <input type="number" min="0" class="wps-form-input" x-model.number="form.image_id">
                                </div>
                                <div class="wps-form-group">
                                    <label class="wps-form-label">Kategori (ID, koma)</label>
                                    <input type="text" class="wps-form-input" x-model="form.categories_raw" placeholder="cth: 12,34">
                                </div>
                            </div>
                        </div>
                        <div class="wps-mt-4">
                            <button type="submit" class="wps-btn wps-btn-primary" :disabled="loading">
                                <span x-show="!loading">Simpan</span>
                                <span x-show="loading">Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
        function wpStoreMpDashboard() {
            return {
                items: [],
                loading: false,
                form: {
                    title: '',
                    content: '',
                    price: '',
                    stock: '',
                    status: 'draft',
                    image_id: '',
                    categories_raw: ''
                },
                init() {
                    this.fetchItems();
                },
                fetchItems() {
                    fetch('<?php echo esc_url_raw(rest_url('wp-store-mp/v1/products')); ?>', {
                        headers: {'X-WP-Nonce': '<?php echo esc_js($nonce); ?>'}
                    }).then(r => r.json()).then(d => {
                        this.items = Array.isArray(d.items) ? d.items : [];
                    });
                },
                submit() {
                    this.loading = true;
                    const cats = this.form.categories_raw.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n));
                    const body = {
                        title: this.form.title,
                        content: this.form.content,
                        price: this.form.price,
                        stock: this.form.stock,
                        status: this.form.status,
                        image_id: this.form.image_id,
                        categories: cats
                    };
                    fetch('<?php echo esc_url_raw(rest_url('wp-store-mp/v1/products')); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': '<?php echo esc_js($nonce); ?>'
                        },
                        body: JSON.stringify(body)
                    }).then(r => r.json()).then(d => {
                        this.loading = false;
                        if (d && d.success) {
                            this.form.title = '';
                            this.form.content = '';
                            this.form.price = '';
                            this.form.stock = '';
                            this.form.status = 'draft';
                            this.form.image_id = '';
                            this.form.categories_raw = '';
                            this.fetchItems();
                        } else {
                            alert(d && d.message ? d.message : 'Gagal menyimpan');
                        }
                    }).catch(() => {
                        this.loading = false;
                        alert('Gagal menyimpan');
                    });
                }
            }
        }
        </script>
        <?php
        return ob_get_clean();
    }

    public function render_vendor_store($atts = [])
    {
        wp_enqueue_script('wp-store-frontend');
        wp_enqueue_style('wp-store-frontend-css');
        $atts = shortcode_atts([
            'user_id' => get_current_user_id(),
            'per_page' => 12,
            'page' => 1,
        ], $atts);
        $uid = (int) $atts['user_id'];
        $per_page = (int) $atts['per_page'];
        $paged = (int) $atts['page'];
        if ($uid <= 0) {
            return '';
        }
        $args = [
            'post_type' => 'store_product',
            'posts_per_page' => $per_page,
            'paged' => $paged,
            'post_status' => 'publish',
            'author' => $uid,
        ];
        $q = new \WP_Query($args);
        $items = [];
        if ($q->have_posts()) {
            while ($q->have_posts()) {
                $q->the_post();
                $id = get_the_ID();
                $price = get_post_meta($id, '_store_price', true);
                $image = get_the_post_thumbnail_url($id, 'medium');
                $items[] = [
                    'id' => $id,
                    'title' => get_the_title(),
                    'link' => get_permalink(),
                    'image' => $image ? $image : null,
                    'price' => $price !== '' ? (float) $price : null,
                ];
            }
            wp_reset_postdata();
        }
        ob_start();
        ?>
        <div class="wps-container wps-mx-auto wps-my-8">
            <div class="wps-text-lg wps-font-medium wps-text-gray-900 wps-mb-4">Toko</div>
            <?php if (!empty($items)) : ?>
                <div class="wps-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <?php foreach ($items as $item) : ?>
                        <a class="wps-card" href="<?php echo esc_url($item['link']); ?>">
                            <div class="wps-p-2">
                                <img class="wps-rounded" src="<?php echo esc_url($item['image'] ?: (WP_STORE_URL . 'assets/frontend/img/noimg.webp')); ?>" alt="<?php echo esc_attr($item['title']); ?>" style="width:100%; aspect-ratio: 1 / 1; object-fit: cover;">
                                <div class="wps-text-sm wps-text-gray-900 wps-font-medium"><?php echo esc_html($item['title']); ?></div>
                                <div class="wps-mt-1"><?php echo do_shortcode('[wp_store_price id="' . esc_attr($item['id']) . '"]'); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="wps-text-sm wps-text-gray-500">Belum ada produk.</div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
