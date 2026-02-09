<?php

namespace WpStoreMp\Admin;

class PostTypes
{
    public function register_vendor_request()
    {
        $labels = [
            'name' => 'Pengajuan Vendor',
            'singular_name' => 'Pengajuan Vendor',
            'menu_name' => 'Pengajuan Vendor',
            'add_new' => 'Tambah Baru',
            'add_new_item' => 'Tambah Pengajuan',
            'edit_item' => 'Edit Pengajuan',
            'new_item' => 'Pengajuan Baru',
            'view_item' => 'Lihat Pengajuan',
            'all_items' => 'Semua Pengajuan',
            'search_items' => 'Cari Pengajuan',
            'not_found' => 'Tidak ada pengajuan',
        ];
        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor'],
        ];
        register_post_type('mp_vendor_request', $args);
    }
}
