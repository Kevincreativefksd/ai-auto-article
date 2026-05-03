<?php
/*
Plugin Name: AI Auto Article Gemini Pro
Description: Generate artikel otomatis dengan Brand Context, Informasi Tambahan Opsional, dan Internal Link Beranda.
Version: 1.5.2
Author: Muhammad Arfakhsyad
*/

    if (!defined('ABSPATH')) {
    exit;
}

/* ================= MENU ================= */

add_action('admin_menu', function() {
    add_menu_page(
        'AI Auto Article',
        'AI Article',
        'manage_options',
        'ai-auto-article',
        'aaa_admin_page',
        'dashicons-edit'
    );
});

/* ================= ADMIN PAGE ================= */

function aaa_admin_page() {
    // 1. SIMPAN PENGATURAN KE DATABASE
    if (isset($_POST['save_settings'])) {
        update_option('aaa_gemini_api', sanitize_text_field($_POST['aaa_gemini_api']));
        update_option('aaa_brand_name', sanitize_text_field($_POST['aaa_brand_name']));
        update_option('aaa_target_audience', sanitize_text_field($_POST['aaa_target_audience']));
        update_option('aaa_tone_voice', sanitize_text_field($_POST['aaa_tone_voice']));
        echo "<div class='updated'><p>Pengaturan Brand dan API berhasil disimpan!</p></div>";
    }

    $api_key = get_option('aaa_gemini_api', '');
    $brand_name = get_option('aaa_brand_name', '');
    $target_audience = get_option('aaa_target_audience', '');
    $tone_voice = get_option('aaa_tone_voice', '');

    // 2. PROSES GENERATE ARTIKEL
    if (isset($_POST['aaa_generate_btn'])) {
        $keyword = sanitize_text_field($_POST['aaa_keyword']);
        $extra_info = sanitize_textarea_field($_POST['aaa_extra_info']); // Ambil info tambahan
        
        $data = aaa_generate_article($keyword, $extra_info);

        if ($data && !empty($data['content'])) {
            $post_id = wp_insert_post([
                'post_title'   => ucfirst($keyword),
                'post_content' => $data['content'], 
                'post_status'  => 'draft',
                'post_author'  => get_current_user_id(),
            ]);

            if ($post_id) {
                // Proses Tag Otomatis (Fix logic)
                if (!empty($data['tags'])) {
                    $tags_array = preg_split('/[,|#]/', $data['tags']); 
                    $tags_array = array_map('trim', $tags_array);
                    wp_set_post_tags($post_id, array_filter($tags_array), false);
                }
                echo "<div class='updated'><p>Sukses! Artikel <strong>" . esc_html($keyword) . "</strong> berhasil dibuat sebagai Draft.</p></div>";
            }
        } else {
            echo "<div class='error'><p>Gagal menghubungi Gemini. Cek koneksi atau API Key Anda.</p></div>";
        }
    }

    ?>
    <div class="wrap">
        <h1>AI Article Generator (Gemini Flash)</h1>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
            
            <div class="card" style="flex: 1; min-width: 400px; padding: 20px; border-radius: 8px;">
                <h3><span class="dashicons dashicons-admin-generic"></span> Konfigurasi Brand & API</h3>
                <hr>
                <form method="post">
                    <p><strong>Gemini API Key:</strong><br>
                    <input type="password" name="aaa_gemini_api" value="<?php echo esc_attr($api_key); ?>" style="width: 100%;" placeholder="Paste API Key..."></p>
                    
                    <p><strong>Nama Brand:</strong><br>
                    <input type="text" name="aaa_brand_name" value="<?php echo esc_attr($brand_name); ?>" style="width: 100%;"></p>
                    
                    <p><strong>Target Audience:</strong><br>
                    <input type="text" name="aaa_target_audience" value="<?php echo esc_attr($target_audience); ?>" style="width: 100%;"></p>
                    
                    <p><strong>Tone of Voice:</strong><br>
                    <input type="text" name="aaa_tone_voice" value="<?php echo esc_attr($tone_voice); ?>" style="width: 100%;"></p>
                    
                    <input type="submit" name="save_settings" class="button button-primary" value="Simpan Pengaturan">
                </form>
            </div>

            <div class="card" style="flex: 1; min-width: 400px; padding: 20px; border-radius: 8px; background: #fdfdfd;">
                <h3><span class="dashicons dashicons-welcome-write-blog"></span> Buat Artikel</h3>
                <hr>
                <form method="post">
                    <p><strong>Kata Kunci Utama:</strong></p>
                    <input type="text" name="aaa_keyword" placeholder="Ketik topik artikel..." style="width: 100%; padding: 10px; font-size: 1.1em;" required>
                    
                    <p><strong>Informasi Tambahan (Opsional):</strong></p>
                    <textarea name="aaa_extra_info" rows="5" style="width: 100%;" placeholder="Sertakan detail produk, promo, atau poin khusus yang ingin dibahas..."></textarea>
                    
                    <br><br>
                    <input type="submit" name="aaa_generate_btn" class="button button-primary button-large" value="Generate Artikel & Tag" style="width: 100%; height: 50px; font-weight: bold;">
                </form>
            </div>

        </div>
    </div>
    <?php
}

/* ================= GEMINI FUNCTION ================= */
function aaa_generate_article($keyword, $extra_info = '') {
    $api_key = get_option('aaa_gemini_api');
    $brand_name = get_option('aaa_brand_name');
    $target_audience = get_option('aaa_target_audience');
    $tone_voice = get_option('aaa_tone_voice');
    $home_url = home_url();

    if (!$api_key) return false;
    
    set_time_limit(300);

    // MENGGUNAKAN MODEL 3 FLASH (Paling Stabil untuk API Free Tier)
    $model_name = "gemini-3-flash-preview"; 
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model_name}:generateContent?key=" . $api_key;

    $additional_context = "";
    if (!empty($extra_info)) {
        $additional_context = "[INFORMASI TAMBAHAN]:\n$extra_info\n\n";
    }

    $prompt = "
[ROLE]
Anda adalah Konsultan Senior SEO (Topical Authority Specialist), Pakar Digital Marketing, dan Psikolog Perilaku Konsumen. Tugas Anda adalah menciptakan konten yang tidak hanya ramah algoritma Google (Semantic SEO), tetapi juga memicu konversi psikologis.

[BRAND CONTEXT]
Nama Brand: $brand_name
Target Audience: $target_audience
Tone of Voice: $tone_voice
$additional_context

[TASK]
Buat artikel SEO mendalam (min 800 kata) untuk keyword utama: '$keyword'.
Wajib memasang hyperlink ke $home_url pada penyebutan keyword '$keyword' pertama kali di paragraf awal.

[SEMANTIC & NLP RULES]

Identifikasi dan integrasikan 'LSI Keywords' (Latent Semantic Indexing) dan entitas yang berkaitan erat dengan '$keyword'.

Gunakan variasi sinonim dan frasa kontekstual untuk menghindari keyword stuffing dan membangun 'Topical Relevance'.

Jawab pertanyaan 'People Also Ask' yang relevan secara psikologis di dalam narasi artikel.

[STRUCTURE]

Mulai langsung dengan tag <h1> (Harus mengandung keyword utama).

Gunakan subjudul H2 dan H3 yang mengandung variasi semantic keyword dan long-tail keywords.

Gunakan kalimat pendek, nada aktif, dan bullet points untuk meningkatkan 'Readability Score'.

Sisipkan insight berbasis pengalaman (E-E-A-T)

Gunakan data atau penjelasan faktual untuk memperkuat kredibilitas

Setiap bagian harus memberikan nilai praktis (Utility) dan validasi psikologis bagi $target_audience.

Akhiri dengan CTA (Call to Action) natural ke $home_url dan bagian FAQ (min 3 pertanyaan).

[TECHNICAL RULES]

Output HANYA tag HTML artikel (h1, h2, h3, p, ul, li).

DILARANG KERAS menyertakan tag <style>, CSS, <html>, <head>, atau <body>.

Tanpa sapaan pembuka atau komentar di luar artikel.

Berikan markdown yang sesuai dengan ketentuan google dan suport di wordpress

Buat artikel terasa human-written

Jangan terlalu AI sounding

Fokus pada value, bukan hanya panjang tulisan
[TAGS GENERATION]
Setelah artikel selesai, berikan tepat 5 tag SEO yang paling relevan (kombinasi head dan long-tail) dipisahkan koma dan diawali dengan tanda ###.
Contoh: ### tag1, tag2, tag3, tag4, tag5
";

    $response = wp_remote_post($endpoint, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            "contents" => [["parts" => [["text" => $prompt]]]],
            "generationConfig" => [
                "temperature" => 0.7,
                "maxOutputTokens" => 8000 // Menambah kuota output agar artikel tidak terpotong
            ]
        ]),
        'timeout' => 180,
        'sslverify' => false 
    ]);

    // CEK APAKAH SERVER ANDA MEMBLOKIR KONEKSI
    if (is_wp_error($response)) {
        // Ini akan memunculkan pesan error teknis asli dari server Anda
        echo "<div class='error'><p>Error Teknis Server: " . $response->get_error_message() . "</p></div>";
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    // CEK APAKAH API KEY SALAH ATAU LIMIT HABIS (Error 403 / 429)
    if ($response_code !== 200) {
        $msg = isset($body['error']['message']) ? $body['error']['message'] : 'Koneksi Ditolak (Code: '.$response_code.')';
        echo "<div class='error'><p>Gemini API Error: " . $msg . "</p></div>";
        return false;
    }

    if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        $raw_output = $body['candidates'][0]['content']['parts'][0]['text'];
        $raw_output = preg_replace('/```html|```/', '', $raw_output);
        $raw_output = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $raw_output);
        $raw_output = preg_replace('/<html[^>]*>|<head[^>]*>|<\/head>|<body[^>]*>|<\/body>|<\/html>/i', '', $raw_output);

        $parts = explode('###', $raw_output);
        return [
            'content' => trim($parts[0]),
            'tags'    => isset($parts[1]) ? trim($parts[1]) : ''
        ];
    }
    
    return false;
}