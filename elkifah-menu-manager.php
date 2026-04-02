<?php
/*
Plugin Name: Elkifah Ultimate Menu Manager
Description: Mengonversi dan mengedit URL/Target menu (Custom Link & Kategori) langsung dari satu antarmuka tanpa perlu ke Appearance > Menus.
Version: 3.0
Author: ELKIFAH PRODUCTION
*/

if (!defined('ABSPATH')) exit;

// 1. Daftarkan Menu di Sidebar Utama WordPress
add_action('admin_menu', 'elkifah_ultimate_menu_manager');
function elkifah_ultimate_menu_manager() {
    add_menu_page(
        'Menu Manager', 
        'Menu Manager', 
        'manage_options', 
        'elkifah-menu-manager', 
        'elkifah_ultimate_menu_page', 
        'dashicons-admin-links', // Ikon Rantai
        80
    );
}

// 2. Fungsi Utama
function elkifah_ultimate_menu_page() {
    echo '<div class="wrap"><h1>Elkifah Ultimate Menu Manager</h1>';
    
    // Ambil daftar kategori (untuk dropdown konversi ke Kategori)
    $categories = get_categories(['hide_empty' => false]);

    // AKSI: PROSES PENYIMPANAN
    if (isset($_POST['save_menu_changes']) && !empty($_POST['menu_data'])) {
        $count = 0;
        foreach ($_POST['menu_data'] as $item_id => $data) {
            $item = get_post($item_id);
            if (!$item) continue;

            // Aksi 1: Jika user memilih mengubah menjadi Custom Link
            if ($data['action'] === 'to_custom_link') {
                $new_url = sanitize_text_field($data['custom_url']);
                $new_title = sanitize_text_field($data['anchor_text']);
                $new_target = isset($data['open_new_tab']) ? '_blank' : '';
                
                // Update Type, Object, Target, & URL Metas
                update_post_meta($item_id, '_menu_item_type', 'custom');
                update_post_meta($item_id, '_menu_item_object', 'custom');
                update_post_meta($item_id, '_menu_item_url', $new_url);
                update_post_meta($item_id, '_menu_item_target', $new_target);
                update_post_meta($item_id, '_menu_item_object_id', $item_id); 

                // Update Judul/Anchor Teks (Post Title)
                if (!empty($new_title)) {
                    wp_update_post([
                        'ID' => $item_id,
                        'post_title' => $new_title
                    ]);
                }
                $count++;
            }
            // Aksi 2: Jika user memilih mengubah menjadi Kategori
            elseif ($data['action'] === 'to_category') {
                $cat_id = intval($data['category_id']);
                update_post_meta($item_id, '_menu_item_type', 'taxonomy');
                update_post_meta($item_id, '_menu_item_object', 'category');
                update_post_meta($item_id, '_menu_item_object_id', $cat_id);
                // Bersihkan URL custom lama
                delete_post_meta($item_id, '_menu_item_url'); 
                $count++;
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Sukses! <strong>'.$count.'</strong> item menu berhasil diperbarui.</p></div>';
    }

    // Ambil semua menu yang ada
    $menus = wp_get_nav_menus();
    if (empty($menus)) {
        echo '<p>Belum ada menu navigasi di website ini.</p></div>';
        return;
    }

    $selected_menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : $menus[0]->term_id;

    // UI: PILIH MENU UTAMA
    echo '<form method="post" style="margin-bottom:20px; background:#fff; padding:15px; border:1px solid #ccc;">
            <label style="font-weight:bold; font-size:14px;">Pilih Susunan Menu: </label>
            <select name="menu_id" onchange="this.form.submit()" style="min-width: 250px; padding: 3px;">';
    foreach ($menus as $menu) {
        $selected = ($menu->term_id == $selected_menu_id) ? 'selected' : '';
        echo '<option value="'.$menu->term_id.'" '.$selected.'>'.$menu->name.'</option>';
    }
    echo '</select></form>';

    // UI: TABEL PENGEDITAN MENU
    $menu_items = wp_get_nav_menu_items($selected_menu_id);
    
    if ($menu_items) {
        echo '<form method="post">
                <input type="hidden" name="menu_id" value="'.$selected_menu_id.'">
                <table class="wp-list-table widefat fixed striped" style="max-width: 1200px;">
                    <thead>
                        <tr>
                            <th style="width:250px;">Nama Menu (Hierarki)</th>
                            <th style="width:120px;">Tipe Saat Ini</th>
                            <th style="width:180px;">Aksi Konversi</th>
                            <th>Input URL Baru / Pilih Kategori</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($menu_items as $item) {
            $type = get_post_meta($item->ID, '_menu_item_type', true);
            $object = get_post_meta($item->ID, '_menu_item_object', true);
            
            // Tentukan label tipe saat ini
            if ($type == 'post_type' && $object == 'page') {
                $type_label = '<span style="color:#0071a1; font-weight:bold;">Page</span>';
                $current_target = get_permalink(get_post_meta($item->ID, '_menu_item_object_id', true));
            } elseif ($type == 'custom') {
                $type_label = '<span style="color:green; font-weight:bold;">Custom Link</span>';
                $current_target = get_post_meta($item->ID, '_menu_item_url', true);
            } elseif ($type == 'taxonomy' && $object == 'category') {
                $type_label = '<span style="color:orange; font-weight:bold;">Category</span>';
                $cat = get_category(get_post_meta($item->ID, '_menu_item_object_id', true));
                $current_target = ($cat && !is_wp_error($cat)) ? 'Kategori: ' . $cat->name : 'Kategori Dihapus';
            } else {
                $type_label = '<span style="color:gray;">Lainnya ('.$object.')</span>';
                $current_target = '-';
            }

            // Visual Hierarki
            $indent = ($item->menu_item_parent) ? '&mdash; ' : '<strong>';
            $end_indent = ($item->menu_item_parent) ? '' : '</strong>';
            
            echo '<tr>';
            // Kolom 1: Nama
            echo '<td>'.$indent . esc_html($item->title) . $end_indent.'</td>';
            
            // Kolom 2: Tipe
            echo '<td>'.$type_label.'<br><small style="color:#888;">'.$current_target.'</small></td>';
            
            // Kolom 3: Dropdown Aksi
            echo '<td>
                    <select name="menu_data['.$item->ID.'][action]" onchange="toggleInput(this, '.$item->ID.')" style="width:100%;">
                        <option value="none">-- Biarkan --</option>
                        <option value="to_custom_link">Ubah ke Custom Link</option>
                        <option value="to_category">Ubah ke Kategori</option>
                    </select>
                  </td>';
            
            // Kolom 4: Input Dinamis (Tergantung Pilihan Aksi)
            echo '<td>
                    <div id="input_custom_'.$item->ID.'" style="display:none;">
                        <input type="text" name="menu_data['.$item->ID.'][anchor_text]" placeholder="Teks Menu (misal: Beranda)" value="'.esc_attr($item->title).'" style="width:100%; margin-bottom:5px;">
                        <input type="text" name="menu_data['.$item->ID.'][custom_url]" placeholder="Ketik URL (misal: # atau https://...)" style="width:100%; margin-bottom:5px;">
                        <label style="font-size:12px; cursor:pointer;"><input type="checkbox" name="menu_data['.$item->ID.'][open_new_tab]" value="1" '.($item->target == '_blank' ? 'checked' : '').'> Buka di tab baru?</label>
                    </div>
                    <div id="input_category_'.$item->ID.'" style="display:none;">
                        <select name="menu_data['.$item->ID.'][category_id]" style="width:100%;">';
                            foreach($categories as $cat) {
                                echo '<option value="'.$cat->term_id.'">'.$cat->name.'</option>';
                            }
            echo '      </select>
                    </div>
                  </td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>
              <br>
              <button type="submit" name="save_menu_changes" class="button button-primary button-large" style="font-size:16px; padding:5px 30px;">Simpan Perubahan Menu</button>
              </form>';

        // Script Javascript sederhana untuk menampilkan/menyembunyikan input berdasarkan pilihan dropdown
        echo '<script>
                function toggleInput(selectObj, itemId) {
                    var customDiv = document.getElementById("input_custom_" + itemId);
                    var categoryDiv = document.getElementById("input_category_" + itemId);
                    
                    if(selectObj.value == "to_custom_link") {
                        customDiv.style.display = "block";
                        categoryDiv.style.display = "none";
                    } else if(selectObj.value == "to_category") {
                        customDiv.style.display = "none";
                        categoryDiv.style.display = "block";
                    } else {
                        customDiv.style.display = "none";
                        categoryDiv.style.display = "none";
                    }
                }
              </script>';

    } else {
        echo '<p>Menu ini masih kosong.</p>';
    }
    echo '</div>';
}
?>
