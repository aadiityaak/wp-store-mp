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
                        <div class="wps-mb-3">
                            <button class="wps-btn wps-btn-primary" @click="openAdd">Tambah Produk</button>
                        </div>
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
                        <div class="wps-text-sm wps-text-gray-600">Klik “Tambah Produk” untuk membuka formulir.</div>
                    </div>
                </div>
            </div>
            <div x-show="showModal" x-transition
                style="position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;">
                <div class="wps-card" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:min(920px,95vw);max-height:85vh;display:flex;flex-direction:column;">
                    <div class="wps-p-4" style="border-bottom:1px solid #e5e7eb;">
                        <div class="wps-flex wps-justify-between wps-items-center">
                            <div class="wps-text-sm wps-font-medium" x-text="editingId ? 'Ubah Produk' : 'Tambah Produk'"></div>
                            <button class="wps-btn wps-btn-secondary" @click="closeModal">Tutup</button>
                        </div>
                    </div>
                    <div class="wps-p-4" style="flex:1;overflow:auto;">
                        <div class="wps-tabs">
                            <template x-for="tab in tabs" :key="tab.id">
                                <button class="wps-tab" :class="{'active': activeFormTab===tab.id}" @click="activeFormTab=tab.id" x-text="tab.title"></button>
                            </template>
                        </div>
                        <form @submit.prevent="submit" class="wps-mt-3">
                            <div class="wps-grid wps-grid-cols-1 wps-md-grid-cols-2 wps-gap-4" x-show="activeFormTab==='general'">
                                <div>
                                    <div class="wps-form-group">
                                        <label class="wps-form-label">Judul</label>
                                        <input type="text" class="wps-form-input" x-model="form.title" required>
                                    </div>
                                    <div class="wps-form-group">
                                        <label class="wps-form-label">Deskripsi</label>
                                        <textarea class="wps-form-textarea" x-model="form.content"></textarea>
                                    </div>
                                    <template x-for="field in getFields('general')" :key="field.id">
                                        <div class="wps-form-group">
                                            <label class="wps-form-label" x-text="field.label"></label>
                                            <template x-if="field.type==='select'">
                                                <select class="wps-form-input" x-model="form.meta[field.id]">
                                                    <template x-for="(label,val) in field.options" :key="val">
                                                        <option :value="val" x-text="label"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="field.type==='number'">
                                                <input type="number" class="wps-form-input" :step="field.attributes?.step||'1'" :min="field.attributes?.min||null" x-model.number="form.meta[field.id]">
                                            </template>
                                            <template x-if="field.type==='datetime-local'">
                                                <input type="datetime-local" class="wps-form-input" x-model="form.meta[field.id]">
                                            </template>
                                            <template x-if="field.type==='file'">
                                                <input type="number" min="0" class="wps-form-input" x-model.number="form.meta[field.id]">
                                            </template>
                                        </div>
                                    </template>
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
                                    <div class="wps-form-group">
                                        <label class="wps-form-label">Status</label>
                                        <select class="wps-form-input" x-model="form.status">
                                            <option value="draft">Draft</option>
                                            <option value="pending">Pending</option>
                                            <option value="publish">Publish</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div x-show="activeFormTab==='inventory'">
                                <template x-for="field in getFields('inventory')" :key="field.id">
                                    <div class="wps-form-group">
                                        <label class="wps-form-label" x-text="field.label"></label>
                                        <input type="number" class="wps-form-input" :step="field.attributes?.step||'1'" :min="field.attributes?.min||null" x-model.number="form.meta[field.id]">
                                    </div>
                                </template>
                            </div>
                            <div x-show="activeFormTab==='attributes'">
                                <template x-for="field in getFields('attributes')" :key="field.id">
                                    <div class="wps-form-group">
                                        <label class="wps-form-label" x-text="field.label"></label>
                                        <template x-if="field.type==='text'">
                                            <input type="text" class="wps-form-input" x-model="form.meta[field.id]">
                                        </template>
                                        <template x-if="field.type==='select'">
                                            <select class="wps-form-input" x-model="form.meta[field.id]">
                                                <template x-for="(label,val) in field.options" :key="val">
                                                    <option :value="val" x-text="label"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="field.type==='repeatable_text'">
                                            <div class="wps-flex wps-flex-col wps-gap-2">
                                                <template x-for="(opt, idx) in form.meta[field.id]" :key="idx">
                                                    <div class="wps-flex wps-gap-2">
                                                        <input type="text" class="wps-form-input" x-model="form.meta[field.id][idx]">
                                                        <button type="button" class="wps-btn wps-btn-danger" @click="form.meta[field.id].splice(idx,1)">Hapus</button>
                                                    </div>
                                                </template>
                                                <button type="button" class="wps-btn wps-btn-secondary" @click="form.meta[field.id].push('')">Tambah Opsi</button>
                                            </div>
                                        </template>
                                        <template x-if="field.type==='group_advanced_options'">
                                            <div class="wps-flex wps-flex-col wps-gap-2">
                                                <template x-for="(g, i) in form.meta[field.id]" :key="i">
                                                    <div class="wps-grid wps-grid-cols-1 wps-md-grid-cols-3 wps-gap-2">
                                                        <input type="text" class="wps-form-input" x-model="form.meta[field.id][i].label" placeholder="Label">
                                                        <input type="number" step="0.01" min="0" class="wps-form-input" x-model.number="form.meta[field.id][i].price" placeholder="Harga">
                                                        <button type="button" class="wps-btn wps-btn-danger" @click="form.meta[field.id].splice(i,1)">Hapus</button>
                                                    </div>
                                                </template>
                                                <button type="button" class="wps-btn wps-btn-secondary" @click="form.meta[field.id].push({label:'',price:''})">Tambah Opsi</button>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <div x-show="activeFormTab==='gallery'">
                                <template x-for="field in getFields('gallery')" :key="field.id">
                                    <div class="wps-form-group">
                                        <label class="wps-form-label" x-text="field.label"></label>
                                        <input type="text" class="wps-form-input" x-model="form.meta[field.id]" placeholder="cth: 101,102,103">
                                    </div>
                                </template>
                            </div>
                        </form>
                    </div>
                    <div class="wps-p-4" style="border-top:1px solid #e5e7eb;">
                        <div class="wps-flex wps-justify-end">
                            <button type="button" class="wps-btn wps-btn-primary" :disabled="loading" @click="submit">
                                <span x-show="!loading">Simpan</span>
                                <span x-show="loading">Menyimpan...</span>
                            </button>
                        </div>
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
                    showModal: false,
                    tabs: [],
                    schema: {},
                    form: {
                        title: '',
                        content: '',
                        status: 'draft',
                        image_id: '',
                        categories_raw: '',
                        meta: {}
                    },
                    init() {
                        fetch('<?php echo esc_url_raw(rest_url('wp-store-mp/v1/products/schema')); ?>', {
                            headers: {
                                'X-WP-Nonce': '<?php echo esc_js($nonce); ?>'
                            }
                        }).then(r => r.json()).then(s => {
                            this.tabs = Array.isArray(s.tabs) ? s.tabs : [];
                            this.schema = {};
                            for (const t of this.tabs) {
                                this.schema[t.id] = t.fields;
                            }
                            this.initializeMetaDefaults();
                        });
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
                    getFields(tabId) {
                        return Array.isArray(this.schema[tabId]) ? this.schema[tabId] : [];
                    },
                    initializeMetaDefaults() {
                        const meta = {};
                        for (const t of this.tabs) {
                            for (const f of t.fields) {
                                if (f.type === 'repeatable_text') meta[f.id] = [];
                                else if (f.type === 'group_advanced_options') meta[f.id] = [];
                                else meta[f.id] = f.default ?? '';
                            }
                        }
                        this.form.meta = meta;
                    },
                    openAdd() {
                        this.resetForm();
                        this.showModal = true;
                    },
                    editItem(it) {
                        this.editingId = it.id;
                        this.activeFormTab = 'general';
                        this.form.title = it.title || '';
                        this.form.content = it.content || '';
                        this.form.status = it.status || 'draft';
                        this.form.image_id = '';
                        this.form.categories_raw = Array.isArray(it.categories) ? it.categories.join(',') : '';
                        this.initializeMetaDefaults();
                        const map = {
                            '_store_product_type': it.product_type || 'physical',
                            '_store_price': it.price ?? '',
                            '_store_sale_price': it.sale_price ?? '',
                            '_store_flashsale_until': it.flashsale_until || '',
                            '_store_digital_file': it.digital_file || '',
                            '_store_sku': it.sku || '',
                            '_store_stock': it.stock ?? '',
                            '_store_min_order': it.min_order ?? '',
                            '_store_weight_kg': it.weight_kg ?? '',
                            '_store_label': it.label || '',
                            '_store_option_name': it.option_name || '',
                            '_store_options': Array.isArray(it.options) ? it.options : [],
                            '_store_option2_name': it.option2_name || '',
                            '_store_advanced_options': Array.isArray(it.advanced_options) ? it.advanced_options : [],
                            '_store_gallery_ids': Array.isArray(it.gallery_ids) ? it.gallery_ids.join(',') : ''
                        };
                        for (const k in map) {
                            if (Object.prototype.hasOwnProperty.call(this.form.meta, k)) {
                                this.form.meta[k] = map[k];
                            }
                        }
                        this.showModal = true;
                    },
                    resetForm() {
                        this.editingId = null;
                        this.activeFormTab = 'general';
                        this.form = {
                            title: '',
                            content: '',
                            status: 'draft',
                            image_id: '',
                            categories_raw: '',
                            meta: {}
                        };
                        this.initializeMetaDefaults();
                    },
                    closeModal() {
                        this.resetForm();
                        this.showModal = false;
                    },
                    submit() {
                        this.loading = true;
                        const cats = this.form.categories_raw.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n));
                        const body = {
                            title: this.form.title,
                            content: this.form.content,
                            status: this.form.status,
                            image_id: this.form.image_id,
                            categories: cats,
                            meta: this.form.meta
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
                                this.showModal = false;
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
