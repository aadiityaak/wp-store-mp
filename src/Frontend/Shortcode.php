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
        wp_add_inline_style('wp-store-frontend-css', '
            .wps-grid{display:grid;}
            .wps-gap-4{gap:1rem;}
            .wps-grid-cols-1{grid-template-columns:repeat(1,minmax(0,1fr));}
            @media(min-width:768px){.wps-md-grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr));}}
            .wps-card{background:#fff;border:1px solid #e5e7eb;border-radius:.5rem;box-shadow:0 1px 2px rgba(0,0,0,.06);}
            .wps-p-4{padding:1rem;}
            .wps-p-2{padding:.5rem;}
        ');
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
?>
        <div class="wps-container wps-mx-auto wps-my-8" x-data="wpStoreMpDashboard()">
            <div class="wps-text-lg wps-font-medium wps-text-gray-900 wps-mb-4">Produk</div>
            <div class="wps-grid wps-grid-cols-1 wps-md-grid-cols-3 wps-gap-4">
                <div class="wps-card">
                    <div class="wps-p-4">
                        <div class="wps-text-sm wps-font-medium wps-mb-2">Daftar Produk</div>
                        <template x-if="items.length === 0">
                            <div class="wps-text-sm wps-text-gray-500">Belum ada produk.</div>
                        </template>
                        <div class="wps-flex wps-flex-col wps-gap-2" x-show="items.length > 0">
                            <template x-for="it in items" :key="it.id">
                                <div class="wps-card wps-p-2 wps-flex wps-items-center wps-justify-between">
                                    <div class="wps-flex wps-items-center wps-gap-2">
                                        <img class="wps-rounded" :src="it.image || '<?php echo esc_js(WP_STORE_URL . 'assets/frontend/img/noimg.webp'); ?>'" :alt="it.title" style="width:40px;height:40px;object-fit:cover;">
                                        <div>
                                            <div class="wps-text-sm wps-text-gray-900 wps-font-medium" x-text="it.title"></div>
                                            <div class="wps-text-xs wps-text-gray-500">Rp <span x-text="formatCurrency(it.price||0)"></span> • Stok <span x-text="it.stock||0"></span></div>
                                        </div>
                                    </div>
                                    <div class="wps-flex wps-gap-2">
                                        <button class="wps-btn wps-btn-secondary" @click="editItem(it)">Edit</button>
                                        <button class="wps-btn wps-btn-danger" @click="removeItem(it)">Hapus</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="wps-card wps-md-col-span-2">
                    <div class="wps-p-4">
                        <div class="wps-flex wps-justify-between wps-items-center wps-mb-2">
                            <div class="wps-text-sm wps-font-medium" x-text="editingId ? 'Ubah Produk' : 'Tambah Produk'"></div>
                            <div>
                                <button class="wps-btn wps-btn-secondary" @click="resetForm" x-show="editingId">Batal Edit</button>
                            </div>
                        </div>
                        <div class="wps-tabs">
                            <button class="wps-tab" :class="{'active': activeFormTab==='general'}" @click="activeFormTab='general'">General</button>
                            <button class="wps-tab" :class="{'active': activeFormTab==='inventory'}" @click="activeFormTab='inventory'">Inventory</button>
                            <button class="wps-tab" :class="{'active': activeFormTab==='attributes'}" @click="activeFormTab='attributes'">Attributes</button>
                            <button class="wps-tab" :class="{'active': activeFormTab==='gallery'}" @click="activeFormTab='gallery'">Gallery</button>
                        </div>
                        <form @submit.prevent="submit" class="wps-mt-3">
                            <div x-show="activeFormTab==='general'">
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
                                        <div class="wps-form-group">
                                            <label class="wps-form-label">Tipe Produk</label>
                                            <select class="wps-form-input" x-model="form.product_type">
                                                <option value="physical">Produk Fisik</option>
                                                <option value="digital">Produk Digital</option>
                                            </select>
                                        </div>
                                        <div class="wps-grid wps-grid-cols-2 wps-gap-4">
                                            <div class="wps-form-group">
                                                <label class="wps-form-label">Harga</label>
                                                <input type="number" step="0.01" min="0" class="wps-form-input" x-model.number="form.price">
                                            </div>
                                            <div class="wps-form-group">
                                                <label class="wps-form-label">Harga Promo</label>
                                                <input type="number" step="0.01" min="0" class="wps-form-input" x-model.number="form.sale_price">
                                            </div>
                                        </div>
                                        <div class="wps-form-group">
                                            <label class="wps-form-label">Diskon Sampai</label>
                                            <input type="datetime-local" class="wps-form-input" x-model="form.flashsale_until">
                                        </div>
                                        <div class="wps-form-group" x-show="form.product_type==='digital'">
                                            <label class="wps-form-label">File Digital (Attachment ID)</label>
                                            <input type="number" min="0" class="wps-form-input" x-model.number="form.digital_file">
                                        </div>
                                        <div class="wps-grid wps-grid-cols-2 wps-gap-4">
                                            <div class="wps-form-group">
                                                <label class="wps-form-label">Status</label>
                                                <select class="wps-form-input" x-model="form.status">
                                                    <option value="draft">Draft</option>
                                                    <option value="pending">Pending</option>
                                                    <option value="publish">Publish</option>
                                                </select>
                                            </div>
                                            <div class="wps-form-group">
                                                <label class="wps-form-label">Label Produk</label>
                                                <select class="wps-form-input" x-model="form.label">
                                                    <option value="">-</option>
                                                    <option value="label-best">Best Seller</option>
                                                    <option value="label-limited">Limited</option>
                                                    <option value="label-new">New</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="wps-form-group">
                                            <label class="wps-form-label">Gambar Utama (Attachment ID)</label>
                                            <input type="number" min="0" class="wps-form-input" x-model.number="form.image_id">
                                        </div>
                                        <div class="wps-form-group">
                                            <label class="wps-form-label">Kategori (ID, koma)</label>
                                            <input type="text" class="wps-form-input" x-model="form.categories_raw" placeholder="cth: 12,34">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div x-show="activeFormTab==='inventory'">
                                <div class="wps-grid wps-grid-cols-1 wps-md-grid-cols-2 wps-gap-4">
                                    <div class="wps-form-group">
                                        <label class="wps-form-label">SKU</label>
                                        <input type="text" class="wps-form-input" x-model="form.sku">
                                    </div>
                                    <div class="wps-form-group">
                                        <label class="wps-form-label">Stok</label>
                                        <input type="number" step="1" min="0" class="wps-form-input" x-model.number="form.stock">
                                    </div>
                                    <div class="wps-form-group">
                                        <label class="wps-form-label">Minimal Order</label>
                                        <input type="number" step="1" min="1" class="wps-form-input" x-model.number="form.min_order">
                                    </div>
                                    <div class="wps-form-group">
                                        <label class="wps-form-label">Berat (Kg)</label>
                                        <input type="number" step="0.01" min="0" class="wps-form-input" x-model.number="form.weight_kg">
                                    </div>
                                </div>
                            </div>
                            <div x-show="activeFormTab==='attributes'">
                                <div class="wps-form-group">
                                    <label class="wps-form-label">Nama Opsi (Basic)</label>
                                    <input type="text" class="wps-form-input" x-model="form.option_name" placeholder="Contoh: Pilih Warna">
                                </div>
                                <div class="wps-form-group">
                                    <label class="wps-form-label">Opsi Basic</label>
                                    <div class="wps-flex wps-flex-col wps-gap-2">
                                        <template x-for="(opt, idx) in form.options" :key="idx">
                                            <div class="wps-flex wps-gap-2">
                                                <input type="text" class="wps-form-input" x-model="form.options[idx]" placeholder="Contoh: merah">
                                                <button type="button" class="wps-btn wps-btn-danger" @click="form.options.splice(idx,1)">Hapus</button>
                                            </div>
                                        </template>
                                        <button type="button" class="wps-btn wps-btn-secondary" @click="form.options.push('')">Tambah Opsi</button>
                                    </div>
                                </div>
                                <div class="wps-form-group">
                                    <label class="wps-form-label">Nama Opsi (Advance)</label>
                                    <input type="text" class="wps-form-input" x-model="form.option2_name" placeholder="Contoh: Pilih Ukuran">
                                </div>
                                <div class="wps-form-group">
                                    <label class="wps-form-label">Opsi Advance</label>
                                    <div class="wps-flex wps-flex-col wps-gap-2">
                                        <template x-for="(g, i) in form.advanced_options" :key="i">
                                            <div class="wps-grid wps-grid-cols-1 wps-md-grid-cols-3 wps-gap-2">
                                                <input type="text" class="wps-form-input" x-model="form.advanced_options[i].label" placeholder="Label">
                                                <input type="number" step="0.01" min="0" class="wps-form-input" x-model.number="form.advanced_options[i].price" placeholder="Harga">
                                                <button type="button" class="wps-btn wps-btn-danger" @click="form.advanced_options.splice(i,1)">Hapus</button>
                                            </div>
                                        </template>
                                        <button type="button" class="wps-btn wps-btn-secondary" @click="form.advanced_options.push({label:'',price:''})">Tambah Opsi</button>
                                    </div>
                                </div>
                            </div>
                            <div x-show="activeFormTab==='gallery'">
                                <div class="wps-form-group">
                                    <label class="wps-form-label">Gallery IDs (koma)</label>
                                    <input type="text" class="wps-form-input" x-model="form.gallery_ids_raw" placeholder="cth: 101,102,103">
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
        </div>
        <script>
            function wpStoreMpDashboard() {
                return {
                    items: [],
                    loading: false,
                    editingId: null,
                    activeFormTab: 'general',
                    form: {
                        title: '',
                        content: '',
                        product_type: 'physical',
                        price: '',
                        sale_price: '',
                        flashsale_until: '',
                        digital_file: '',
                        label: '',
                        stock: '',
                        sku: '',
                        min_order: '',
                        weight_kg: '',
                        status: 'draft',
                        image_id: '',
                        option_name: '',
                        options: [],
                        option2_name: '',
                        advanced_options: [],
                        gallery_ids_raw: '',
                        categories_raw: ''
                    },
                    init() {
                        this.fetchItems();
                    },
                    fetchItems() {
                        fetch('<?php echo esc_url_raw(rest_url('wp-store-mp/v1/products')); ?>', {
                            headers: {
                                'X-WP-Nonce': '<?php echo esc_js($nonce); ?>'
                            }
                        }).then(r => r.json()).then(d => {
                            this.items = Array.isArray(d.items) ? d.items : [];
                        });
                    },
                    editItem(it) {
                        this.editingId = it.id;
                        this.activeFormTab = 'general';
                        this.form.title = it.title || '';
                        this.form.content = '';
                        this.form.status = it.status || 'draft';
                        this.form.image_id = '';
                        this.form.categories_raw = '';
                        this.form.product_type = 'physical';
                        this.form.price = it.price || '';
                        this.form.sale_price = '';
                        this.form.flashsale_until = '';
                        this.form.digital_file = '';
                        this.form.label = '';
                        this.form.stock = it.stock || '';
                        this.form.sku = '';
                        this.form.min_order = '';
                        this.form.weight_kg = '';
                        this.form.option_name = '';
                        this.form.options = [];
                        this.form.option2_name = '';
                        this.form.advanced_options = [];
                        this.form.gallery_ids_raw = '';
                    },
                    resetForm() {
                        this.editingId = null;
                        this.activeFormTab = 'general';
                        this.form = {
                            title: '',
                            content: '',
                            product_type: 'physical',
                            price: '',
                            sale_price: '',
                            flashsale_until: '',
                            digital_file: '',
                            label: '',
                            stock: '',
                            sku: '',
                            min_order: '',
                            weight_kg: '',
                            status: 'draft',
                            image_id: '',
                            option_name: '',
                            options: [],
                            option2_name: '',
                            advanced_options: [],
                            gallery_ids_raw: '',
                            categories_raw: ''
                        };
                    },
                    submit() {
                        this.loading = true;
                        const cats = this.form.categories_raw.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n));
                        const galleryIds = this.form.gallery_ids_raw.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n));
                        const body = {
                            title: this.form.title,
                            content: this.form.content,
                            status: this.form.status,
                            image_id: this.form.image_id,
                            categories: cats,
                            product_type: this.form.product_type,
                            price: this.form.price,
                            sale_price: this.form.sale_price,
                            flashsale_until: this.form.flashsale_until,
                            digital_file: this.form.digital_file,
                            label: this.form.label,
                            stock: this.form.stock,
                            sku: this.form.sku,
                            min_order: this.form.min_order,
                            weight_kg: this.form.weight_kg,
                            option_name: this.form.option_name,
                            options: this.form.options,
                            option2_name: this.form.option2_name,
                            advanced_options: this.form.advanced_options,
                            gallery_ids: galleryIds
                        };
                        const url = this.editingId ?
                            '<?php echo esc_url_raw(rest_url('wp-store-mp/v1/products/')); ?>' + this.editingId :
                            '<?php echo esc_url_raw(rest_url('wp-store-mp/v1/products')); ?>';
                        const method = this.editingId ? 'PUT' : 'POST';
                        fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo esc_js($nonce); ?>'
                            },
                            body: JSON.stringify(body)
                        }).then(r => r.json()).then(d => {
                            this.loading = false;
                            if (d && d.success) {
                                this.resetForm();
                                this.fetchItems();
                            } else {
                                alert(d && d.message ? d.message : 'Gagal menyimpan');
                            }
                        }).catch(() => {
                            this.loading = false;
                            alert('Gagal menyimpan');
                        });
                    },
                    removeItem(it) {
                        if (!confirm('Hapus produk ini?')) return;
                        fetch('<?php echo esc_url_raw(rest_url('wp-store-mp/v1/products/')); ?>' + it.id, {
                            method: 'DELETE',
                            headers: {
                                'X-WP-Nonce': '<?php echo esc_js($nonce); ?>'
                            }
                        }).then(r => r.json()).then(d => {
                            if (d && d.success) {
                                this.fetchItems();
                            } else {
                                alert(d && d.message ? d.message : 'Gagal menghapus');
                            }
                        }).catch(() => alert('Gagal menghapus'));
                    },
                    formatCurrency(n) {
                        try {
                            return new Intl.NumberFormat('id-ID', {
                                maximumFractionDigits: 2
                            }).format(n);
                        } catch (e) {
                            return n;
                        }
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
