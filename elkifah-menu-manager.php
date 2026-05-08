<?php
/*
Plugin Name: Elkifah Ultimate Menu Manager
Description: Mengonversi dan mengedit URL/Target menu, serta Generate halaman dan menu otomatis berbasis teks (ASCII tree).
Version: 4.0
Author: ELKIFAH PRODUCTION
*/

if (!defined('ABSPATH'))
    exit;

// 1. Daftarkan Menu di Sidebar Utama WordPress
add_action('admin_menu', 'elkifah_ultimate_menu_manager');
function elkifah_ultimate_menu_manager()
{
    add_menu_page(
        'Menu Manager',
        'Menu Manager',
        'manage_options',
        'elkifah-menu-manager',
        'elkifah_ultimate_menu_page',
        'dashicons-admin-links', // Ikon Rantai
        80
    );

    // Submenu untuk Auto Generator
    add_submenu_page(
        'elkifah-menu-manager',
        'Auto Menu Generator',
        'Auto Generator',
        'manage_options',
        'elkifah-auto-menu',
        'elkifah_auto_menu_callback'
    );
}

// Helper: Cari page berdasarkan URL
function elkifah_find_page_by_url($url)
{
    if (empty($url) || $url === '#' || $url === '/')
        return false;

    // Pastikan URL absolut untuk url_to_postid
    if (strpos($url, 'http') === false) {
        $url = home_url($url);
    }

    $post_id = url_to_postid($url);
    if ($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'page') {
            return $post;
        }
    }
    return false;
}

// 2. Fungsi Utama
function elkifah_ultimate_menu_page()
{
    echo '<div class="wrap"><h1>Elkifah Ultimate Menu Manager</h1>';

    // Ambil daftar kategori
    $categories = get_categories(['hide_empty' => false]);
    // Ambil daftar page
    $pages = get_posts(['post_type' => 'page', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);

    // AKSI: PROSES PENYIMPANAN
    if (isset($_POST['save_menu_changes']) && !empty($_POST['menu_data'])) {
        $count = 0;
        foreach ($_POST['menu_data'] as $item_id => $data) {
            $item = get_post($item_id);
            if (!$item)
                continue;

            // Aksi 1: Custom Link
            if ($data['action'] === 'to_custom_link') {
                $new_url = sanitize_text_field($data['custom_url']);
                $new_title = sanitize_text_field($data['anchor_text']);
                $new_target = isset($data['open_new_tab']) ? '_blank' : '';

                update_post_meta($item_id, '_menu_item_type', 'custom');
                update_post_meta($item_id, '_menu_item_object', 'custom');
                update_post_meta($item_id, '_menu_item_url', $new_url);
                update_post_meta($item_id, '_menu_item_target', $new_target);
                update_post_meta($item_id, '_menu_item_object_id', $item_id);

                if (!empty($new_title)) {
                    wp_update_post(['ID' => $item_id, 'post_title' => $new_title]);
                }
                $count++;
            }
            // Aksi 2: Kategori
            elseif ($data['action'] === 'to_category') {
                $cat_id = intval($data['category_id']);
                update_post_meta($item_id, '_menu_item_type', 'taxonomy');
                update_post_meta($item_id, '_menu_item_object', 'category');
                update_post_meta($item_id, '_menu_item_object_id', $cat_id);
                delete_post_meta($item_id, '_menu_item_url');
                $count++;
            }
            // Aksi 3: Page
            elseif ($data['action'] === 'to_page') {
                $page_id = intval($data['page_id']);
                update_post_meta($item_id, '_menu_item_type', 'post_type');
                update_post_meta($item_id, '_menu_item_object', 'page');
                update_post_meta($item_id, '_menu_item_object_id', $page_id);
                delete_post_meta($item_id, '_menu_item_url');
                $count++;
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Sukses! <strong>' . $count . '</strong> item menu berhasil diperbarui.</p></div>';
    }

    $menus = wp_get_nav_menus();
    if (empty($menus)) {
        echo '<p>Belum ada menu navigasi di website ini.</p></div>';
        return;
    }

    $selected_menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : $menus[0]->term_id;

    echo '<form method="post" style="margin-bottom:20px; background:#fff; padding:15px; border:1px solid #ccc;">
            <label style="font-weight:bold; font-size:14px;">Pilih Susunan Menu: </label>
            <select name="menu_id" onchange="this.form.submit()" style="min-width: 250px; padding: 3px;">';
    foreach ($menus as $menu) {
        $selected = ($menu->term_id == $selected_menu_id) ? 'selected' : '';
        echo '<option value="' . $menu->term_id . '" ' . $selected . '>' . $menu->name . '</option>';
    }
    echo '</select></form>';

    $menu_items = wp_get_nav_menu_items($selected_menu_id);

    if ($menu_items) {
        echo '<form method="post">
                <input type="hidden" name="menu_id" value="' . $selected_menu_id . '">
                <table class="wp-list-table widefat fixed striped" style="max-width: 1200px;">
                    <thead>
                        <tr>
                            <th style="width:250px;">Nama Menu (Hierarki)</th>
                            <th style="width:140px;">Tipe Saat Ini</th>
                            <th style="width:180px;">Aksi Konversi</th>
                            <th>Opsi / Input</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($menu_items as $item) {
            $type = get_post_meta($item->ID, '_menu_item_type', true);
            $object = get_post_meta($item->ID, '_menu_item_object', true);
            $matching_page = false;

            if ($type == 'post_type' && $object == 'page') {
                $type_label = '<span style="color:#0071a1; font-weight:bold;">Page</span>';
                $current_target = get_permalink(get_post_meta($item->ID, '_menu_item_object_id', true));
            } elseif ($type == 'custom') {
                $type_label = '<span style="color:green; font-weight:bold;">Custom Link</span>';
                $current_target = get_post_meta($item->ID, '_menu_item_url', true);
                $matching_page = elkifah_find_page_by_url($current_target);
            } elseif ($type == 'taxonomy' && $object == 'category') {
                $type_label = '<span style="color:orange; font-weight:bold;">Category</span>';
                $cat = get_category(get_post_meta($item->ID, '_menu_item_object_id', true));
                $current_target = ($cat && !is_wp_error($cat)) ? 'Kategori: ' . $cat->name : 'Kategori Dihapus';
                $matching_page = elkifah_find_page_by_url(get_term_link($cat));
            } else {
                $type_label = '<span style="color:gray;">Lainnya (' . $object . ')</span>';
                $current_target = '-';
            }

            $indent = ($item->menu_item_parent) ? '&mdash; ' : '<strong>';
            $end_indent = ($item->menu_item_parent) ? '' : '</strong>';

            echo '<tr>';
            echo '<td>' . $indent . esc_html($item->title) . $end_indent . '</td>';
            echo '<td>' . $type_label . '<br><small style="color:#888; word-break: break-all;">' . $current_target . '</small></td>';

            echo '<td>
                    <select name="menu_data[' . $item->ID . '][action]" onchange="toggleInput(this, ' . $item->ID . ')" style="width:100%;">
                        <option value="none">-- Biarkan --</option>
                        <option value="to_custom_link">Ubah ke Custom Link</option>
                        <option value="to_category">Ubah ke Kategori</option>
                        <option value="to_page">' . ($matching_page ? 'Ubah Kembali ke Page' : 'Ubah ke Page') . '</option>
                    </select>';

            if ($matching_page) {
                echo '<div style="margin-top:5px; padding:5px; background:#e7f5fe; border-left:4px solid #0071a1; font-size:11px;">
                        <strong>Saran:</strong> URL identik dengan Page <strong>"' . $matching_page->post_title . '"</strong>.
                      </div>';
            }

            echo '</td>';

            echo '<td>
                    <div id="input_custom_' . $item->ID . '" style="display:none;">
                        <input type="text" name="menu_data[' . $item->ID . '][anchor_text]" placeholder="Teks Menu" value="' . esc_attr($item->title) . '" style="width:100%; margin-bottom:5px;">
                        <input type="text" name="menu_data[' . $item->ID . '][custom_url]" placeholder="URL (# atau https://...)" value="' . esc_attr($current_target) . '" style="width:100%; margin-bottom:5px;">
                        <label style="font-size:12px; cursor:pointer;"><input type="checkbox" name="menu_data[' . $item->ID . '][open_new_tab]" value="1" ' . ($item->target == '_blank' ? 'checked' : '') . '> Buka tab baru</label>
                    </div>
                    <div id="input_category_' . $item->ID . '" style="display:none;">
                        <select name="menu_data[' . $item->ID . '][category_id]" style="width:100%;">';
            foreach ($categories as $cat) {
                echo '<option value="' . $cat->term_id . '">' . $cat->name . '</option>';
            }
            echo '      </select>
                    </div>
                    <div id="input_page_' . $item->ID . '" style="display:none;">
                        <select name="menu_data[' . $item->ID . '][page_id]" style="width:100%;">';
            foreach ($pages as $p) {
                $sel = ($matching_page && $matching_page->ID == $p->ID) ? 'selected' : '';
                echo '<option value="' . $p->ID . '" ' . $sel . '>' . $p->post_title . '</option>';
            }
            echo '      </select>
                    </div>
                  </td>';
            echo '</tr>';
        }

        echo '</tbody></table>
              <br>
              <button type="submit" name="save_menu_changes" class="button button-primary button-large">Simpan Perubahan Menu</button>
              </form>';

        echo '<script>
                function toggleInput(selectObj, itemId) {
                    var customDiv = document.getElementById("input_custom_" + itemId);
                    var categoryDiv = document.getElementById("input_category_" + itemId);
                    var pageDiv = document.getElementById("input_page_" + itemId);
                    
                    customDiv.style.display = "none";
                    categoryDiv.style.display = "none";
                    pageDiv.style.display = "none";

                    if(selectObj.value == "to_custom_link") {
                        customDiv.style.display = "block";
                    } else if(selectObj.value == "to_category") {
                        categoryDiv.style.display = "block";
                    } else if(selectObj.value == "to_page") {
                        pageDiv.style.display = "block";
                    }
                }
              </script>';

    } else {
        echo '<p>Menu ini masih kosong.</p>';
    }
    echo '</div>';
}

// 3. Fungsi Auto Menu Generator
function elkifah_auto_menu_callback()
{
    echo '<div class="wrap"><h1>Elkifah Auto Page & Menu Generator</h1>';
    $menu_name = 'Navigasi Otomatis Elkifah';

    // AKSI RESET
    if (isset($_POST['elkifah_action']) && $_POST['elkifah_action'] == 'reset') {
        $pages = get_pages(['meta_key' => '_elkifah_generated', 'meta_value' => '1', 'hierarchical' => 0]);
        $count = 0;
        foreach ($pages as $p) {
            wp_delete_post($p->ID, true);
            $count++;
        }

        $menu = wp_get_nav_menu_object($menu_name);
        if ($menu)
            wp_delete_nav_menu($menu->term_id);

        echo '<div class="notice notice-warning is-dismissible"><p>Reset berhasil: <strong>' . $count . '</strong> halaman dan menu dihapus.</p></div>';
    }

    // AKSI GENERATE
    if (isset($_POST['elkifah_action']) && $_POST['elkifah_action'] == 'generate') {
        $raw_data = $_POST['menu_data'];
        $lines = explode("\n", $raw_data);

        $menu_exists = wp_get_nav_menu_object($menu_name);
        if ($menu_exists)
            wp_delete_nav_menu($menu_exists->term_id);
        $menu_id = wp_create_nav_menu($menu_name);

        $stack = []; // Menyimpan struktur kedalaman [depth => [page_id, menu_id]]

        foreach ($lines as $line) {
            // Bersihkan spasi gaib (non-breaking space) dan enter
            $line = str_replace(["\xC2\xA0", "\r"], [" ", ""], $line);

            // Lewati baris kosong, header, atau baris yang cuma berisi garis vertikal "│"
            if (trim($line) === '' || strpos($line, '[ MENU') !== false || preg_match('/^[│\s]*$/u', $line)) {
                continue;
            }

            // Pisahkan karakter struktur (garis/spasi) dengan teks judul
            if (preg_match('/^([│├└─\s]*)(.*)$/u', $line, $matches)) {
                $prefix = $matches[1];
                $title = trim($matches[2]);

                // Hapus penomoran seperti "1. ", "2. " dari awal judul
                $title = preg_replace('/^\d+\.\s*/', '', $title);

                if (empty($title))
                    continue;

                // Hitung panjang prefix untuk menentukan kedalaman (level)
                $depth = mb_strlen($prefix);

                // Cari parent berdasarkan kedalaman
                while (!empty($stack) && end($stack)['depth'] >= $depth) {
                    array_pop($stack);
                }

                $parent_page_id = empty($stack) ? 0 : end($stack)['page_id'];
                $parent_menu_id = empty($stack) ? 0 : end($stack)['menu_id'];

                // 1. Buat Page
                $page_id = wp_insert_post([
                    'post_title' => $title,
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'post_parent' => $parent_page_id
                ]);
                update_post_meta($page_id, '_elkifah_generated', '1');

                // 2. Buat Menu Item
                $menu_item_id = wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-title' => $title,
                    'menu-item-object-id' => $page_id,
                    'menu-item-object' => 'page',
                    'menu-item-parent-id' => $parent_menu_id,
                    'menu-item-type' => 'post_type',
                    'menu-item-status' => 'publish'
                ]);

                // 3. Masukkan ke stack
                $stack[] = [
                    'depth' => $depth,
                    'page_id' => $page_id,
                    'menu_id' => $menu_item_id
                ];
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Sukses! Struktur diagram berhasil dikonversi menjadi Halaman dan Menu.</p></div>';
    }

    // TAMPILAN FORM
    echo '<form method="post" style="margin-top:20px;">
            <p>Paste langsung teks diagram <strong>(berisi garis ├──, └──, │)</strong> ke dalam kotak di bawah ini:</p>
            <textarea name="menu_data" rows="20" style="width:100%; max-width:800px; font-family:monospace; background:#1e1e1e; color:#00ff00; padding:10px;" placeholder="Paste diagram tree di sini..."></textarea><br><br>
            
            <button type="submit" name="elkifah_action" value="generate" class="button button-primary button-large">Generate Pages & Menu</button>
            <button type="submit" name="elkifah_action" value="reset" class="button button-large" style="color:red; border-color:red;" onclick="return confirm(\'PERINGATAN: Ini akan menghapus SEMUA halaman dan menu yang dibuat oleh plugin ini. Lanjutkan?\');">Reset (Hapus Semua)</button>
          </form></div>';
}
?>