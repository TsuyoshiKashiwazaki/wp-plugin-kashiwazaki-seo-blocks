<?php
/**
 * Plugin Name: Kashiwazaki SEO Blocks
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: シンプルで使いやすいブロックエディタ対応のカスタムブロックプラグイン。見出しバー、ボタン、汎用ボックスを簡単に作成できます。
 * Version: 1.0.3
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * License: GPL v2 or later
 * Text Domain: kashiwazaki-seo-blocks
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KSB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KSB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('KSB_PLUGIN_VERSION', '1.0.3');

class KashiwazakiSeoBlocks {

    const PRESETS_OPTION_KEY = 'ksb_speech_bubble_presets';
    const BUTTON_PRESETS_OPTION_KEY = 'ksb_button_presets';
    const BOX_PRESETS_OPTION_KEY = 'ksb_box_presets';

    public function __construct() {
        add_action('init', [$this, 'register_blocks']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_block_assets']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_filter('block_categories_all', [$this, 'add_block_category'], 10, 2);

        // AJAX handlers - Speech Bubble
        add_action('wp_ajax_ksb_save_presets', [$this, 'ajax_save_presets']);
        add_action('wp_ajax_ksb_get_presets', [$this, 'ajax_get_presets']);
        add_action('wp_ajax_ksb_bulk_convert_speech_bubble', [$this, 'ajax_bulk_convert_speech_bubble']);

        // AJAX handlers - Button
        add_action('wp_ajax_ksb_save_button_presets', [$this, 'ajax_save_button_presets']);
        add_action('wp_ajax_ksb_get_button_presets', [$this, 'ajax_get_button_presets']);
        add_action('wp_ajax_ksb_bulk_convert_button', [$this, 'ajax_bulk_convert_button']);

        // AJAX handlers - Box
        add_action('wp_ajax_ksb_save_box_presets', [$this, 'ajax_save_box_presets']);
        add_action('wp_ajax_ksb_get_box_presets', [$this, 'ajax_get_box_presets']);
        add_action('wp_ajax_ksb_bulk_convert_box', [$this, 'ajax_bulk_convert_box']);
    }
    
    public function add_block_category($categories, $post) {
        return array_merge(
            [
                [
                    'slug' => 'kashiwazaki-seo-blocks',
                    'title' => __('Kashiwazaki SEO Blocks', 'kashiwazaki-seo-blocks'),
                    'icon' => 'layout',
                ],
            ],
            $categories
        );
    }
    
    public function register_blocks() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        $blocks = [
            'heading-bar',
            'button',
            'box',
            'border',
            'table',
            'speech-bubble'
        ];
        
        foreach ($blocks as $block) {
            register_block_type(
                'kashiwazaki-seo-blocks/' . $block,
                [
                    'editor_script' => 'ksb-editor-script',
                    'editor_style' => 'ksb-editor-style',
                    'style' => 'ksb-style'
                ]
            );
        }
    }
    
    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'ksb-editor-script',
            KSB_PLUGIN_URL . 'build/index.js',
            ['wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-components', 'wp-data'],
            KSB_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ksb-editor-style',
            KSB_PLUGIN_URL . 'build/index.css',
            ['wp-edit-blocks'],
            KSB_PLUGIN_VERSION
        );
        
        wp_localize_script('ksb-editor-script', 'ksbData', [
            'pluginUrl' => KSB_PLUGIN_URL,
            'nonce' => wp_create_nonce('ksb_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'presets' => get_option(self::PRESETS_OPTION_KEY, []),
            'buttonPresets' => get_option(self::BUTTON_PRESETS_OPTION_KEY, []),
            'boxPresets' => get_option(self::BOX_PRESETS_OPTION_KEY, [])
        ]);
    }
    
    public function enqueue_block_assets() {
        if (!is_admin()) {
            wp_enqueue_style(
                'ksb-style',
                KSB_PLUGIN_URL . 'build/style-index.css',
                [],
                KSB_PLUGIN_VERSION
            );
        }
    }
    
    public function add_admin_menu() {
        $hook = add_menu_page(
            'Kashiwazaki SEO Blocks',
            'Kashiwazaki SEO Blocks',
            'manage_options',
            'kashiwazaki-seo-blocks',
            [$this, 'admin_page'],
            'dashicons-layout',
            81
        );

        // 管理画面でメディアライブラリを使えるようにする
        add_action('admin_print_scripts-' . $hook, function() {
            wp_enqueue_media();
        });
    }
    
    public function admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'about';
        ?>
        <div class="wrap">
            <h1>Kashiwazaki SEO Blocks</h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=kashiwazaki-seo-blocks&tab=about" class="nav-tab <?php echo $active_tab === 'about' ? 'nav-tab-active' : ''; ?>">このプラグインについて</a>
                <a href="?page=kashiwazaki-seo-blocks&tab=usage" class="nav-tab <?php echo $active_tab === 'usage' ? 'nav-tab-active' : ''; ?>">ブロック使用状況</a>
            </nav>

            <div class="ksb-admin-content" style="margin-top: 20px;">
                <?php
                if ($active_tab === 'about') {
                    $this->render_about_tab();
                } else if ($active_tab === 'usage') {
                    $this->render_usage_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_about_tab() {
        ?>
        <style>
            .ksb-about-section { margin-bottom: 30px; }
            .ksb-about-section h2 { font-size: 1.5em; border-bottom: 2px solid #2271b1; padding-bottom: 10px; margin-bottom: 15px; }
            .ksb-about-section h3 { font-size: 1.1em; color: #1d2327; margin: 20px 0 10px; }
            .ksb-block-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 15px; }
            .ksb-block-card h4 { margin: 0 0 10px; color: #2271b1; font-size: 1.1em; }
            .ksb-block-card p { margin: 0 0 10px; color: #50575e; }
            .ksb-block-card ul { margin: 10px 0 0 20px; color: #50575e; }
            .ksb-block-card ul li { margin-bottom: 5px; }
            .ksb-feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-top: 15px; }
            .ksb-feature-item { background: #f6f7f7; border-left: 4px solid #2271b1; padding: 15px; }
            .ksb-feature-item strong { color: #1d2327; }
            .ksb-info-box { background: #e7f3ff; border: 1px solid #72aee6; border-radius: 4px; padding: 15px; margin: 15px 0; }
            .ksb-version-table { width: auto; border-collapse: collapse; }
            .ksb-version-table th, .ksb-version-table td { padding: 8px 15px; text-align: left; border-bottom: 1px solid #c3c4c7; }
            .ksb-version-table th { background: #f0f0f1; }
        </style>

        <div class="ksb-about-section">
            <h2>Kashiwazaki SEO Blocks とは</h2>
            <p>Kashiwazaki SEO Blocksは、WordPressのブロックエディタ（Gutenberg）専用に設計されたカスタムブロックコレクションです。</p>
            <p>コンテンツ制作者が直感的に操作でき、コードを書くことなく美しいレイアウトを実現できます。すべてのブロックは軽量で高速に動作し、SEOとアクセシビリティに配慮して設計されています。</p>

            <div class="ksb-info-box">
                <strong>開発者:</strong> 柏崎剛 (Tsuyoshi Kashiwazaki)<br>
                <strong>公式サイト:</strong> <a href="https://www.tsuyoshikashiwazaki.jp" target="_blank">https://www.tsuyoshikashiwazaki.jp</a>
            </div>
        </div>

        <div class="ksb-about-section">
            <h2>利用可能なブロック</h2>

            <div class="ksb-block-card">
                <h4>見出しバー</h4>
                <p>記事内のセクションを視覚的に区切る装飾付き見出しブロックです。</p>
                <ul>
                    <li>H1〜H6の見出しレベルを選択可能</li>
                    <li>背景色・文字色・枠線色を自由にカスタマイズ</li>
                    <li>角丸、影（シャドウ）効果の設定</li>
                    <li>パディングの細かい調整</li>
                </ul>
            </div>

            <div class="ksb-block-card">
                <h4>ボタン</h4>
                <p>CTAやリンク誘導に最適なカスタマイズ可能なボタンブロックです。</p>
                <ul>
                    <li>リンク先URL、新規タブで開く設定</li>
                    <li>ボタンサイズ（小・中・大）の選択</li>
                    <li>全幅表示オプション</li>
                    <li>背景色・文字色・枠線・角丸のカスタマイズ</li>
                    <li>ホバー時のアニメーション効果</li>
                </ul>
            </div>

            <div class="ksb-block-card">
                <h4>汎用ボックス</h4>
                <p>注意書きや補足情報を目立たせるためのボックスブロックです。</p>
                <ul>
                    <li>5種類のプリセットアイコン（情報・警告・成功・注意・ヒント）</li>
                    <li>アイコンなしのシンプルなボックスも作成可能</li>
                    <li>背景にグラデーションを設定可能</li>
                    <li>枠線スタイル（実線・破線・点線）の選択</li>
                </ul>
            </div>

            <div class="ksb-block-card">
                <h4>ボーダー（区切り線）</h4>
                <p>コンテンツを視覚的に区切るための水平線ブロックです。</p>
                <ul>
                    <li>線の太さ・色・スタイルをカスタマイズ</li>
                    <li>実線・破線・点線・二重線から選択</li>
                    <li>線の幅（パーセント）を調整可能</li>
                    <li>上下の余白を個別に設定</li>
                </ul>
            </div>

            <div class="ksb-block-card">
                <h4>テーブル</h4>
                <p>データや比較表を美しく表示するための高機能テーブルブロックです。</p>
                <ul>
                    <li>行・列の追加・削除が簡単</li>
                    <li>ヘッダー行の有効/無効切り替え</li>
                    <li>ストライプ（縞模様）表示オプション</li>
                    <li>ホバー時のハイライト効果</li>
                    <li>横スクロール対応（モバイルでも見やすい）</li>
                    <li>セルの背景色・文字色を個別に設定</li>
                </ul>
            </div>

            <div class="ksb-block-card">
                <h4>吹き出し</h4>
                <p>会話形式のコンテンツやQ&Aを作成できる吹き出しブロックです。</p>
                <ul>
                    <li>アバター画像の設定（メディアライブラリから選択）</li>
                    <li>話者の名前・肩書・プロフィールURLを設定可能</li>
                    <li>アバターの位置（左/右）・形状（円形/角丸/四角）・サイズを調整</li>
                    <li>3種類の吹き出しスタイル（標準・丸みを帯びた・考え事）</li>
                    <li>吹き出しの背景色・文字色・枠線をカスタマイズ</li>
                    <li>アバタープリセット機能（設定を名前を付けて保存・呼び出し可能）</li>
                </ul>
            </div>
        </div>

        <div class="ksb-about-section">
            <h2>主な特徴</h2>

            <div class="ksb-feature-grid">
                <div class="ksb-feature-item">
                    <strong>直感的な操作</strong><br>
                    すべての設定はサイドバーパネルから視覚的に行えます。コードの知識は不要です。
                </div>
                <div class="ksb-feature-item">
                    <strong>豊富なカスタマイズ</strong><br>
                    色・サイズ・余白・影など、細部まで調整可能。サイトのデザインに合わせられます。
                </div>
                <div class="ksb-feature-item">
                    <strong>レスポンシブ対応</strong><br>
                    PC・タブレット・スマートフォンすべてで美しく表示されます。
                </div>
                <div class="ksb-feature-item">
                    <strong>軽量・高速</strong><br>
                    必要最小限のCSSとJavaScriptで動作。ページ表示速度に影響を与えません。
                </div>
                <div class="ksb-feature-item">
                    <strong>SEO対応</strong><br>
                    セマンティックなHTML構造を採用。検索エンジンに適切に解釈されます。
                </div>
                <div class="ksb-feature-item">
                    <strong>専用カテゴリー</strong><br>
                    ブロック挿入時に「Kashiwazaki SEO Blocks」カテゴリーでまとめて表示されます。
                </div>
            </div>
        </div>

        <div class="ksb-about-section">
            <h2>使い方</h2>

            <h3>基本的な使い方</h3>
            <ol>
                <li><strong>ブロックの追加:</strong> 投稿や固定ページの編集画面でブロック追加ボタン（＋）をクリック</li>
                <li><strong>ブロックの選択:</strong> 「Kashiwazaki SEO Blocks」カテゴリーから使用したいブロックを選択</li>
                <li><strong>コンテンツの入力:</strong> ブロック内にテキストや画像を入力</li>
                <li><strong>スタイルの調整:</strong> 右側のサイドバーで色・サイズ・余白などを設定</li>
                <li><strong>プレビュー確認:</strong> エディタ上でリアルタイムにプレビューを確認</li>
            </ol>

            <h3>吹き出しブロックのプリセット機能</h3>
            <p>吹き出しブロックでは、アバター設定（画像・名前・肩書など）を保存して再利用できます。</p>
            <ol>
                <li>吹き出しブロックを選択し、アバター設定を行う</li>
                <li>サイドバーの「アバタープリセット」パネルで「現在の設定を保存」をクリック</li>
                <li>プリセット名を入力して保存</li>
                <li>次回からはドロップダウンからプリセットを選ぶだけで設定が適用されます</li>
            </ol>
        </div>

        <div class="ksb-about-section">
            <h2>バージョン情報</h2>
            <table class="ksb-version-table">
                <tr>
                    <th>プラグインバージョン</th>
                    <td><?php echo KSB_PLUGIN_VERSION; ?></td>
                </tr>
                <tr>
                    <th>対応WordPress</th>
                    <td>6.0 以上</td>
                </tr>
                <tr>
                    <th>対応PHP</th>
                    <td>7.4 以上</td>
                </tr>
                <tr>
                    <th>ライセンス</th>
                    <td>GPL v2 or later</td>
                </tr>
            </table>
        </div>

        <div class="ksb-about-section">
            <h2>サポート・お問い合わせ</h2>
            <p>ご質問やご要望がございましたら、下記よりお問い合わせください。</p>
            <p><a href="https://www.tsuyoshikashiwazaki.jp" target="_blank" class="button button-secondary">公式サイトへ</a></p>
        </div>
        <?php
    }

    private function render_usage_tab() {
        $blocks = [
            'heading-bar' => ['name' => '見出しバー', 'icon' => 'heading', 'color' => '#2271b1'],
            'button' => ['name' => 'ボタン', 'icon' => 'button', 'color' => '#00a32a'],
            'box' => ['name' => '汎用ボックス', 'icon' => 'info-outline', 'color' => '#dba617'],
            'border' => ['name' => 'ボーダー', 'icon' => 'minus', 'color' => '#9e9e9e'],
            'table' => ['name' => 'テーブル', 'icon' => 'editor-table', 'color' => '#8c5eea'],
            'speech-bubble' => ['name' => '吹き出し', 'icon' => 'format-chat', 'color' => '#e65054']
        ];

        $usage_data = [];
        $total_usage = 0;
        $total_posts = 0;
        $posts_with_blocks = [];

        foreach ($blocks as $block_slug => $block_info) {
            $posts = $this->get_posts_using_block('kashiwazaki-seo-blocks/' . $block_slug);
            $usage_data[$block_slug] = $posts;
            $total_usage += count($posts);
            foreach ($posts as $post) {
                $posts_with_blocks[$post->ID] = true;
            }
        }
        $total_posts = count($posts_with_blocks);

        ?>
        <style>
            .ksb-usage-summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 15px;
                margin-bottom: 30px;
            }
            .ksb-summary-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                padding: 20px;
                text-align: center;
                transition: box-shadow 0.2s;
            }
            .ksb-summary-card:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .ksb-summary-card.total {
                background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
                color: #fff;
                grid-column: span 2;
            }
            .ksb-summary-number {
                font-size: 2.5em;
                font-weight: bold;
                line-height: 1;
                margin-bottom: 5px;
            }
            .ksb-summary-label {
                font-size: 0.9em;
                opacity: 0.8;
            }
            .ksb-summary-card:not(.total) .ksb-summary-number {
                color: #1d2327;
            }
            .ksb-block-accordion {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                margin-bottom: 12px;
                overflow: hidden;
            }
            .ksb-block-header {
                display: flex;
                align-items: center;
                padding: 15px 20px;
                cursor: pointer;
                background: #fff;
                transition: background 0.2s;
                user-select: none;
            }
            .ksb-block-header:hover {
                background: #f6f7f7;
            }
            .ksb-block-icon {
                width: 40px;
                height: 40px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 15px;
                flex-shrink: 0;
            }
            .ksb-block-icon .dashicons {
                color: #fff;
                font-size: 20px;
                width: 20px;
                height: 20px;
            }
            .ksb-block-info {
                flex-grow: 1;
            }
            .ksb-block-name {
                font-weight: 600;
                font-size: 1.1em;
                color: #1d2327;
                margin-bottom: 2px;
            }
            .ksb-block-count {
                font-size: 0.85em;
                color: #646970;
            }
            .ksb-block-badge {
                display: inline-flex;
                align-items: center;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.85em;
                font-weight: 500;
                margin-right: 10px;
            }
            .ksb-block-badge.active {
                background: #e6f4ea;
                color: #137333;
            }
            .ksb-block-badge.inactive {
                background: #f0f0f1;
                color: #646970;
            }
            .ksb-block-toggle {
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.2s;
            }
            .ksb-block-accordion.open .ksb-block-toggle {
                transform: rotate(180deg);
            }
            .ksb-block-content {
                display: none;
                border-top: 1px solid #e0e0e0;
                padding: 0;
            }
            .ksb-block-accordion.open .ksb-block-content {
                display: block;
            }
            .ksb-post-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .ksb-post-item {
                display: flex;
                align-items: center;
                padding: 12px 20px;
                border-bottom: 1px solid #f0f0f1;
                transition: background 0.2s;
            }
            .ksb-post-item:last-child {
                border-bottom: none;
            }
            .ksb-post-item:hover {
                background: #f6f7f7;
            }
            .ksb-post-title {
                flex-grow: 1;
                font-weight: 500;
                color: #1d2327;
            }
            .ksb-post-title a {
                text-decoration: none;
                color: inherit;
            }
            .ksb-post-title a:hover {
                color: #2271b1;
            }
            .ksb-post-meta {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-shrink: 0;
            }
            .ksb-meta-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 0.75em;
                font-weight: 500;
            }
            .ksb-meta-badge.type {
                background: #e7f3ff;
                color: #2271b1;
            }
            .ksb-meta-badge.status-publish {
                background: #e6f4ea;
                color: #137333;
            }
            .ksb-meta-badge.status-draft {
                background: #fff3cd;
                color: #856404;
            }
            .ksb-meta-badge.status-pending {
                background: #fff3cd;
                color: #856404;
            }
            .ksb-meta-badge.status-private {
                background: #f0f0f1;
                color: #646970;
            }
            .ksb-meta-badge.status-future {
                background: #e7f3ff;
                color: #2271b1;
            }
            .ksb-post-date {
                color: #646970;
                font-size: 0.85em;
                min-width: 90px;
                text-align: right;
            }
            .ksb-post-actions {
                display: flex;
                gap: 8px;
                margin-left: 15px;
            }
            .ksb-post-actions a {
                color: #646970;
                text-decoration: none;
                font-size: 0.85em;
            }
            .ksb-post-actions a:hover {
                color: #2271b1;
            }
            .ksb-empty-message {
                padding: 30px 20px;
                text-align: center;
                color: #646970;
            }
            .ksb-empty-message .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #c3c4c7;
                margin-bottom: 10px;
            }
            .ksb-post-list-header {
                padding: 12px 20px;
                background: #f6f7f7;
                border-bottom: 1px solid #e0e0e0;
                color: #1d2327;
            }
            /* 一括変換セクション */
            .ksb-bulk-convert-section {
                background: linear-gradient(135deg, #fef8e7 0%, #fff4d4 100%);
                border-bottom: 1px solid #e0e0e0;
                padding: 20px;
            }
            .ksb-bulk-convert-header {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 15px;
                color: #1d2327;
            }
            .ksb-bulk-convert-header .dashicons {
                color: #dba617;
            }
            .ksb-bulk-convert-notice {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 12px 15px;
                background: #fff;
                border-radius: 6px;
                color: #646970;
                font-size: 0.9em;
                line-height: 1.5;
            }
            .ksb-bulk-convert-notice .dashicons {
                color: #72aee6;
                flex-shrink: 0;
                margin-top: 2px;
            }
            .ksb-bulk-convert-form {
                background: #fff;
                border-radius: 8px;
                padding: 20px;
            }
            .ksb-bulk-convert-row {
                margin-bottom: 10px;
            }
            .ksb-bulk-convert-row label {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .ksb-label-text {
                font-weight: 500;
                color: #1d2327;
                font-size: 0.9em;
            }
            .ksb-preset-select {
                width: 100%;
                max-width: 400px;
                padding: 8px 12px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                font-size: 14px;
            }
            .ksb-bulk-convert-arrow {
                text-align: center;
                padding: 5px 0;
                color: #646970;
            }
            .ksb-bulk-convert-preview {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 15px;
                background: #f0f6fc;
                border-radius: 6px;
                margin: 15px 0;
            }
            .ksb-bulk-convert-preview img {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .ksb-bulk-convert-preview span {
                font-weight: 500;
                color: #1d2327;
            }
            .ksb-bulk-convert-targets {
                margin: 15px 0;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                overflow: hidden;
            }
            .ksb-targets-header {
                padding: 10px 15px;
                background: #f0f0f1;
                border-bottom: 1px solid #e0e0e0;
            }
            .ksb-targets-header label {
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                font-weight: 500;
            }
            .ksb-targets-list {
                max-height: 200px;
                overflow-y: auto;
            }
            .ksb-target-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 15px;
                border-bottom: 1px solid #f0f0f1;
                cursor: pointer;
                transition: background 0.15s;
            }
            .ksb-target-item:last-child {
                border-bottom: none;
            }
            .ksb-target-item:hover {
                background: #f6f7f7;
            }
            .ksb-target-title {
                flex-grow: 1;
                font-size: 0.9em;
            }
            .ksb-target-meta {
                font-size: 0.75em;
                color: #646970;
                background: #f0f0f1;
                padding: 2px 8px;
                border-radius: 3px;
            }
            .ksb-bulk-convert-actions {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-top: 15px;
            }
            .ksb-bulk-convert-actions .button {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .ksb-bulk-convert-actions .button .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .ksb-convert-status {
                font-size: 0.9em;
            }
            .ksb-convert-status.success {
                color: #00a32a;
            }
            .ksb-convert-status.error {
                color: #d63638;
            }
            .ksb-convert-status.loading {
                color: #646970;
            }
            /* プリセット管理セクション */
            .ksb-preset-manager-section {
                padding: 20px;
                background: #f0f6fc;
                border-bottom: 1px solid #e0e0e0;
            }
            .ksb-preset-manager-header {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 15px;
            }
            .ksb-preset-manager-header .dashicons {
                color: #2271b1;
            }
            .ksb-preset-manager-header strong {
                flex-grow: 1;
            }
            .ksb-add-preset-btn {
                display: inline-flex !important;
                align-items: center;
                gap: 4px;
            }
            .ksb-add-preset-btn .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .ksb-preset-list {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 12px;
            }
            .ksb-preset-empty {
                grid-column: 1 / -1;
                text-align: center;
                padding: 30px 20px;
                background: #fff;
                border-radius: 6px;
                color: #646970;
            }
            .ksb-preset-empty .dashicons {
                font-size: 32px;
                width: 32px;
                height: 32px;
                color: #c3c4c7;
                margin-bottom: 10px;
            }
            .ksb-preset-empty p {
                margin: 0;
                line-height: 1.6;
            }
            .ksb-preset-card {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 15px;
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                transition: box-shadow 0.2s;
            }
            .ksb-preset-card:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }
            .ksb-preset-avatar {
                flex-shrink: 0;
            }
            .ksb-preset-avatar img {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                object-fit: cover;
            }
            .ksb-preset-avatar-placeholder {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                background: #e0e0e0;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .ksb-preset-avatar-placeholder .dashicons {
                color: #999;
            }
            .ksb-preset-info {
                flex-grow: 1;
                min-width: 0;
            }
            .ksb-preset-name {
                font-weight: 600;
                color: #1d2327;
                margin-bottom: 2px;
            }
            .ksb-preset-details {
                font-size: 0.85em;
                color: #646970;
            }
            .ksb-preset-speaker {
                margin-right: 8px;
            }
            .ksb-preset-title-text {
                opacity: 0.8;
            }
            .ksb-preset-actions {
                display: flex;
                gap: 5px;
                flex-shrink: 0;
            }
            .ksb-preset-actions .button {
                padding: 0 6px;
                min-height: 28px;
            }
            .ksb-preset-actions .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            /* プリセット編集フォーム */
            .ksb-preset-form-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 100000;
            }
            .ksb-preset-form {
                background: #fff;
                border-radius: 8px;
                width: 90%;
                max-width: 500px;
                max-height: 90vh;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            }
            .ksb-preset-form-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 15px 20px;
                border-bottom: 1px solid #e0e0e0;
                background: #f6f7f7;
            }
            .ksb-preset-form-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #646970;
                padding: 0;
                line-height: 1;
            }
            .ksb-preset-form-close:hover {
                color: #d63638;
            }
            .ksb-preset-form-body {
                padding: 20px;
                overflow-y: auto;
                flex-grow: 1;
            }
            .ksb-form-row {
                margin-bottom: 15px;
            }
            .ksb-form-row label {
                display: block;
                font-weight: 500;
                margin-bottom: 5px;
                color: #1d2327;
            }
            .ksb-form-row label .required {
                color: #d63638;
            }
            .ksb-form-row input[type="text"],
            .ksb-form-row input[type="url"],
            .ksb-form-row input[type="number"],
            .ksb-form-row select {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
            }
            .ksb-form-row input[type="color"] {
                width: 60px;
                height: 36px;
                padding: 2px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                cursor: pointer;
            }
            .ksb-form-row-group {
                display: flex;
                gap: 15px;
                margin-bottom: 15px;
            }
            .ksb-form-row-half {
                flex: 1;
                margin-bottom: 0;
            }
            .ksb-avatar-upload {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            .ksb-avatar-preview {
                width: 64px;
                height: 64px;
                border-radius: 50%;
                background: #e0e0e0;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                flex-shrink: 0;
            }
            .ksb-avatar-preview img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .ksb-avatar-preview .dashicons {
                font-size: 32px;
                width: 32px;
                height: 32px;
                color: #999;
            }
            .ksb-remove-avatar-btn {
                color: #d63638 !important;
            }
            .ksb-preset-form-footer {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                padding: 15px 20px;
                border-top: 1px solid #e0e0e0;
                background: #f6f7f7;
            }
            @media screen and (max-width: 782px) {
                .ksb-usage-summary {
                    grid-template-columns: repeat(2, 1fr);
                }
                .ksb-summary-card.total {
                    grid-column: span 2;
                }
                .ksb-post-item {
                    flex-wrap: wrap;
                    gap: 8px;
                }
                .ksb-post-meta {
                    width: 100%;
                    justify-content: flex-start;
                }
                .ksb-post-actions {
                    margin-left: 0;
                }
            }
        </style>

        <h2>ブロック使用状況</h2>
        <p>サイト内でKashiwazaki SEO Blocksがどのように使用されているか確認できます。</p>

        <div class="ksb-usage-summary">
            <div class="ksb-summary-card total">
                <div class="ksb-summary-number"><?php echo $total_posts; ?></div>
                <div class="ksb-summary-label">記事でブロックを使用中</div>
            </div>
            <?php foreach ($blocks as $block_slug => $block_info) : ?>
                <div class="ksb-summary-card">
                    <div class="ksb-summary-number"><?php echo count($usage_data[$block_slug]); ?></div>
                    <div class="ksb-summary-label"><?php echo esc_html($block_info['name']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <h3 style="margin-bottom: 15px;">ブロック別詳細</h3>

        <?php
        $presets = get_option(self::PRESETS_OPTION_KEY, []);
        $button_presets = get_option(self::BUTTON_PRESETS_OPTION_KEY, []);
        $box_presets = get_option(self::BOX_PRESETS_OPTION_KEY, []);
        foreach ($blocks as $block_slug => $block_info) :
            $posts = $usage_data[$block_slug];
            $has_posts = !empty($posts);
            $is_speech_bubble = ($block_slug === 'speech-bubble');
            $is_button = ($block_slug === 'button');
            $is_box = ($block_slug === 'box');
        ?>
            <div class="ksb-block-accordion<?php echo $has_posts ? '' : ''; ?>" data-block-slug="<?php echo esc_attr($block_slug); ?>">
                <div class="ksb-block-header" onclick="this.parentElement.classList.toggle('open')">
                    <div class="ksb-block-icon" style="background: <?php echo esc_attr($block_info['color']); ?>;">
                        <span class="dashicons dashicons-<?php echo esc_attr($block_info['icon']); ?>"></span>
                    </div>
                    <div class="ksb-block-info">
                        <div class="ksb-block-name"><?php echo esc_html($block_info['name']); ?></div>
                        <div class="ksb-block-count">
                            <?php if ($has_posts) : ?>
                                <?php echo count($posts); ?>件の記事で使用中
                            <?php else : ?>
                                使用なし
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="ksb-block-badge <?php echo $has_posts ? 'active' : 'inactive'; ?>">
                        <?php echo $has_posts ? '使用中' : '未使用'; ?>
                    </span>
                    <div class="ksb-block-toggle">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                </div>

                <div class="ksb-block-content">
                    <?php if ($is_speech_bubble) : ?>
                        <!-- プリセット管理UI -->
                        <div class="ksb-preset-manager-section">
                            <div class="ksb-preset-manager-header">
                                <span class="dashicons dashicons-admin-users"></span>
                                <strong>アバタープリセット管理</strong>
                                <button type="button" class="button button-small ksb-add-preset-btn" id="ksb-add-preset-btn">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    新規追加
                                </button>
                            </div>

                            <!-- プリセット一覧 -->
                            <div class="ksb-preset-list" id="ksb-preset-list">
                                <?php if (empty($presets)) : ?>
                                    <div class="ksb-preset-empty" id="ksb-preset-empty">
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <p>プリセットがまだ登録されていません。<br>「新規追加」ボタンからプリセットを登録してください。</p>
                                    </div>
                                <?php else : ?>
                                    <?php foreach ($presets as $preset) : ?>
                                        <div class="ksb-preset-card" data-preset-id="<?php echo esc_attr($preset['id']); ?>">
                                            <div class="ksb-preset-avatar">
                                                <?php if (!empty($preset['avatarUrl'])) : ?>
                                                    <img src="<?php echo esc_url($preset['avatarUrl']); ?>" alt="<?php echo esc_attr($preset['name']); ?>" />
                                                <?php else : ?>
                                                    <div class="ksb-preset-avatar-placeholder">
                                                        <span class="dashicons dashicons-admin-users"></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ksb-preset-info">
                                                <div class="ksb-preset-name"><?php echo esc_html($preset['name']); ?></div>
                                                <div class="ksb-preset-details">
                                                    <?php if (!empty($preset['speakerName'])) : ?>
                                                        <span class="ksb-preset-speaker"><?php echo esc_html($preset['speakerName']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($preset['speakerTitle'])) : ?>
                                                        <span class="ksb-preset-title-text"><?php echo esc_html($preset['speakerTitle']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="ksb-preset-actions">
                                                <button type="button" class="button button-small ksb-edit-preset" data-id="<?php echo esc_attr($preset['id']); ?>">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </button>
                                                <button type="button" class="button button-small ksb-delete-preset" data-id="<?php echo esc_attr($preset['id']); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- プリセット編集フォーム（モーダル的に表示） -->
                            <div class="ksb-preset-form-overlay" id="ksb-preset-form-overlay" style="display: none;">
                                <div class="ksb-preset-form">
                                    <div class="ksb-preset-form-header">
                                        <strong id="ksb-preset-form-title">プリセットを追加</strong>
                                        <button type="button" class="ksb-preset-form-close" id="ksb-preset-form-close">&times;</button>
                                    </div>
                                    <div class="ksb-preset-form-body">
                                        <input type="hidden" id="ksb-edit-preset-id" value="" />

                                        <div class="ksb-form-row">
                                            <label for="ksb-preset-name-input">プリセット名 <span class="required">*</span></label>
                                            <input type="text" id="ksb-preset-name-input" placeholder="例: 管理人、ゲスト、専門家" required />
                                        </div>

                                        <div class="ksb-form-row">
                                            <label>アバター画像</label>
                                            <div class="ksb-avatar-upload">
                                                <div class="ksb-avatar-preview" id="ksb-avatar-preview">
                                                    <span class="dashicons dashicons-admin-users"></span>
                                                </div>
                                                <input type="hidden" id="ksb-avatar-url-input" value="" />
                                                <input type="hidden" id="ksb-avatar-id-input" value="0" />
                                                <button type="button" class="button" id="ksb-select-avatar-btn">画像を選択</button>
                                                <button type="button" class="button ksb-remove-avatar-btn" id="ksb-remove-avatar-btn" style="display: none;">削除</button>
                                            </div>
                                        </div>

                                        <div class="ksb-form-row">
                                            <label for="ksb-speaker-name-input">話者の名前</label>
                                            <input type="text" id="ksb-speaker-name-input" placeholder="吹き出しに表示する名前" />
                                        </div>

                                        <div class="ksb-form-row">
                                            <label for="ksb-speaker-title-input">肩書</label>
                                            <input type="text" id="ksb-speaker-title-input" placeholder="例: SEO専門家、ライター" />
                                        </div>

                                        <div class="ksb-form-row">
                                            <label for="ksb-profile-url-input">プロフィールURL</label>
                                            <input type="url" id="ksb-profile-url-input" placeholder="https://..." />
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-avatar-position-input">位置</label>
                                                <select id="ksb-avatar-position-input">
                                                    <option value="left">左</option>
                                                    <option value="right">右</option>
                                                </select>
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-avatar-shape-input">形状</label>
                                                <select id="ksb-avatar-shape-input">
                                                    <option value="circle">円形</option>
                                                    <option value="rounded">角丸</option>
                                                    <option value="square">四角</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-avatar-size-input">アバターサイズ (px)</label>
                                                <input type="number" id="ksb-avatar-size-input" value="60" min="40" max="120" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-name-color-input">名前の色</label>
                                                <input type="color" id="ksb-name-color-input" value="#007cba" />
                                            </div>
                                        </div>

                                        <hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;" />
                                        <p style="margin: 0 0 10px; font-weight: bold; color: #333;">吹き出しスタイル</p>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-bubble-style-input">スタイル</label>
                                                <select id="ksb-bubble-style-input">
                                                    <option value="standard">標準</option>
                                                    <option value="think">もくもく（考え中）</option>
                                                    <option value="shout">ギザギザ（叫び）</option>
                                                </select>
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-bubble-max-width-input">最大幅 (%)</label>
                                                <input type="number" id="ksb-bubble-max-width-input" value="100" min="50" max="100" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-bubble-color-input">吹き出し背景色</label>
                                                <input type="color" id="ksb-bubble-color-input" value="#f0f8ff" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-text-color-input">テキスト色</label>
                                                <input type="color" id="ksb-text-color-input" value="#333333" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-border-color-input">枠線色</label>
                                                <input type="color" id="ksb-border-color-input" value="#007cba" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-border-width-input">枠線幅 (px)</label>
                                                <input type="number" id="ksb-border-width-input" value="2" min="0" max="10" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row">
                                            <label>
                                                <input type="checkbox" id="ksb-show-tail-input" checked />
                                                しっぽを表示
                                            </label>
                                        </div>

                                        <div class="ksb-form-row" style="margin-top: 15px;">
                                            <label>プレビュー:</label>
                                            <div style="padding: 15px; background: #f5f5f5; border-radius: 6px;">
                                                <div id="ksb-speech-bubble-preview" style="display: flex; align-items: flex-start; gap: 10px;">
                                                    <div id="ksb-preview-avatar" style="width: 50px; height: 50px; border-radius: 50%; background: #ddd; flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                                        <span class="dashicons dashicons-admin-users" style="font-size: 24px; color: #999;"></span>
                                                    </div>
                                                    <div>
                                                        <div id="ksb-preview-name" style="font-size: 12px; color: #007cba; margin-bottom: 4px;"></div>
                                                        <div id="ksb-preview-bubble" style="background: #f0f8ff; border: 2px solid #007cba; border-radius: 10px; padding: 10px; font-size: 12px; color: #333; position: relative;">
                                                            サンプルテキスト
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ksb-preset-form-footer">
                                        <button type="button" class="button" id="ksb-preset-cancel-btn">キャンセル</button>
                                        <button type="button" class="button button-primary" id="ksb-preset-save-btn">保存</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($has_posts) : ?>
                        <!-- 吹き出し一括変換UI -->
                        <div class="ksb-bulk-convert-section">
                            <div class="ksb-bulk-convert-header">
                                <span class="dashicons dashicons-update"></span>
                                <strong>アバター一括変換</strong>
                            </div>
                            <?php if (empty($presets)) : ?>
                                <div class="ksb-bulk-convert-notice" id="ksb-bulk-convert-notice">
                                    <span class="dashicons dashicons-info"></span>
                                    上の「新規追加」ボタンからプリセットを登録すると、一括変換が使えるようになります。
                                </div>
                            <?php else : ?>
                                <div class="ksb-bulk-convert-form">
                                    <div class="ksb-bulk-convert-row">
                                        <label>
                                            <span class="ksb-label-text">変換元（任意）:</span>
                                            <select id="ksb-from-preset" class="ksb-preset-select">
                                                <option value="">すべての吹き出し</option>
                                                <?php foreach ($presets as $preset) : ?>
                                                    <option value="<?php echo esc_attr($preset['id']); ?>">
                                                        <?php echo esc_html($preset['name']); ?>
                                                        <?php if (!empty($preset['speakerName'])) : ?>
                                                            (<?php echo esc_html($preset['speakerName']); ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="ksb-bulk-convert-arrow">
                                        <span class="dashicons dashicons-arrow-down-alt"></span>
                                    </div>
                                    <div class="ksb-bulk-convert-row">
                                        <label>
                                            <span class="ksb-label-text">変換先:</span>
                                            <select id="ksb-to-preset" class="ksb-preset-select" required>
                                                <option value="">-- 選択してください --</option>
                                                <?php foreach ($presets as $preset) : ?>
                                                    <option value="<?php echo esc_attr($preset['id']); ?>"
                                                            data-avatar-url="<?php echo esc_attr($preset['avatarUrl'] ?? ''); ?>"
                                                            data-speaker-name="<?php echo esc_attr($preset['speakerName'] ?? ''); ?>"
                                                            data-speaker-title="<?php echo esc_attr($preset['speakerTitle'] ?? ''); ?>"
                                                            data-avatar-shape="<?php echo esc_attr($preset['avatarShape'] ?? 'circle'); ?>"
                                                            data-name-label-color="<?php echo esc_attr($preset['nameLabelColor'] ?? '#007cba'); ?>"
                                                            data-bubble-color="<?php echo esc_attr($preset['bubbleColor'] ?? '#f0f8ff'); ?>"
                                                            data-text-color="<?php echo esc_attr($preset['textColor'] ?? '#333333'); ?>"
                                                            data-border-color="<?php echo esc_attr($preset['borderColor'] ?? '#007cba'); ?>">
                                                        <?php echo esc_html($preset['name']); ?>
                                                        <?php if (!empty($preset['speakerName'])) : ?>
                                                            (<?php echo esc_html($preset['speakerName']); ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="ksb-bulk-convert-preview" id="ksb-bulk-convert-preview" style="display: none; align-items: flex-start; gap: 15px; padding: 15px; background: #f9f9f9; border-radius: 8px; margin: 15px 0;">
                                        <div id="ksb-bulk-preview-avatar-wrap" style="text-align: center;">
                                            <img id="ksb-bulk-preview-avatar" src="" alt="Preview" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; display: block;" />
                                            <div id="ksb-bulk-preview-name" style="font-weight: bold; font-size: 12px; margin-top: 5px;"></div>
                                        </div>
                                        <div id="ksb-bulk-preview-bubble" style="flex: 1; padding: 12px 16px; border-radius: 10px; background-color: #f0f8ff; border: 2px solid #007cba; color: #333;">
                                            <span style="opacity: 0.7;">サンプルテキスト</span>
                                        </div>
                                    </div>
                                    <div class="ksb-bulk-convert-targets">
                                        <div class="ksb-targets-header">
                                            <label>
                                                <input type="checkbox" id="ksb-select-all-posts" checked />
                                                <span>対象記事を選択 (<?php echo count($posts); ?>件)</span>
                                            </label>
                                        </div>
                                        <div class="ksb-targets-list">
                                            <?php foreach ($posts as $post) : ?>
                                                <label class="ksb-target-item">
                                                    <input type="checkbox" class="ksb-post-checkbox" value="<?php echo esc_attr($post->ID); ?>" checked />
                                                    <span class="ksb-target-title"><?php echo esc_html($post->post_title ? $post->post_title : '(タイトルなし)'); ?></span>
                                                    <span class="ksb-target-meta"><?php echo esc_html($this->get_post_type_label($post->post_type)); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="ksb-bulk-convert-actions">
                                        <button type="button" id="ksb-bulk-convert-btn" class="button button-primary" disabled>
                                            <span class="dashicons dashicons-update"></span>
                                            一括変換を実行
                                        </button>
                                        <span class="ksb-convert-status" id="ksb-convert-status"></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($is_button) : ?>
                        <!-- ボタンプリセット管理UI -->
                        <div class="ksb-preset-manager-section" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);">
                            <div class="ksb-preset-manager-header">
                                <span class="dashicons dashicons-button" style="color: #00a32a;"></span>
                                <strong>ボタンスタイルプリセット管理</strong>
                                <button type="button" class="button button-small ksb-add-btn-preset-btn" id="ksb-add-btn-preset-btn">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    新規追加
                                </button>
                            </div>

                            <div class="ksb-preset-list" id="ksb-btn-preset-list">
                                <?php if (empty($button_presets)) : ?>
                                    <div class="ksb-preset-empty" id="ksb-btn-preset-empty">
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <p>プリセットがまだ登録されていません。<br>「新規追加」ボタンからスタイルを登録してください。</p>
                                    </div>
                                <?php else : ?>
                                    <?php foreach ($button_presets as $preset) : ?>
                                        <div class="ksb-preset-card ksb-btn-preset-card" data-preset-id="<?php echo esc_attr($preset['id']); ?>">
                                            <div class="ksb-btn-preset-preview">
                                                <span style="
                                                    display: inline-block;
                                                    padding: 8px 16px;
                                                    background-color: <?php echo esc_attr($preset['backgroundColor'] ?: '#007cba'); ?>;
                                                    color: <?php echo esc_attr($preset['textColor'] ?: '#ffffff'); ?>;
                                                    border: <?php echo esc_attr($preset['borderWidth'] ?: '0'); ?>px solid <?php echo esc_attr($preset['borderColor'] ?: 'transparent'); ?>;
                                                    border-radius: <?php echo esc_attr($preset['borderRadius'] ?: '5'); ?>px;
                                                    font-size: 13px;
                                                    <?php echo !empty($preset['hasShadow']) ? 'box-shadow: 0 2px 5px rgba(0,0,0,0.3);' : ''; ?>
                                                "><?php echo esc_html($preset['name']); ?></span>
                                            </div>
                                            <div class="ksb-preset-info">
                                                <div class="ksb-preset-name"><?php echo esc_html($preset['name']); ?></div>
                                                <div class="ksb-preset-details">
                                                    <span>背景: <?php echo esc_html($preset['backgroundColor'] ?: '#007cba'); ?></span>
                                                    <span>角丸: <?php echo esc_attr($preset['borderRadius'] ?: '5'); ?>px</span>
                                                </div>
                                            </div>
                                            <div class="ksb-preset-actions">
                                                <button type="button" class="button button-small ksb-edit-btn-preset" data-id="<?php echo esc_attr($preset['id']); ?>">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </button>
                                                <button type="button" class="button button-small ksb-delete-btn-preset" data-id="<?php echo esc_attr($preset['id']); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- ボタンプリセット編集フォーム -->
                            <div class="ksb-preset-form-overlay" id="ksb-btn-preset-form-overlay" style="display: none;">
                                <div class="ksb-preset-form">
                                    <div class="ksb-preset-form-header">
                                        <strong id="ksb-btn-preset-form-title">ボタンプリセットを追加</strong>
                                        <button type="button" class="ksb-preset-form-close" id="ksb-btn-preset-form-close">&times;</button>
                                    </div>
                                    <div class="ksb-preset-form-body">
                                        <input type="hidden" id="ksb-edit-btn-preset-id" value="" />

                                        <div class="ksb-form-row">
                                            <label for="ksb-btn-preset-name-input">プリセット名 <span class="required">*</span></label>
                                            <input type="text" id="ksb-btn-preset-name-input" placeholder="例: プライマリ、セカンダリ、CTA" required />
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-btn-bg-color-input">背景色</label>
                                                <input type="color" id="ksb-btn-bg-color-input" value="#007cba" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-btn-text-color-input">文字色</label>
                                                <input type="color" id="ksb-btn-text-color-input" value="#ffffff" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-btn-border-color-input">枠線色</label>
                                                <input type="color" id="ksb-btn-border-color-input" value="#007cba" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-btn-border-width-input">枠線幅 (px)</label>
                                                <input type="number" id="ksb-btn-border-width-input" value="0" min="0" max="10" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-btn-border-radius-input">角丸 (px)</label>
                                                <input type="number" id="ksb-btn-border-radius-input" value="5" min="0" max="50" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-btn-font-size-input">文字サイズ (px)</label>
                                                <input type="number" id="ksb-btn-font-size-input" value="16" min="10" max="32" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-btn-padding-v-input">上下余白 (px)</label>
                                                <input type="number" id="ksb-btn-padding-v-input" value="12" min="0" max="50" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-btn-padding-h-input">左右余白 (px)</label>
                                                <input type="number" id="ksb-btn-padding-h-input" value="24" min="0" max="100" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-btn-width-input">幅</label>
                                                <select id="ksb-btn-width-input">
                                                    <option value="auto">自動</option>
                                                    <option value="full">全幅</option>
                                                </select>
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label>
                                                    <input type="checkbox" id="ksb-btn-shadow-input" />
                                                    影を付ける
                                                </label>
                                            </div>
                                        </div>

                                        <div class="ksb-form-row" style="margin-top: 15px;">
                                            <label>プレビュー:</label>
                                            <div style="padding: 15px; background: #f5f5f5; border-radius: 6px; text-align: center;">
                                                <span id="ksb-btn-preview" style="display: inline-block; padding: 12px 24px; background-color: #007cba; color: #fff; border-radius: 5px; font-size: 16px;">ボタン</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ksb-preset-form-footer">
                                        <button type="button" class="button" id="ksb-btn-preset-cancel-btn">キャンセル</button>
                                        <button type="button" class="button button-primary" id="ksb-btn-preset-save-btn">保存</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($has_posts && !empty($button_presets)) : ?>
                        <!-- ボタン一括変換UI -->
                        <div class="ksb-bulk-convert-section">
                            <div class="ksb-bulk-convert-header">
                                <span class="dashicons dashicons-update"></span>
                                <strong>スタイル一括変換</strong>
                            </div>
                            <div class="ksb-bulk-convert-form">
                                <div class="ksb-bulk-convert-row">
                                    <label>
                                        <span class="ksb-label-text">変換先スタイル:</span>
                                        <select id="ksb-btn-to-preset" class="ksb-preset-select" required>
                                            <option value="">-- 選択してください --</option>
                                            <?php foreach ($button_presets as $preset) : ?>
                                                <option value="<?php echo esc_attr($preset['id']); ?>"
                                                        data-bg-color="<?php echo esc_attr($preset['backgroundColor']); ?>"
                                                        data-text-color="<?php echo esc_attr($preset['textColor']); ?>">
                                                    <?php echo esc_html($preset['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <div class="ksb-bulk-convert-preview" id="ksb-btn-convert-preview" style="display: none;">
                                    <span id="ksb-btn-convert-preview-btn" style="display: inline-block; padding: 8px 16px; border-radius: 5px;"></span>
                                </div>
                                <div class="ksb-bulk-convert-targets">
                                    <div class="ksb-targets-header">
                                        <label>
                                            <input type="checkbox" id="ksb-btn-select-all-posts" checked />
                                            <span>対象記事を選択 (<?php echo count($posts); ?>件)</span>
                                        </label>
                                    </div>
                                    <div class="ksb-targets-list">
                                        <?php foreach ($posts as $post) : ?>
                                            <label class="ksb-target-item">
                                                <input type="checkbox" class="ksb-btn-post-checkbox" value="<?php echo esc_attr($post->ID); ?>" checked />
                                                <span class="ksb-target-title"><?php echo esc_html($post->post_title ? $post->post_title : '(タイトルなし)'); ?></span>
                                                <span class="ksb-target-meta"><?php echo esc_html($this->get_post_type_label($post->post_type)); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="ksb-bulk-convert-actions">
                                    <button type="button" id="ksb-btn-bulk-convert-btn" class="button button-primary" disabled>
                                        <span class="dashicons dashicons-update"></span>
                                        一括変換を実行
                                    </button>
                                    <span class="ksb-convert-status" id="ksb-btn-convert-status"></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($is_box) : ?>
                        <!-- ボックスプリセット管理UI -->
                        <div class="ksb-preset-manager-section" style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);">
                            <div class="ksb-preset-manager-header">
                                <span class="dashicons dashicons-info-outline" style="color: #dba617;"></span>
                                <strong>ボックススタイルプリセット管理</strong>
                                <button type="button" class="button button-small ksb-add-box-preset-btn" id="ksb-add-box-preset-btn">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    新規追加
                                </button>
                            </div>

                            <div class="ksb-preset-list" id="ksb-box-preset-list">
                                <?php if (empty($box_presets)) : ?>
                                    <div class="ksb-preset-empty" id="ksb-box-preset-empty">
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <p>プリセットがまだ登録されていません。<br>「新規追加」ボタンからスタイルを登録してください。</p>
                                    </div>
                                <?php else : ?>
                                    <?php foreach ($box_presets as $preset) : ?>
                                        <?php
                                        $bg_style = !empty($preset['backgroundGradient'])
                                            ? "linear-gradient(135deg, {$preset['gradientColor1']} 0%, {$preset['gradientColor2']} 100%)"
                                            : ($preset['backgroundColor'] ?: '#f5f5f5');
                                        ?>
                                        <div class="ksb-preset-card ksb-box-preset-card" data-preset-id="<?php echo esc_attr($preset['id']); ?>">
                                            <div class="ksb-box-preset-preview">
                                                <span style="
                                                    display: inline-block;
                                                    width: 50px;
                                                    height: 30px;
                                                    background: <?php echo esc_attr($bg_style); ?>;
                                                    border: <?php echo esc_attr($preset['borderWidth'] ?: '1'); ?>px <?php echo esc_attr($preset['borderStyle'] ?: 'solid'); ?> <?php echo esc_attr($preset['borderColor'] ?: '#ccc'); ?>;
                                                    border-radius: <?php echo esc_attr($preset['borderRadius'] ?: '0'); ?>px;
                                                    <?php echo !empty($preset['hasShadow']) ? 'box-shadow: 0 0 ' . esc_attr($preset['shadowSpread'] ?: '5') . 'px rgba(0,0,0,0.1);' : ''; ?>
                                                "></span>
                                            </div>
                                            <div class="ksb-preset-info">
                                                <div class="ksb-preset-name"><?php echo esc_html($preset['name']); ?></div>
                                                <div class="ksb-preset-details">
                                                    <span>枠線: <?php echo esc_html($preset['borderStyle'] ?: 'solid'); ?></span>
                                                    <span>角丸: <?php echo esc_attr($preset['borderRadius'] ?: '0'); ?>px</span>
                                                </div>
                                            </div>
                                            <div class="ksb-preset-actions">
                                                <button type="button" class="button button-small ksb-edit-box-preset" data-id="<?php echo esc_attr($preset['id']); ?>">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </button>
                                                <button type="button" class="button button-small ksb-delete-box-preset" data-id="<?php echo esc_attr($preset['id']); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- ボックスプリセット編集フォーム -->
                            <div class="ksb-preset-form-overlay" id="ksb-box-preset-form-overlay" style="display: none;">
                                <div class="ksb-preset-form">
                                    <div class="ksb-preset-form-header">
                                        <strong id="ksb-box-preset-form-title">ボックスプリセットを追加</strong>
                                        <button type="button" class="ksb-preset-form-close" id="ksb-box-preset-form-close">&times;</button>
                                    </div>
                                    <div class="ksb-preset-form-body">
                                        <input type="hidden" id="ksb-edit-box-preset-id" value="" />

                                        <div class="ksb-form-row">
                                            <label for="ksb-box-preset-name-input">プリセット名 <span class="required">*</span></label>
                                            <input type="text" id="ksb-box-preset-name-input" placeholder="例: 注意ボックス、情報ボックス" required />
                                        </div>

                                        <div class="ksb-form-row">
                                            <label>
                                                <input type="checkbox" id="ksb-box-gradient-input" />
                                                グラデーション背景
                                            </label>
                                        </div>

                                        <div id="ksb-box-solid-bg-row" class="ksb-form-row">
                                            <label for="ksb-box-bg-color-input">背景色</label>
                                            <input type="color" id="ksb-box-bg-color-input" value="#f5f5f5" />
                                        </div>

                                        <div id="ksb-box-gradient-row" class="ksb-form-row-group" style="display: none;">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-box-gradient1-input">開始色</label>
                                                <input type="color" id="ksb-box-gradient1-input" value="#ffffff" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-box-gradient2-input">終了色</label>
                                                <input type="color" id="ksb-box-gradient2-input" value="#f0f0f0" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-box-border-color-input">枠線色</label>
                                                <input type="color" id="ksb-box-border-color-input" value="#cccccc" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-box-border-style-input">枠線スタイル</label>
                                                <select id="ksb-box-border-style-input">
                                                    <option value="none">なし</option>
                                                    <option value="solid" selected>実線</option>
                                                    <option value="dotted">点線</option>
                                                    <option value="dashed">破線</option>
                                                    <option value="double">二重線</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-box-border-width-input">枠線幅 (px)</label>
                                                <input type="number" id="ksb-box-border-width-input" value="2" min="0" max="10" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-box-border-radius-input">角丸 (px)</label>
                                                <input type="number" id="ksb-box-border-radius-input" value="0" min="0" max="50" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-box-padding-top-input">上余白 (px)</label>
                                                <input type="number" id="ksb-box-padding-top-input" value="20" min="0" max="100" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-box-padding-bottom-input">下余白 (px)</label>
                                                <input type="number" id="ksb-box-padding-bottom-input" value="20" min="0" max="100" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-box-padding-left-input">左余白 (px)</label>
                                                <input type="number" id="ksb-box-padding-left-input" value="20" min="0" max="100" />
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label for="ksb-box-padding-right-input">右余白 (px)</label>
                                                <input type="number" id="ksb-box-padding-right-input" value="20" min="0" max="100" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label>
                                                    <input type="checkbox" id="ksb-box-shadow-input" />
                                                    影を付ける
                                                </label>
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half" id="ksb-box-shadow-spread-row" style="display: none;">
                                                <label for="ksb-box-shadow-spread-input">影の広がり (px)</label>
                                                <input type="number" id="ksb-box-shadow-spread-input" value="5" min="0" max="30" />
                                            </div>
                                        </div>

                                        <div class="ksb-form-row-group">
                                            <div class="ksb-form-row ksb-form-row-half">
                                                <label>
                                                    <input type="checkbox" id="ksb-box-icon-input" />
                                                    アイコンを表示
                                                </label>
                                            </div>
                                            <div class="ksb-form-row ksb-form-row-half" id="ksb-box-icon-type-row" style="display: none;">
                                                <label for="ksb-box-icon-type-input">アイコンタイプ</label>
                                                <select id="ksb-box-icon-type-input">
                                                    <option value="info">情報</option>
                                                    <option value="warning">警告</option>
                                                    <option value="success">成功</option>
                                                    <option value="alert">注意</option>
                                                    <option value="tip">ヒント</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="ksb-form-row" style="margin-top: 15px;">
                                            <label>プレビュー:</label>
                                            <div style="padding: 15px; background: #f5f5f5; border-radius: 6px;">
                                                <div id="ksb-box-preview" style="background: #f5f5f5; border: 2px solid #ccc; padding: 15px; border-radius: 0;">
                                                    <span style="font-size: 12px; color: #666;">ボックスのプレビュー</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ksb-preset-form-footer">
                                        <button type="button" class="button" id="ksb-box-preset-cancel-btn">キャンセル</button>
                                        <button type="button" class="button button-primary" id="ksb-box-preset-save-btn">保存</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($has_posts && !empty($box_presets)) : ?>
                        <!-- ボックス一括変換UI -->
                        <div class="ksb-bulk-convert-section">
                            <div class="ksb-bulk-convert-header">
                                <span class="dashicons dashicons-update"></span>
                                <strong>スタイル一括変換</strong>
                            </div>
                            <div class="ksb-bulk-convert-form">
                                <div class="ksb-bulk-convert-row">
                                    <label>
                                        <span class="ksb-label-text">変換先スタイル:</span>
                                        <select id="ksb-box-to-preset" class="ksb-preset-select" required>
                                            <option value="">-- 選択してください --</option>
                                            <?php foreach ($box_presets as $preset) : ?>
                                                <option value="<?php echo esc_attr($preset['id']); ?>">
                                                    <?php echo esc_html($preset['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <div class="ksb-bulk-convert-preview" id="ksb-box-convert-preview" style="display: none;">
                                    <div id="ksb-box-convert-preview-box" style="padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px;">プレビュー</div>
                                </div>
                                <div class="ksb-bulk-convert-targets">
                                    <div class="ksb-targets-header">
                                        <label>
                                            <input type="checkbox" id="ksb-box-select-all-posts" checked />
                                            <span>対象記事を選択 (<?php echo count($posts); ?>件)</span>
                                        </label>
                                    </div>
                                    <div class="ksb-targets-list">
                                        <?php foreach ($posts as $post) : ?>
                                            <label class="ksb-target-item">
                                                <input type="checkbox" class="ksb-box-post-checkbox" value="<?php echo esc_attr($post->ID); ?>" checked />
                                                <span class="ksb-target-title"><?php echo esc_html($post->post_title ? $post->post_title : '(タイトルなし)'); ?></span>
                                                <span class="ksb-target-meta"><?php echo esc_html($this->get_post_type_label($post->post_type)); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="ksb-bulk-convert-actions">
                                    <button type="button" id="ksb-box-bulk-convert-btn" class="button button-primary" disabled>
                                        <span class="dashicons dashicons-update"></span>
                                        一括変換を実行
                                    </button>
                                    <span class="ksb-convert-status" id="ksb-box-convert-status"></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!$has_posts) : ?>
                        <div class="ksb-empty-message">
                            <span class="dashicons dashicons-info-outline"></span>
                            <p>このブロックはまだ使用されていません。</p>
                        </div>
                    <?php else : ?>
                        <div class="ksb-post-list-header">
                            <strong>使用中の記事一覧</strong>
                        </div>
                        <ul class="ksb-post-list">
                            <?php foreach ($posts as $post) : ?>
                                <li class="ksb-post-item">
                                    <div class="ksb-post-title">
                                        <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                            <?php echo esc_html($post->post_title ? $post->post_title : '(タイトルなし)'); ?>
                                        </a>
                                    </div>
                                    <div class="ksb-post-meta">
                                        <span class="ksb-meta-badge type">
                                            <?php echo esc_html($this->get_post_type_label($post->post_type)); ?>
                                        </span>
                                        <span class="ksb-meta-badge status-<?php echo esc_attr($post->post_status); ?>">
                                            <?php echo esc_html($this->get_post_status_label($post->post_status)); ?>
                                        </span>
                                        <span class="ksb-post-date">
                                            <?php echo esc_html(get_the_modified_date('Y/m/d', $post->ID)); ?>
                                        </span>
                                    </div>
                                    <div class="ksb-post-actions">
                                        <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">編集</a>
                                        <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank">表示</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <script>
        (function() {
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce = '<?php echo wp_create_nonce('ksb_nonce'); ?>';
            var currentPresets = <?php echo wp_json_encode($presets); ?>;

            // 最初に使用中のブロックを1つだけ開く
            var accordions = document.querySelectorAll('.ksb-block-accordion');
            for (var i = 0; i < accordions.length; i++) {
                var badge = accordions[i].querySelector('.ksb-block-badge.active');
                if (badge) {
                    accordions[i].classList.add('open');
                    break;
                }
            }

            // ===== プリセット管理機能 =====
            var addPresetBtn = document.getElementById('ksb-add-preset-btn');
            var formOverlay = document.getElementById('ksb-preset-form-overlay');
            var formTitle = document.getElementById('ksb-preset-form-title');
            var formClose = document.getElementById('ksb-preset-form-close');
            var formCancel = document.getElementById('ksb-preset-cancel-btn');
            var formSave = document.getElementById('ksb-preset-save-btn');
            var editPresetId = document.getElementById('ksb-edit-preset-id');
            var presetNameInput = document.getElementById('ksb-preset-name-input');
            var avatarUrlInput = document.getElementById('ksb-avatar-url-input');
            var avatarIdInput = document.getElementById('ksb-avatar-id-input');
            var avatarPreview = document.getElementById('ksb-avatar-preview');
            var selectAvatarBtn = document.getElementById('ksb-select-avatar-btn');
            var removeAvatarBtn = document.getElementById('ksb-remove-avatar-btn');
            var speakerNameInput = document.getElementById('ksb-speaker-name-input');
            var speakerTitleInput = document.getElementById('ksb-speaker-title-input');
            var profileUrlInput = document.getElementById('ksb-profile-url-input');
            var avatarPositionInput = document.getElementById('ksb-avatar-position-input');
            var avatarShapeInput = document.getElementById('ksb-avatar-shape-input');
            var avatarSizeInput = document.getElementById('ksb-avatar-size-input');
            var nameColorInput = document.getElementById('ksb-name-color-input');
            var bubbleStyleInput = document.getElementById('ksb-bubble-style-input');
            var bubbleMaxWidthInput = document.getElementById('ksb-bubble-max-width-input');
            var bubbleColorInput = document.getElementById('ksb-bubble-color-input');
            var textColorInput = document.getElementById('ksb-text-color-input');
            var borderColorInput = document.getElementById('ksb-border-color-input');
            var borderWidthInput = document.getElementById('ksb-border-width-input');
            var showTailInput = document.getElementById('ksb-show-tail-input');
            var presetList = document.getElementById('ksb-preset-list');
            var speechBubblePreview = document.getElementById('ksb-speech-bubble-preview');
            var previewAvatarEl = document.getElementById('ksb-preview-avatar');
            var previewNameEl = document.getElementById('ksb-preview-name');
            var previewBubbleEl = document.getElementById('ksb-preview-bubble');

            var mediaFrame = null;

            // プレビュー更新
            function updateSpeechBubblePreview() {
                if (!previewBubbleEl) return;

                // アバタープレビュー
                if (previewAvatarEl) {
                    var avatarUrl = avatarUrlInput ? avatarUrlInput.value : '';
                    var avatarShape = avatarShapeInput ? avatarShapeInput.value : 'circle';
                    var borderRadius = avatarShape === 'circle' ? '50%' : (avatarShape === 'rounded' ? '10px' : '0');
                    previewAvatarEl.style.borderRadius = borderRadius;
                    if (avatarUrl) {
                        previewAvatarEl.innerHTML = '<img src="' + avatarUrl + '" style="width: 100%; height: 100%; object-fit: cover;" />';
                    } else {
                        previewAvatarEl.innerHTML = '<span class="dashicons dashicons-admin-users" style="font-size: 24px; color: #999;"></span>';
                    }
                }

                // 名前プレビュー
                if (previewNameEl) {
                    var speakerName = speakerNameInput ? speakerNameInput.value : '';
                    var nameColor = nameColorInput ? nameColorInput.value : '#007cba';
                    previewNameEl.textContent = speakerName;
                    previewNameEl.style.color = nameColor;
                }

                // 吹き出しプレビュー
                var bubbleColor = bubbleColorInput ? bubbleColorInput.value : '#f0f8ff';
                var textColor = textColorInput ? textColorInput.value : '#333333';
                var borderColor = borderColorInput ? borderColorInput.value : '#007cba';
                var borderWidth = borderWidthInput ? borderWidthInput.value : '2';
                var bubbleStyle = bubbleStyleInput ? bubbleStyleInput.value : 'standard';

                previewBubbleEl.style.backgroundColor = bubbleColor;
                previewBubbleEl.style.color = textColor;
                previewBubbleEl.style.borderColor = borderColor;
                previewBubbleEl.style.borderWidth = borderWidth + 'px';
                previewBubbleEl.style.borderStyle = 'solid';

                if (bubbleStyle === 'think') {
                    previewBubbleEl.style.borderRadius = '50%';
                } else if (bubbleStyle === 'shout') {
                    previewBubbleEl.style.borderRadius = '5px';
                } else {
                    previewBubbleEl.style.borderRadius = '10px';
                }
            }

            // フォームを開く
            function openForm(isEdit) {
                formTitle.textContent = isEdit ? 'プリセットを編集' : 'プリセットを追加';
                formOverlay.style.display = 'flex';
                updateSpeechBubblePreview();
            }

            // フォームを閉じてリセット
            function closeForm() {
                formOverlay.style.display = 'none';
                resetForm();
            }

            // フォームをリセット
            function resetForm() {
                editPresetId.value = '';
                presetNameInput.value = '';
                avatarUrlInput.value = '';
                avatarIdInput.value = '0';
                avatarPreview.innerHTML = '<span class="dashicons dashicons-admin-users"></span>';
                removeAvatarBtn.style.display = 'none';
                speakerNameInput.value = '';
                speakerTitleInput.value = '';
                profileUrlInput.value = '';
                avatarPositionInput.value = 'left';
                avatarShapeInput.value = 'circle';
                avatarSizeInput.value = '60';
                nameColorInput.value = '#007cba';
                if (bubbleStyleInput) bubbleStyleInput.value = 'standard';
                if (bubbleMaxWidthInput) bubbleMaxWidthInput.value = '100';
                if (bubbleColorInput) bubbleColorInput.value = '#f0f8ff';
                if (textColorInput) textColorInput.value = '#333333';
                if (borderColorInput) borderColorInput.value = '#007cba';
                if (borderWidthInput) borderWidthInput.value = '2';
                if (showTailInput) showTailInput.checked = true;
                updateSpeechBubblePreview();
            }

            // フォームにデータを設定
            function populateForm(preset) {
                editPresetId.value = preset.id || '';
                presetNameInput.value = preset.name || '';
                avatarUrlInput.value = preset.avatarUrl || '';
                avatarIdInput.value = preset.avatarId || '0';
                speakerNameInput.value = preset.speakerName || '';
                speakerTitleInput.value = preset.speakerTitle || '';
                profileUrlInput.value = preset.profileUrl || '';
                avatarPositionInput.value = preset.avatarPosition || 'left';
                avatarShapeInput.value = preset.avatarShape || 'circle';
                avatarSizeInput.value = preset.avatarSize || '60';
                nameColorInput.value = preset.nameLabelColor || '#007cba';
                if (bubbleStyleInput) bubbleStyleInput.value = preset.bubbleStyle || 'standard';
                if (bubbleMaxWidthInput) bubbleMaxWidthInput.value = preset.bubbleMaxWidth || '100';
                if (bubbleColorInput) bubbleColorInput.value = preset.bubbleColor || '#f0f8ff';
                if (textColorInput) textColorInput.value = preset.textColor || '#333333';
                if (borderColorInput) borderColorInput.value = preset.borderColor || '#007cba';
                if (borderWidthInput) borderWidthInput.value = preset.borderWidth || '2';
                if (showTailInput) showTailInput.checked = preset.showTail !== false;

                if (preset.avatarUrl) {
                    avatarPreview.innerHTML = '<img src="' + preset.avatarUrl + '" alt="Avatar" />';
                    removeAvatarBtn.style.display = 'inline-block';
                } else {
                    avatarPreview.innerHTML = '<span class="dashicons dashicons-admin-users"></span>';
                    removeAvatarBtn.style.display = 'none';
                }
                updateSpeechBubblePreview();
            }

            // プリセットを保存
            function savePresets(callback) {
                var formData = new FormData();
                formData.append('action', 'ksb_save_presets');
                formData.append('nonce', nonce);
                formData.append('presets', JSON.stringify(currentPresets));

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success && callback) {
                        callback(data.data.presets);
                    }
                })
                .catch(function(err) {
                    console.error('Save error:', err);
                    alert('保存に失敗しました');
                });
            }

            // プリセットカードを作成
            function createPresetCard(preset) {
                var card = document.createElement('div');
                card.className = 'ksb-preset-card';
                card.setAttribute('data-preset-id', preset.id);

                var avatarHtml = preset.avatarUrl
                    ? '<img src="' + preset.avatarUrl + '" alt="' + (preset.name || '') + '" />'
                    : '<div class="ksb-preset-avatar-placeholder"><span class="dashicons dashicons-admin-users"></span></div>';

                var detailsHtml = '';
                if (preset.speakerName) {
                    detailsHtml += '<span class="ksb-preset-speaker">' + preset.speakerName + '</span>';
                }
                if (preset.speakerTitle) {
                    detailsHtml += '<span class="ksb-preset-title-text">' + preset.speakerTitle + '</span>';
                }

                card.innerHTML = '<div class="ksb-preset-avatar">' + avatarHtml + '</div>'
                    + '<div class="ksb-preset-info">'
                    + '<div class="ksb-preset-name">' + (preset.name || '') + '</div>'
                    + '<div class="ksb-preset-details">' + detailsHtml + '</div>'
                    + '</div>'
                    + '<div class="ksb-preset-actions">'
                    + '<button type="button" class="button button-small ksb-edit-preset" data-id="' + preset.id + '"><span class="dashicons dashicons-edit"></span></button>'
                    + '<button type="button" class="button button-small ksb-delete-preset" data-id="' + preset.id + '"><span class="dashicons dashicons-trash"></span></button>'
                    + '</div>';

                return card;
            }

            // リストを更新
            function refreshPresetList() {
                presetList.innerHTML = '';

                if (currentPresets.length === 0) {
                    presetList.innerHTML = '<div class="ksb-preset-empty" id="ksb-preset-empty">'
                        + '<span class="dashicons dashicons-info-outline"></span>'
                        + '<p>プリセットがまだ登録されていません。<br>「新規追加」ボタンからプリセットを登録してください。</p>'
                        + '</div>';
                } else {
                    currentPresets.forEach(function(preset) {
                        presetList.appendChild(createPresetCard(preset));
                    });
                }

                bindPresetActions();
            }

            // 編集・削除ボタンのイベントをバインド
            function bindPresetActions() {
                document.querySelectorAll('.ksb-edit-preset').forEach(function(btn) {
                    btn.onclick = function() {
                        var id = this.getAttribute('data-id');
                        var preset = currentPresets.find(function(p) { return p.id === id; });
                        if (preset) {
                            populateForm(preset);
                            openForm(true);
                        }
                    };
                });

                document.querySelectorAll('.ksb-delete-preset').forEach(function(btn) {
                    btn.onclick = function() {
                        var id = this.getAttribute('data-id');
                        if (confirm('このプリセットを削除しますか？')) {
                            currentPresets = currentPresets.filter(function(p) { return p.id !== id; });
                            savePresets(function() {
                                refreshPresetList();
                            });
                        }
                    };
                });
            }

            if (addPresetBtn) {
                // 新規追加ボタン
                addPresetBtn.addEventListener('click', function() {
                    resetForm();
                    openForm(false);
                });

                // フォームを閉じる
                formClose.addEventListener('click', closeForm);
                formCancel.addEventListener('click', closeForm);
                formOverlay.addEventListener('click', function(e) {
                    if (e.target === formOverlay) closeForm();
                });

                // メディアライブラリを開く
                selectAvatarBtn.addEventListener('click', function() {
                    if (mediaFrame) {
                        mediaFrame.open();
                        return;
                    }

                    mediaFrame = wp.media({
                        title: '画像を選択',
                        button: { text: '選択' },
                        multiple: false,
                        library: { type: 'image' }
                    });

                    mediaFrame.on('select', function() {
                        var attachment = mediaFrame.state().get('selection').first().toJSON();
                        avatarUrlInput.value = attachment.url;
                        avatarIdInput.value = attachment.id;
                        avatarPreview.innerHTML = '<img src="' + attachment.url + '" alt="Avatar" />';
                        removeAvatarBtn.style.display = 'inline-block';
                    });

                    mediaFrame.open();
                });

                // アバター削除
                removeAvatarBtn.addEventListener('click', function() {
                    avatarUrlInput.value = '';
                    avatarIdInput.value = '0';
                    avatarPreview.innerHTML = '<span class="dashicons dashicons-admin-users"></span>';
                    removeAvatarBtn.style.display = 'none';
                });

                // 保存
                formSave.addEventListener('click', function() {
                    var name = presetNameInput.value.trim();
                    if (!name) {
                        alert('プリセット名を入力してください');
                        presetNameInput.focus();
                        return;
                    }

                    var presetData = {
                        id: editPresetId.value || Date.now().toString(),
                        name: name,
                        avatarUrl: avatarUrlInput.value,
                        avatarId: parseInt(avatarIdInput.value) || 0,
                        speakerName: speakerNameInput.value.trim(),
                        speakerTitle: speakerTitleInput.value.trim(),
                        profileUrl: profileUrlInput.value.trim(),
                        avatarPosition: avatarPositionInput.value,
                        avatarShape: avatarShapeInput.value,
                        avatarSize: parseInt(avatarSizeInput.value) || 60,
                        nameLabelColor: nameColorInput.value,
                        bubbleStyle: bubbleStyleInput ? bubbleStyleInput.value : 'standard',
                        bubbleMaxWidth: bubbleMaxWidthInput ? parseInt(bubbleMaxWidthInput.value) || 100 : 100,
                        bubbleColor: bubbleColorInput ? bubbleColorInput.value : '#f0f8ff',
                        textColor: textColorInput ? textColorInput.value : '#333333',
                        borderColor: borderColorInput ? borderColorInput.value : '#007cba',
                        borderWidth: borderWidthInput ? parseInt(borderWidthInput.value) || 2 : 2,
                        showTail: showTailInput ? showTailInput.checked : true
                    };

                    if (editPresetId.value) {
                        // 編集
                        var idx = currentPresets.findIndex(function(p) { return p.id === editPresetId.value; });
                        if (idx !== -1) {
                            currentPresets[idx] = presetData;
                        }
                    } else {
                        // 新規追加
                        currentPresets.push(presetData);
                    }

                    savePresets(function() {
                        closeForm();
                        location.reload(); // 一括変換のセレクトも更新するためリロード
                    });
                });

                bindPresetActions();

                // プレビュー更新のイベントリスナー
                var previewInputs = [
                    speakerNameInput, nameColorInput, avatarShapeInput,
                    bubbleStyleInput, bubbleColorInput, textColorInput,
                    borderColorInput, borderWidthInput
                ];
                previewInputs.forEach(function(input) {
                    if (input) {
                        input.addEventListener('input', updateSpeechBubblePreview);
                        input.addEventListener('change', updateSpeechBubblePreview);
                    }
                });
            }

            // ===== 一括変換機能 =====
            var toPresetSelect = document.getElementById('ksb-to-preset');
            var fromPresetSelect = document.getElementById('ksb-from-preset');
            var convertBtn = document.getElementById('ksb-bulk-convert-btn');
            var selectAllCheckbox = document.getElementById('ksb-select-all-posts');
            var postCheckboxes = document.querySelectorAll('.ksb-post-checkbox');
            var previewDiv = document.getElementById('ksb-bulk-convert-preview');
            var previewAvatar = document.getElementById('ksb-bulk-preview-avatar');
            var previewName = document.getElementById('ksb-bulk-preview-name');
            var previewBubble = document.getElementById('ksb-bulk-preview-bubble');
            var statusSpan = document.getElementById('ksb-convert-status');

            if (!toPresetSelect) return;

            // 変換先選択時のプレビュー表示
            toPresetSelect.addEventListener('change', function() {
                var selectedOption = this.options[this.selectedIndex];
                var avatarUrl = selectedOption.getAttribute('data-avatar-url');
                var speakerName = selectedOption.getAttribute('data-speaker-name');
                var avatarShape = selectedOption.getAttribute('data-avatar-shape') || 'circle';
                var nameLabelColor = selectedOption.getAttribute('data-name-label-color') || '#007cba';
                var bubbleColor = selectedOption.getAttribute('data-bubble-color') || '#f0f8ff';
                var textColor = selectedOption.getAttribute('data-text-color') || '#333333';
                var borderColor = selectedOption.getAttribute('data-border-color') || '#007cba';

                if (this.value) {
                    // アバター
                    if (avatarUrl) {
                        previewAvatar.src = avatarUrl;
                        previewAvatar.style.display = 'block';
                    } else {
                        previewAvatar.src = '';
                        previewAvatar.style.display = 'none';
                    }

                    // アバター形状
                    var borderRadius = avatarShape === 'circle' ? '50%' : (avatarShape === 'rounded' ? '10px' : '0');
                    previewAvatar.style.borderRadius = borderRadius;

                    // 名前
                    previewName.textContent = speakerName || '(名前未設定)';
                    previewName.style.color = nameLabelColor;

                    // 吹き出しスタイル
                    if (previewBubble) {
                        previewBubble.style.backgroundColor = bubbleColor;
                        previewBubble.style.color = textColor;
                        previewBubble.style.borderColor = borderColor;
                    }

                    previewDiv.style.display = 'flex';
                } else {
                    previewDiv.style.display = 'none';
                }

                updateConvertButton();
            });

            // 全選択チェックボックス
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    postCheckboxes.forEach(function(cb) {
                        cb.checked = selectAllCheckbox.checked;
                    });
                    updateConvertButton();
                });
            }

            // 個別チェックボックス
            postCheckboxes.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    var allChecked = Array.from(postCheckboxes).every(function(c) { return c.checked; });
                    var someChecked = Array.from(postCheckboxes).some(function(c) { return c.checked; });
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                        selectAllCheckbox.indeterminate = !allChecked && someChecked;
                    }
                    updateConvertButton();
                });
            });

            // ボタンの有効/無効を更新
            function updateConvertButton() {
                var hasToPreset = toPresetSelect.value !== '';
                var hasSelectedPosts = Array.from(postCheckboxes).some(function(cb) { return cb.checked; });
                convertBtn.disabled = !(hasToPreset && hasSelectedPosts);
            }

            // 一括変換実行
            convertBtn.addEventListener('click', function() {
                var selectedPostIds = Array.from(postCheckboxes)
                    .filter(function(cb) { return cb.checked; })
                    .map(function(cb) { return cb.value; });

                if (selectedPostIds.length === 0) {
                    alert('変換対象の記事を選択してください。');
                    return;
                }

                if (!toPresetSelect.value) {
                    alert('変換先のプリセットを選択してください。');
                    return;
                }

                var confirmMsg = selectedPostIds.length + '件の記事の吹き出しを変換します。\n\n';
                confirmMsg += '※この操作は元に戻せません。\n続行しますか？';

                if (!confirm(confirmMsg)) {
                    return;
                }

                convertBtn.disabled = true;
                statusSpan.textContent = '変換中...';
                statusSpan.className = 'ksb-convert-status loading';

                var formData = new FormData();
                formData.append('action', 'ksb_bulk_convert_speech_bubble');
                formData.append('nonce', '<?php echo wp_create_nonce('ksb_nonce'); ?>');
                formData.append('to_preset_id', toPresetSelect.value);
                if (fromPresetSelect && fromPresetSelect.value) {
                    formData.append('from_preset_id', fromPresetSelect.value);
                }
                selectedPostIds.forEach(function(id) {
                    formData.append('post_ids[]', id);
                });

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        statusSpan.textContent = data.data.updated_count + '件の記事を変換しました。';
                        statusSpan.className = 'ksb-convert-status success';

                        if (data.data.errors && data.data.errors.length > 0) {
                            console.warn('Conversion errors:', data.data.errors);
                        }

                        // 3秒後にページをリロード
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        statusSpan.textContent = 'エラー: ' + (data.data.message || '変換に失敗しました');
                        statusSpan.className = 'ksb-convert-status error';
                        convertBtn.disabled = false;
                    }
                })
                .catch(function(error) {
                    statusSpan.textContent = 'エラー: 通信に失敗しました';
                    statusSpan.className = 'ksb-convert-status error';
                    convertBtn.disabled = false;
                    console.error('Fetch error:', error);
                });
            });

            updateConvertButton();

            // ===== ボタンプリセット管理機能 =====
            var currentBtnPresets = <?php echo wp_json_encode($button_presets); ?>;

            var addBtnPresetBtn = document.getElementById('ksb-add-btn-preset-btn');
            var btnFormOverlay = document.getElementById('ksb-btn-preset-form-overlay');
            var btnFormTitle = document.getElementById('ksb-btn-preset-form-title');
            var btnFormClose = document.getElementById('ksb-btn-preset-form-close');
            var btnFormCancel = document.getElementById('ksb-btn-preset-cancel-btn');
            var btnFormSave = document.getElementById('ksb-btn-preset-save-btn');
            var editBtnPresetId = document.getElementById('ksb-edit-btn-preset-id');
            var btnPresetNameInput = document.getElementById('ksb-btn-preset-name-input');
            var btnBgColorInput = document.getElementById('ksb-btn-bg-color-input');
            var btnTextColorInput = document.getElementById('ksb-btn-text-color-input');
            var btnBorderColorInput = document.getElementById('ksb-btn-border-color-input');
            var btnBorderWidthInput = document.getElementById('ksb-btn-border-width-input');
            var btnBorderRadiusInput = document.getElementById('ksb-btn-border-radius-input');
            var btnFontSizeInput = document.getElementById('ksb-btn-font-size-input');
            var btnPaddingVInput = document.getElementById('ksb-btn-padding-v-input');
            var btnPaddingHInput = document.getElementById('ksb-btn-padding-h-input');
            var btnWidthInput = document.getElementById('ksb-btn-width-input');
            var btnShadowInput = document.getElementById('ksb-btn-shadow-input');
            var btnPreview = document.getElementById('ksb-btn-preview');
            var btnPresetList = document.getElementById('ksb-btn-preset-list');

            function openBtnForm(isEdit) {
                if (!btnFormOverlay) return;
                btnFormTitle.textContent = isEdit ? 'ボタンプリセットを編集' : 'ボタンプリセットを追加';
                btnFormOverlay.style.display = 'flex';
                updateBtnPreview();
            }

            function closeBtnForm() {
                if (!btnFormOverlay) return;
                btnFormOverlay.style.display = 'none';
                resetBtnForm();
            }

            function resetBtnForm() {
                if (!editBtnPresetId) return;
                editBtnPresetId.value = '';
                btnPresetNameInput.value = '';
                btnBgColorInput.value = '#007cba';
                btnTextColorInput.value = '#ffffff';
                btnBorderColorInput.value = '#007cba';
                btnBorderWidthInput.value = '0';
                btnBorderRadiusInput.value = '5';
                btnFontSizeInput.value = '16';
                btnPaddingVInput.value = '12';
                btnPaddingHInput.value = '24';
                btnWidthInput.value = 'auto';
                btnShadowInput.checked = false;
            }

            function populateBtnForm(preset) {
                editBtnPresetId.value = preset.id || '';
                btnPresetNameInput.value = preset.name || '';
                btnBgColorInput.value = preset.backgroundColor || '#007cba';
                btnTextColorInput.value = preset.textColor || '#ffffff';
                btnBorderColorInput.value = preset.borderColor || '#007cba';
                btnBorderWidthInput.value = preset.borderWidth || '0';
                btnBorderRadiusInput.value = preset.borderRadius || '5';
                btnFontSizeInput.value = preset.fontSize || '16';
                btnPaddingVInput.value = preset.paddingVertical || '12';
                btnPaddingHInput.value = preset.paddingHorizontal || '24';
                btnWidthInput.value = preset.buttonWidth || 'auto';
                btnShadowInput.checked = !!preset.hasShadow;
            }

            function updateBtnPreview() {
                if (!btnPreview) return;
                btnPreview.style.backgroundColor = btnBgColorInput.value;
                btnPreview.style.color = btnTextColorInput.value;
                btnPreview.style.borderColor = btnBorderColorInput.value;
                btnPreview.style.borderWidth = btnBorderWidthInput.value + 'px';
                btnPreview.style.borderStyle = parseInt(btnBorderWidthInput.value) > 0 ? 'solid' : 'none';
                btnPreview.style.borderRadius = btnBorderRadiusInput.value + 'px';
                btnPreview.style.fontSize = btnFontSizeInput.value + 'px';
                btnPreview.style.padding = btnPaddingVInput.value + 'px ' + btnPaddingHInput.value + 'px';
                btnPreview.style.boxShadow = btnShadowInput.checked ? '0 2px 5px rgba(0,0,0,0.3)' : 'none';
                btnPreview.textContent = btnPresetNameInput.value || 'ボタン';
            }

            function saveBtnPresets(callback) {
                var formData = new FormData();
                formData.append('action', 'ksb_save_button_presets');
                formData.append('nonce', nonce);
                formData.append('presets', JSON.stringify(currentBtnPresets));

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success && callback) callback(data.data.presets);
                })
                .catch(function(err) {
                    console.error('Save button preset error:', err);
                    alert('保存に失敗しました');
                });
            }

            function bindBtnPresetActions() {
                document.querySelectorAll('.ksb-edit-btn-preset').forEach(function(btn) {
                    btn.onclick = function() {
                        var id = this.getAttribute('data-id');
                        var preset = currentBtnPresets.find(function(p) { return p.id === id; });
                        if (preset) {
                            populateBtnForm(preset);
                            openBtnForm(true);
                            updateBtnPreview();
                        }
                    };
                });

                document.querySelectorAll('.ksb-delete-btn-preset').forEach(function(btn) {
                    btn.onclick = function() {
                        var id = this.getAttribute('data-id');
                        if (confirm('このプリセットを削除しますか？')) {
                            currentBtnPresets = currentBtnPresets.filter(function(p) { return p.id !== id; });
                            saveBtnPresets(function() {
                                location.reload();
                            });
                        }
                    };
                });
            }

            if (addBtnPresetBtn) {
                addBtnPresetBtn.addEventListener('click', function() {
                    resetBtnForm();
                    openBtnForm(false);
                });

                btnFormClose.addEventListener('click', closeBtnForm);
                btnFormCancel.addEventListener('click', closeBtnForm);
                btnFormOverlay.addEventListener('click', function(e) {
                    if (e.target === btnFormOverlay) closeBtnForm();
                });

                // プレビュー更新
                [btnPresetNameInput, btnBgColorInput, btnTextColorInput, btnBorderColorInput,
                 btnBorderWidthInput, btnBorderRadiusInput, btnFontSizeInput,
                 btnPaddingVInput, btnPaddingHInput, btnShadowInput].forEach(function(input) {
                    if (input) input.addEventListener('input', updateBtnPreview);
                    if (input) input.addEventListener('change', updateBtnPreview);
                });

                btnFormSave.addEventListener('click', function() {
                    var name = btnPresetNameInput.value.trim();
                    if (!name) {
                        alert('プリセット名を入力してください');
                        btnPresetNameInput.focus();
                        return;
                    }

                    var presetData = {
                        id: editBtnPresetId.value || Date.now().toString(),
                        name: name,
                        backgroundColor: btnBgColorInput.value,
                        textColor: btnTextColorInput.value,
                        borderColor: btnBorderColorInput.value,
                        borderWidth: parseInt(btnBorderWidthInput.value) || 0,
                        borderRadius: parseInt(btnBorderRadiusInput.value) || 5,
                        fontSize: parseInt(btnFontSizeInput.value) || 16,
                        paddingVertical: parseInt(btnPaddingVInput.value) || 12,
                        paddingHorizontal: parseInt(btnPaddingHInput.value) || 24,
                        buttonWidth: btnWidthInput.value,
                        hasShadow: btnShadowInput.checked
                    };

                    if (editBtnPresetId.value) {
                        var idx = currentBtnPresets.findIndex(function(p) { return p.id === editBtnPresetId.value; });
                        if (idx !== -1) currentBtnPresets[idx] = presetData;
                    } else {
                        currentBtnPresets.push(presetData);
                    }

                    saveBtnPresets(function() {
                        closeBtnForm();
                        location.reload();
                    });
                });

                bindBtnPresetActions();
            }

            // ===== ボタン一括変換機能 =====
            var btnToPresetSelect = document.getElementById('ksb-btn-to-preset');
            var btnConvertBtn = document.getElementById('ksb-btn-bulk-convert-btn');
            var btnSelectAllCheckbox = document.getElementById('ksb-btn-select-all-posts');
            var btnPostCheckboxes = document.querySelectorAll('.ksb-btn-post-checkbox');
            var btnConvertPreview = document.getElementById('ksb-btn-convert-preview');
            var btnConvertPreviewBtn = document.getElementById('ksb-btn-convert-preview-btn');
            var btnConvertStatus = document.getElementById('ksb-btn-convert-status');

            if (btnToPresetSelect) {
                btnToPresetSelect.addEventListener('change', function() {
                    var selectedOption = this.options[this.selectedIndex];
                    var bgColor = selectedOption.getAttribute('data-bg-color');
                    var textColor = selectedOption.getAttribute('data-text-color');

                    if (this.value && bgColor) {
                        btnConvertPreviewBtn.style.backgroundColor = bgColor;
                        btnConvertPreviewBtn.style.color = textColor || '#fff';
                        btnConvertPreviewBtn.textContent = selectedOption.text;
                        btnConvertPreview.style.display = 'flex';
                    } else {
                        btnConvertPreview.style.display = 'none';
                    }
                    updateBtnConvertButton();
                });

                if (btnSelectAllCheckbox) {
                    btnSelectAllCheckbox.addEventListener('change', function() {
                        btnPostCheckboxes.forEach(function(cb) {
                            cb.checked = btnSelectAllCheckbox.checked;
                        });
                        updateBtnConvertButton();
                    });
                }

                btnPostCheckboxes.forEach(function(cb) {
                    cb.addEventListener('change', function() {
                        var allChecked = Array.from(btnPostCheckboxes).every(function(c) { return c.checked; });
                        var someChecked = Array.from(btnPostCheckboxes).some(function(c) { return c.checked; });
                        if (btnSelectAllCheckbox) {
                            btnSelectAllCheckbox.checked = allChecked;
                            btnSelectAllCheckbox.indeterminate = !allChecked && someChecked;
                        }
                        updateBtnConvertButton();
                    });
                });

                function updateBtnConvertButton() {
                    var hasPreset = btnToPresetSelect.value !== '';
                    var hasSelectedPosts = Array.from(btnPostCheckboxes).some(function(cb) { return cb.checked; });
                    btnConvertBtn.disabled = !(hasPreset && hasSelectedPosts);
                }

                btnConvertBtn.addEventListener('click', function() {
                    var selectedPostIds = Array.from(btnPostCheckboxes)
                        .filter(function(cb) { return cb.checked; })
                        .map(function(cb) { return cb.value; });

                    if (selectedPostIds.length === 0 || !btnToPresetSelect.value) return;

                    if (!confirm(selectedPostIds.length + '件の記事のボタンスタイルを変換します。\n\n※この操作は元に戻せません。\n続行しますか？')) {
                        return;
                    }

                    btnConvertBtn.disabled = true;
                    btnConvertStatus.textContent = '変換中...';
                    btnConvertStatus.className = 'ksb-convert-status loading';

                    var formData = new FormData();
                    formData.append('action', 'ksb_bulk_convert_button');
                    formData.append('nonce', nonce);
                    formData.append('to_preset_id', btnToPresetSelect.value);
                    selectedPostIds.forEach(function(id) {
                        formData.append('post_ids[]', id);
                    });

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            btnConvertStatus.textContent = data.data.updated_count + '件の記事を変換しました。';
                            btnConvertStatus.className = 'ksb-convert-status success';
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            btnConvertStatus.textContent = 'エラー: ' + (data.data.message || '変換に失敗しました');
                            btnConvertStatus.className = 'ksb-convert-status error';
                            btnConvertBtn.disabled = false;
                        }
                    })
                    .catch(function(error) {
                        btnConvertStatus.textContent = 'エラー: 通信に失敗しました';
                        btnConvertStatus.className = 'ksb-convert-status error';
                        btnConvertBtn.disabled = false;
                    });
                });

                updateBtnConvertButton();
            }

            // ===== ボックスプリセット管理機能 =====
            var currentBoxPresets = <?php echo wp_json_encode($box_presets); ?>;

            var addBoxPresetBtn = document.getElementById('ksb-add-box-preset-btn');
            var boxFormOverlay = document.getElementById('ksb-box-preset-form-overlay');
            var boxFormTitle = document.getElementById('ksb-box-preset-form-title');
            var boxFormClose = document.getElementById('ksb-box-preset-form-close');
            var boxFormCancel = document.getElementById('ksb-box-preset-cancel-btn');
            var boxFormSave = document.getElementById('ksb-box-preset-save-btn');
            var editBoxPresetId = document.getElementById('ksb-edit-box-preset-id');
            var boxPresetNameInput = document.getElementById('ksb-box-preset-name-input');
            var boxGradientInput = document.getElementById('ksb-box-gradient-input');
            var boxBgColorInput = document.getElementById('ksb-box-bg-color-input');
            var boxGradient1Input = document.getElementById('ksb-box-gradient1-input');
            var boxGradient2Input = document.getElementById('ksb-box-gradient2-input');
            var boxBorderColorInput = document.getElementById('ksb-box-border-color-input');
            var boxBorderStyleInput = document.getElementById('ksb-box-border-style-input');
            var boxBorderWidthInput = document.getElementById('ksb-box-border-width-input');
            var boxBorderRadiusInput = document.getElementById('ksb-box-border-radius-input');
            var boxPaddingTopInput = document.getElementById('ksb-box-padding-top-input');
            var boxPaddingBottomInput = document.getElementById('ksb-box-padding-bottom-input');
            var boxPaddingLeftInput = document.getElementById('ksb-box-padding-left-input');
            var boxPaddingRightInput = document.getElementById('ksb-box-padding-right-input');
            var boxShadowInput = document.getElementById('ksb-box-shadow-input');
            var boxShadowSpreadInput = document.getElementById('ksb-box-shadow-spread-input');
            var boxIconInput = document.getElementById('ksb-box-icon-input');
            var boxIconTypeInput = document.getElementById('ksb-box-icon-type-input');
            var boxPreview = document.getElementById('ksb-box-preview');
            var boxPresetList = document.getElementById('ksb-box-preset-list');
            var boxSolidBgRow = document.getElementById('ksb-box-solid-bg-row');
            var boxGradientRow = document.getElementById('ksb-box-gradient-row');
            var boxShadowSpreadRow = document.getElementById('ksb-box-shadow-spread-row');
            var boxIconTypeRow = document.getElementById('ksb-box-icon-type-row');

            function openBoxForm(isEdit) {
                if (!boxFormOverlay) return;
                boxFormTitle.textContent = isEdit ? 'ボックスプリセットを編集' : 'ボックスプリセットを追加';
                boxFormOverlay.style.display = 'flex';
                updateBoxPreview();
            }

            function closeBoxForm() {
                if (!boxFormOverlay) return;
                boxFormOverlay.style.display = 'none';
                resetBoxForm();
            }

            function resetBoxForm() {
                if (!editBoxPresetId) return;
                editBoxPresetId.value = '';
                boxPresetNameInput.value = '';
                boxGradientInput.checked = false;
                boxBgColorInput.value = '#f5f5f5';
                boxGradient1Input.value = '#ffffff';
                boxGradient2Input.value = '#f0f0f0';
                boxBorderColorInput.value = '#cccccc';
                boxBorderStyleInput.value = 'solid';
                boxBorderWidthInput.value = '2';
                boxBorderRadiusInput.value = '0';
                boxPaddingTopInput.value = '20';
                boxPaddingBottomInput.value = '20';
                boxPaddingLeftInput.value = '20';
                boxPaddingRightInput.value = '20';
                boxShadowInput.checked = false;
                boxShadowSpreadInput.value = '5';
                boxIconInput.checked = false;
                boxIconTypeInput.value = 'info';
                toggleBoxGradient();
                toggleBoxShadow();
                toggleBoxIcon();
            }

            function populateBoxForm(preset) {
                editBoxPresetId.value = preset.id || '';
                boxPresetNameInput.value = preset.name || '';
                boxGradientInput.checked = !!preset.backgroundGradient;
                boxBgColorInput.value = preset.backgroundColor || '#f5f5f5';
                boxGradient1Input.value = preset.gradientColor1 || '#ffffff';
                boxGradient2Input.value = preset.gradientColor2 || '#f0f0f0';
                boxBorderColorInput.value = preset.borderColor || '#cccccc';
                boxBorderStyleInput.value = preset.borderStyle || 'solid';
                boxBorderWidthInput.value = preset.borderWidth || '2';
                boxBorderRadiusInput.value = preset.borderRadius || '0';
                boxPaddingTopInput.value = preset.paddingTop || '20';
                boxPaddingBottomInput.value = preset.paddingBottom || '20';
                boxPaddingLeftInput.value = preset.paddingLeft || '20';
                boxPaddingRightInput.value = preset.paddingRight || '20';
                boxShadowInput.checked = !!preset.hasShadow;
                boxShadowSpreadInput.value = preset.shadowSpread || '5';
                boxIconInput.checked = !!preset.hasIcon;
                boxIconTypeInput.value = preset.iconType || 'info';
                toggleBoxGradient();
                toggleBoxShadow();
                toggleBoxIcon();
            }

            function toggleBoxGradient() {
                if (!boxSolidBgRow) return;
                if (boxGradientInput.checked) {
                    boxSolidBgRow.style.display = 'none';
                    boxGradientRow.style.display = 'flex';
                } else {
                    boxSolidBgRow.style.display = 'block';
                    boxGradientRow.style.display = 'none';
                }
            }

            function toggleBoxShadow() {
                if (!boxShadowSpreadRow) return;
                boxShadowSpreadRow.style.display = boxShadowInput.checked ? 'block' : 'none';
            }

            function toggleBoxIcon() {
                if (!boxIconTypeRow) return;
                boxIconTypeRow.style.display = boxIconInput.checked ? 'block' : 'none';
            }

            function updateBoxPreview() {
                if (!boxPreview) return;
                var bgStyle = boxGradientInput.checked
                    ? 'linear-gradient(135deg, ' + boxGradient1Input.value + ' 0%, ' + boxGradient2Input.value + ' 100%)'
                    : boxBgColorInput.value;
                boxPreview.style.background = bgStyle;
                boxPreview.style.borderColor = boxBorderColorInput.value;
                boxPreview.style.borderWidth = boxBorderWidthInput.value + 'px';
                boxPreview.style.borderStyle = boxBorderStyleInput.value;
                boxPreview.style.borderRadius = boxBorderRadiusInput.value + 'px';
                boxPreview.style.padding = boxPaddingTopInput.value + 'px ' + boxPaddingRightInput.value + 'px ' + boxPaddingBottomInput.value + 'px ' + boxPaddingLeftInput.value + 'px';
                boxPreview.style.boxShadow = boxShadowInput.checked ? '0 0 ' + boxShadowSpreadInput.value + 'px rgba(0,0,0,0.1)' : 'none';
            }

            function saveBoxPresets(callback) {
                var formData = new FormData();
                formData.append('action', 'ksb_save_box_presets');
                formData.append('nonce', nonce);
                formData.append('presets', JSON.stringify(currentBoxPresets));

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success && callback) callback(data.data.presets);
                })
                .catch(function(err) {
                    console.error('Save box preset error:', err);
                    alert('保存に失敗しました');
                });
            }

            function bindBoxPresetActions() {
                document.querySelectorAll('.ksb-edit-box-preset').forEach(function(btn) {
                    btn.onclick = function() {
                        var id = this.getAttribute('data-id');
                        var preset = currentBoxPresets.find(function(p) { return p.id === id; });
                        if (preset) {
                            populateBoxForm(preset);
                            openBoxForm(true);
                            updateBoxPreview();
                        }
                    };
                });

                document.querySelectorAll('.ksb-delete-box-preset').forEach(function(btn) {
                    btn.onclick = function() {
                        var id = this.getAttribute('data-id');
                        if (confirm('このプリセットを削除しますか？')) {
                            currentBoxPresets = currentBoxPresets.filter(function(p) { return p.id !== id; });
                            saveBoxPresets(function() {
                                location.reload();
                            });
                        }
                    };
                });
            }

            if (addBoxPresetBtn) {
                addBoxPresetBtn.addEventListener('click', function() {
                    resetBoxForm();
                    openBoxForm(false);
                });

                boxFormClose.addEventListener('click', closeBoxForm);
                boxFormCancel.addEventListener('click', closeBoxForm);
                boxFormOverlay.addEventListener('click', function(e) {
                    if (e.target === boxFormOverlay) closeBoxForm();
                });

                // トグル
                boxGradientInput.addEventListener('change', function() {
                    toggleBoxGradient();
                    updateBoxPreview();
                });
                boxShadowInput.addEventListener('change', function() {
                    toggleBoxShadow();
                    updateBoxPreview();
                });
                boxIconInput.addEventListener('change', toggleBoxIcon);

                // プレビュー更新
                [boxPresetNameInput, boxBgColorInput, boxGradient1Input, boxGradient2Input,
                 boxBorderColorInput, boxBorderStyleInput, boxBorderWidthInput, boxBorderRadiusInput,
                 boxPaddingTopInput, boxPaddingBottomInput, boxPaddingLeftInput, boxPaddingRightInput,
                 boxShadowSpreadInput].forEach(function(input) {
                    if (input) input.addEventListener('input', updateBoxPreview);
                    if (input) input.addEventListener('change', updateBoxPreview);
                });

                boxFormSave.addEventListener('click', function() {
                    var name = boxPresetNameInput.value.trim();
                    if (!name) {
                        alert('プリセット名を入力してください');
                        boxPresetNameInput.focus();
                        return;
                    }

                    var presetData = {
                        id: editBoxPresetId.value || Date.now().toString(),
                        name: name,
                        backgroundGradient: boxGradientInput.checked,
                        backgroundColor: boxBgColorInput.value,
                        gradientColor1: boxGradient1Input.value,
                        gradientColor2: boxGradient2Input.value,
                        borderColor: boxBorderColorInput.value,
                        borderStyle: boxBorderStyleInput.value,
                        borderWidth: parseInt(boxBorderWidthInput.value) || 2,
                        borderRadius: parseInt(boxBorderRadiusInput.value) || 0,
                        paddingTop: parseInt(boxPaddingTopInput.value) || 20,
                        paddingBottom: parseInt(boxPaddingBottomInput.value) || 20,
                        paddingLeft: parseInt(boxPaddingLeftInput.value) || 20,
                        paddingRight: parseInt(boxPaddingRightInput.value) || 20,
                        hasShadow: boxShadowInput.checked,
                        shadowSpread: parseInt(boxShadowSpreadInput.value) || 5,
                        hasIcon: boxIconInput.checked,
                        iconType: boxIconTypeInput.value
                    };

                    if (editBoxPresetId.value) {
                        var idx = currentBoxPresets.findIndex(function(p) { return p.id === editBoxPresetId.value; });
                        if (idx !== -1) currentBoxPresets[idx] = presetData;
                    } else {
                        currentBoxPresets.push(presetData);
                    }

                    saveBoxPresets(function() {
                        closeBoxForm();
                        location.reload();
                    });
                });

                bindBoxPresetActions();
            }

            // ===== ボックス一括変換機能 =====
            var boxToPresetSelect = document.getElementById('ksb-box-to-preset');
            var boxConvertBtn = document.getElementById('ksb-box-bulk-convert-btn');
            var boxSelectAllCheckbox = document.getElementById('ksb-box-select-all-posts');
            var boxPostCheckboxes = document.querySelectorAll('.ksb-box-post-checkbox');
            var boxConvertPreview = document.getElementById('ksb-box-convert-preview');
            var boxConvertPreviewBox = document.getElementById('ksb-box-convert-preview-box');
            var boxConvertStatus = document.getElementById('ksb-box-convert-status');

            if (boxToPresetSelect) {
                boxToPresetSelect.addEventListener('change', function() {
                    var presetId = this.value;
                    var preset = currentBoxPresets.find(function(p) { return p.id === presetId; });

                    if (preset && boxConvertPreviewBox) {
                        var bgStyle = preset.backgroundGradient
                            ? 'linear-gradient(135deg, ' + preset.gradientColor1 + ' 0%, ' + preset.gradientColor2 + ' 100%)'
                            : (preset.backgroundColor || '#f5f5f5');
                        boxConvertPreviewBox.style.background = bgStyle;
                        boxConvertPreviewBox.style.borderColor = preset.borderColor || '#ccc';
                        boxConvertPreviewBox.style.borderWidth = (preset.borderWidth || 2) + 'px';
                        boxConvertPreviewBox.style.borderStyle = preset.borderStyle || 'solid';
                        boxConvertPreviewBox.style.borderRadius = (preset.borderRadius || 0) + 'px';
                        boxConvertPreviewBox.style.boxShadow = preset.hasShadow ? '0 0 ' + (preset.shadowSpread || 5) + 'px rgba(0,0,0,0.1)' : 'none';
                        boxConvertPreviewBox.textContent = preset.name;
                        boxConvertPreview.style.display = 'block';
                    } else {
                        boxConvertPreview.style.display = 'none';
                    }
                    updateBoxConvertButton();
                });

                if (boxSelectAllCheckbox) {
                    boxSelectAllCheckbox.addEventListener('change', function() {
                        boxPostCheckboxes.forEach(function(cb) {
                            cb.checked = boxSelectAllCheckbox.checked;
                        });
                        updateBoxConvertButton();
                    });
                }

                boxPostCheckboxes.forEach(function(cb) {
                    cb.addEventListener('change', function() {
                        var allChecked = Array.from(boxPostCheckboxes).every(function(c) { return c.checked; });
                        var someChecked = Array.from(boxPostCheckboxes).some(function(c) { return c.checked; });
                        if (boxSelectAllCheckbox) {
                            boxSelectAllCheckbox.checked = allChecked;
                            boxSelectAllCheckbox.indeterminate = !allChecked && someChecked;
                        }
                        updateBoxConvertButton();
                    });
                });

                function updateBoxConvertButton() {
                    var hasPreset = boxToPresetSelect.value !== '';
                    var hasSelectedPosts = Array.from(boxPostCheckboxes).some(function(cb) { return cb.checked; });
                    boxConvertBtn.disabled = !(hasPreset && hasSelectedPosts);
                }

                boxConvertBtn.addEventListener('click', function() {
                    var selectedPostIds = Array.from(boxPostCheckboxes)
                        .filter(function(cb) { return cb.checked; })
                        .map(function(cb) { return cb.value; });

                    if (selectedPostIds.length === 0 || !boxToPresetSelect.value) return;

                    if (!confirm(selectedPostIds.length + '件の記事のボックススタイルを変換します。\n\n※この操作は元に戻せません。\n続行しますか？')) {
                        return;
                    }

                    boxConvertBtn.disabled = true;
                    boxConvertStatus.textContent = '変換中...';
                    boxConvertStatus.className = 'ksb-convert-status loading';

                    var formData = new FormData();
                    formData.append('action', 'ksb_bulk_convert_box');
                    formData.append('nonce', nonce);
                    formData.append('to_preset_id', boxToPresetSelect.value);
                    selectedPostIds.forEach(function(id) {
                        formData.append('post_ids[]', id);
                    });

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            boxConvertStatus.textContent = data.data.updated_count + '件の記事を変換しました。';
                            boxConvertStatus.className = 'ksb-convert-status success';
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            boxConvertStatus.textContent = 'エラー: ' + (data.data.message || '変換に失敗しました');
                            boxConvertStatus.className = 'ksb-convert-status error';
                            boxConvertBtn.disabled = false;
                        }
                    })
                    .catch(function(error) {
                        boxConvertStatus.textContent = 'エラー: 通信に失敗しました';
                        boxConvertStatus.className = 'ksb-convert-status error';
                        boxConvertBtn.disabled = false;
                    });
                });

                updateBoxConvertButton();
            }
        })();
        </script>
        <?php
    }

    private function get_posts_using_block($block_name) {
        global $wpdb;

        $like_pattern = '%<!-- wp:' . $wpdb->esc_like($block_name) . '%';

        // ブロックエディタ対応の全投稿タイプを取得
        $post_types = get_post_types(['show_in_rest' => true], 'names');
        // 再利用ブロックを追加
        $post_types['wp_block'] = 'wp_block';
        // 不要なタイプを除外
        unset($post_types['attachment']);

        $post_types_placeholder = implode(',', array_fill(0, count($post_types), '%s'));

        $query = $wpdb->prepare(
            "SELECT ID, post_title, post_type, post_status, post_modified
            FROM {$wpdb->posts}
            WHERE post_content LIKE %s
            AND post_type IN ($post_types_placeholder)
            AND post_status IN ('publish', 'draft', 'pending', 'private', 'future')
            ORDER BY post_modified DESC",
            array_merge([$like_pattern], array_values($post_types))
        );

        $results = $wpdb->get_results($query);

        return $results ? $results : [];
    }

    private function get_post_type_label($post_type) {
        // WordPressに登録された投稿タイプのラベルを取得
        $post_type_obj = get_post_type_object($post_type);
        if ($post_type_obj && !empty($post_type_obj->labels->singular_name)) {
            return $post_type_obj->labels->singular_name;
        }

        // フォールバック
        $labels = [
            'post' => '投稿',
            'page' => '固定ページ',
            'wp_block' => '再利用ブロック'
        ];
        return isset($labels[$post_type]) ? $labels[$post_type] : $post_type;
    }

    private function get_post_status_label($status) {
        $labels = [
            'publish' => '公開',
            'draft' => '下書き',
            'pending' => 'レビュー待ち',
            'private' => '非公開',
            'future' => '予約済み'
        ];
        return isset($labels[$status]) ? $labels[$status] : $status;
    }

    // プリセットを保存するAJAXハンドラー
    public function ajax_save_presets() {
        check_ajax_referer('ksb_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $presets = isset($_POST['presets']) ? json_decode(stripslashes($_POST['presets']), true) : [];

        if (!is_array($presets)) {
            wp_send_json_error(['message' => '無効なデータ形式です']);
        }

        // サニタイズ
        $sanitized_presets = [];
        foreach ($presets as $preset) {
            $sanitized_presets[] = [
                'id' => sanitize_text_field($preset['id'] ?? ''),
                'name' => sanitize_text_field($preset['name'] ?? ''),
                'avatarUrl' => esc_url_raw($preset['avatarUrl'] ?? ''),
                'avatarId' => absint($preset['avatarId'] ?? 0),
                'avatarPosition' => sanitize_text_field($preset['avatarPosition'] ?? 'left'),
                'avatarShape' => sanitize_text_field($preset['avatarShape'] ?? 'circle'),
                'avatarSize' => absint($preset['avatarSize'] ?? 60),
                'speakerName' => sanitize_text_field($preset['speakerName'] ?? ''),
                'speakerTitle' => sanitize_text_field($preset['speakerTitle'] ?? ''),
                'profileUrl' => esc_url_raw($preset['profileUrl'] ?? ''),
                'nameLabelColor' => sanitize_hex_color($preset['nameLabelColor'] ?? '#007cba'),
                'bubbleStyle' => sanitize_text_field($preset['bubbleStyle'] ?? 'standard'),
                'bubbleMaxWidth' => absint($preset['bubbleMaxWidth'] ?? 100),
                'bubbleColor' => sanitize_hex_color($preset['bubbleColor'] ?? '#f0f8ff'),
                'textColor' => sanitize_hex_color($preset['textColor'] ?? '#333333'),
                'borderColor' => sanitize_hex_color($preset['borderColor'] ?? '#007cba'),
                'borderWidth' => absint($preset['borderWidth'] ?? 2),
                'showTail' => !isset($preset['showTail']) || $preset['showTail'] ? true : false,
            ];
        }

        update_option(self::PRESETS_OPTION_KEY, $sanitized_presets);
        wp_send_json_success(['presets' => $sanitized_presets]);
    }

    // プリセットを取得するAJAXハンドラー
    public function ajax_get_presets() {
        check_ajax_referer('ksb_nonce', 'nonce');

        $presets = get_option(self::PRESETS_OPTION_KEY, []);
        wp_send_json_success(['presets' => $presets]);
    }

    // 吹き出しブロックを一括変換するAJAXハンドラー
    public function ajax_bulk_convert_speech_bubble() {
        check_ajax_referer('ksb_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $post_ids = isset($_POST['post_ids']) ? array_map('absint', (array)$_POST['post_ids']) : [];
        $from_preset_id = isset($_POST['from_preset_id']) ? sanitize_text_field($_POST['from_preset_id']) : '';
        $to_preset_id = isset($_POST['to_preset_id']) ? sanitize_text_field($_POST['to_preset_id']) : '';

        if (empty($post_ids) || empty($to_preset_id)) {
            wp_send_json_error(['message' => '必要なパラメータが不足しています']);
        }

        $presets = get_option(self::PRESETS_OPTION_KEY, []);
        $to_preset = null;
        $from_preset = null;

        foreach ($presets as $preset) {
            if ($preset['id'] === $to_preset_id) {
                $to_preset = $preset;
            }
            if ($from_preset_id && $preset['id'] === $from_preset_id) {
                $from_preset = $preset;
            }
        }

        if (!$to_preset) {
            wp_send_json_error(['message' => '変換先プリセットが見つかりません']);
        }

        $updated_count = 0;
        $errors = [];

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $errors[] = "投稿ID {$post_id} が見つかりません";
                continue;
            }

            $content = $post->post_content;
            $original_content = $content;

            // 吹き出しブロックを検索して変換
            $content = $this->convert_speech_bubble_blocks($content, $from_preset, $to_preset);

            if ($content !== $original_content) {
                $result = wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $content
                ], true);

                if (is_wp_error($result)) {
                    $errors[] = "投稿ID {$post_id} の更新に失敗: " . $result->get_error_message();
                } else {
                    $updated_count++;
                }
            }
        }

        wp_send_json_success([
            'updated_count' => $updated_count,
            'errors' => $errors
        ]);
    }

    // 吹き出しブロックの属性を変換
    private function convert_speech_bubble_blocks($content, $from_preset, $to_preset) {
        // ブロック全体にマッチ（属性あり・なし両方に対応）
        $pattern = '/(<!-- wp:kashiwazaki-seo-blocks\/speech-bubble\s*)(\{[^}]*\})?(\s*-->)(.*?)(<!-- \/wp:kashiwazaki-seo-blocks\/speech-bubble -->)/s';

        return preg_replace_callback($pattern, function($matches) use ($from_preset, $to_preset) {
            $attrs_json = $matches[2] ?? '';
            $attrs = !empty($attrs_json) ? json_decode($attrs_json, true) : [];
            $html = $matches[4];

            if (!is_array($attrs)) {
                $attrs = [];
            }

            // デフォルト値を設定
            $attrs = array_merge([
                'content' => '',
                'avatarUrl' => '',
                'avatarId' => 0,
                'avatarPosition' => 'left',
                'avatarShape' => 'circle',
                'avatarSize' => 60,
                'speakerName' => '',
                'speakerTitle' => '',
                'profileUrl' => '',
                'bubbleColor' => '#f0f8ff',
                'textColor' => '#333333',
                'borderColor' => '#007cba',
                'borderWidth' => 2,
                'showTail' => true,
                'bubbleStyle' => 'standard',
                'nameLabelColor' => '#007cba',
                'bubbleMaxWidth' => 100,
            ], $attrs);

            // from_presetが指定されている場合、条件に合致するかチェック
            if ($from_preset) {
                $match = false;
                if (!empty($from_preset['avatarUrl']) && $attrs['avatarUrl'] === $from_preset['avatarUrl']) {
                    $match = true;
                }
                if (!empty($from_preset['speakerName']) && $attrs['speakerName'] === $from_preset['speakerName']) {
                    $match = true;
                }
                if (!$match) {
                    return $matches[0];
                }
            }

            // HTMLからコンテンツを抽出
            if (preg_match('/<div class="ksb-speech-bubble-content">(.*?)<\/div>/s', $html, $content_match)) {
                $attrs['content'] = $content_match[1];
            }

            // to_presetの値で上書き
            // プリセットの値を適用（空でない場合のみ）
            $attrs['avatarUrl'] = !empty($to_preset['avatarUrl']) ? $to_preset['avatarUrl'] : $attrs['avatarUrl'];
            $attrs['avatarId'] = isset($to_preset['avatarId']) && $to_preset['avatarId'] > 0 ? $to_preset['avatarId'] : $attrs['avatarId'];
            $attrs['avatarPosition'] = !empty($to_preset['avatarPosition']) ? $to_preset['avatarPosition'] : $attrs['avatarPosition'];
            $attrs['avatarShape'] = !empty($to_preset['avatarShape']) ? $to_preset['avatarShape'] : $attrs['avatarShape'];
            $attrs['avatarSize'] = isset($to_preset['avatarSize']) && $to_preset['avatarSize'] > 0 ? $to_preset['avatarSize'] : $attrs['avatarSize'];
            $attrs['speakerName'] = isset($to_preset['speakerName']) ? $to_preset['speakerName'] : $attrs['speakerName'];
            $attrs['speakerTitle'] = isset($to_preset['speakerTitle']) ? $to_preset['speakerTitle'] : $attrs['speakerTitle'];
            $attrs['profileUrl'] = isset($to_preset['profileUrl']) ? $to_preset['profileUrl'] : $attrs['profileUrl'];
            $attrs['nameLabelColor'] = !empty($to_preset['nameLabelColor']) ? $to_preset['nameLabelColor'] : $attrs['nameLabelColor'];
            $attrs['bubbleColor'] = !empty($to_preset['bubbleColor']) ? $to_preset['bubbleColor'] : $attrs['bubbleColor'];
            $attrs['textColor'] = !empty($to_preset['textColor']) ? $to_preset['textColor'] : $attrs['textColor'];
            $attrs['borderColor'] = !empty($to_preset['borderColor']) ? $to_preset['borderColor'] : $attrs['borderColor'];
            $attrs['borderWidth'] = isset($to_preset['borderWidth']) ? intval($to_preset['borderWidth']) : $attrs['borderWidth'];
            $attrs['bubbleStyle'] = !empty($to_preset['bubbleStyle']) ? $to_preset['bubbleStyle'] : $attrs['bubbleStyle'];
            $attrs['bubbleMaxWidth'] = isset($to_preset['bubbleMaxWidth']) && $to_preset['bubbleMaxWidth'] > 0 ? intval($to_preset['bubbleMaxWidth']) : $attrs['bubbleMaxWidth'];
            $attrs['showTail'] = isset($to_preset['showTail']) ? (bool)$to_preset['showTail'] : $attrs['showTail'];

            // HTMLを完全に再生成
            $new_html = $this->generate_speech_bubble_html($attrs);

            // デフォルト値と同じ属性は保存しない（ブロックエディタの動作に合わせる）
            $attrs_to_save = $this->filter_default_attrs($attrs, [
                'content' => '',
                'avatarUrl' => '',
                'avatarId' => 0,
                'avatarPosition' => 'left',
                'avatarShape' => 'circle',
                'avatarSize' => 60,
                'speakerName' => '',
                'speakerTitle' => '',
                'profileUrl' => '',
                'bubbleColor' => '#f0f8ff',
                'textColor' => '#333333',
                'borderColor' => '#007cba',
                'borderWidth' => 2,
                'showTail' => true,
                'bubbleStyle' => 'standard',
                'nameLabelColor' => '#007cba',
                'bubbleMaxWidth' => 100,
            ]);

            $attrs_str = !empty($attrs_to_save) ? ' ' . wp_json_encode($attrs_to_save, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

            return '<!-- wp:kashiwazaki-seo-blocks/speech-bubble' . $attrs_str . ' -->' . $new_html . '<!-- /wp:kashiwazaki-seo-blocks/speech-bubble -->';
        }, $content);
    }

    // デフォルト値と同じ属性を除外
    private function filter_default_attrs($attrs, $defaults) {
        $filtered = [];
        foreach ($attrs as $key => $value) {
            if (!isset($defaults[$key]) || $defaults[$key] !== $value) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }

    // 吹き出しHTMLを生成
    private function generate_speech_bubble_html($attrs) {
        // デフォルト値を設定
        $attrs = array_merge([
            'avatarPosition' => 'left',
            'bubbleStyle' => 'standard',
            'avatarSize' => 60,
            'avatarShape' => 'circle',
            'bubbleColor' => '#f0f8ff',
            'textColor' => '#333333',
            'borderColor' => '#007cba',
            'borderWidth' => 2,
            'bubbleMaxWidth' => 100,
            'showTail' => true,
            'nameLabelColor' => '#007cba',
            'avatarUrl' => '',
            'speakerName' => '',
            'speakerTitle' => '',
            'profileUrl' => '',
            'content' => '',
        ], $attrs);

        $avatar_position = esc_attr($attrs['avatarPosition']);
        $bubble_style = esc_attr($attrs['bubbleStyle']);
        $avatar_size = intval($attrs['avatarSize']);
        $avatar_shape = $attrs['avatarShape'];
        $border_radius = $avatar_shape === 'circle' ? '50%' : ($avatar_shape === 'rounded' ? '10px' : '0');
        $bubble_color = esc_attr(!empty($attrs['bubbleColor']) ? $attrs['bubbleColor'] : '#f0f8ff');
        $text_color = esc_attr(!empty($attrs['textColor']) ? $attrs['textColor'] : '#333333');
        $border_color = esc_attr(!empty($attrs['borderColor']) ? $attrs['borderColor'] : '#007cba');
        $border_width = intval($attrs['borderWidth']);
        $bubble_max_width = intval($attrs['bubbleMaxWidth']) ?: 100;
        $show_tail = isset($attrs['showTail']) ? $attrs['showTail'] : true;
        $name_label_color = esc_attr(!empty($attrs['nameLabelColor']) ? $attrs['nameLabelColor'] : '#007cba');
        $bubble_border_radius = $bubble_style === 'rounded' ? '20px' : '10px';
        $max_width_style = $bubble_max_width === 100 ? 'none' : $bubble_max_width . '%';

        // 改行なしの1行HTMLを生成（ブロックエディタのsave関数と同じ形式）
        $html = '<div class="wp-block-kashiwazaki-seo-blocks-speech-bubble ksb-speech-bubble-wrapper ksb-avatar-' . $avatar_position . ' ksb-bubble-' . $bubble_style . '">';
        $html .= '<div class="ksb-speech-bubble-inner">';

        // アバター部分
        $html .= '<div class="ksb-speech-bubble-avatar">';

        // 肩書
        if (!empty($attrs['speakerTitle'])) {
            $html .= '<div class="ksb-speech-bubble-title" style="color:' . $name_label_color . ';font-size:11px;margin-bottom:3px;opacity:0.8">' . esc_html($attrs['speakerTitle']) . '</div>';
        }

        // アバター画像
        $avatar_style = 'width:' . $avatar_size . 'px;height:' . $avatar_size . 'px;border-radius:' . $border_radius . ';object-fit:cover';

        if (!empty($attrs['avatarUrl'])) {
            $avatar_img = '<img src="' . esc_url($attrs['avatarUrl']) . '" alt="' . esc_attr($attrs['speakerName'] ?: 'アバター') . '" style="' . $avatar_style . '"/>';
        } else {
            $avatar_img = '<div style="' . $avatar_style . ';background-color:#e0e0e0;display:flex;align-items:center;justify-content:center;font-size:' . intval($avatar_size / 3) . 'px;color:#666"><span>画像</span></div>';
        }

        if (!empty($attrs['profileUrl'])) {
            $html .= '<a href="' . esc_url($attrs['profileUrl']) . '" class="ksb-speech-bubble-avatar-link" target="_blank" rel="noopener noreferrer">' . $avatar_img . '</a>';
        } else {
            $html .= $avatar_img;
        }

        // 名前
        if (!empty($attrs['speakerName'])) {
            $name_style = 'color:' . $name_label_color . ';font-size:14px;margin-top:5px;font-weight:bold';
            if (!empty($attrs['profileUrl'])) {
                $html .= '<a href="' . esc_url($attrs['profileUrl']) . '" class="ksb-speech-bubble-name-link" style="text-decoration:none" target="_blank" rel="noopener noreferrer">';
                $html .= '<div class="ksb-speech-bubble-name" style="' . $name_style . '">' . esc_html($attrs['speakerName']) . '</div>';
                $html .= '</a>';
            } else {
                $html .= '<div class="ksb-speech-bubble-name" style="' . $name_style . '">' . esc_html($attrs['speakerName']) . '</div>';
            }
        }

        $html .= '</div>'; // .ksb-speech-bubble-avatar

        // 吹き出し部分
        $tail_class = $show_tail ? ' has-tail' : '';
        $bubble_wrapper_style = '--tail-color:' . $bubble_color . ';--tail-border:' . $border_color . ';--bubble-bg-color:' . $bubble_color . ';--bubble-border-color:' . $border_color . ';--border-width:' . $border_width . 'px;max-width:' . $max_width_style;
        $html .= '<div class="ksb-speech-bubble-bubble' . $tail_class . '" style="' . $bubble_wrapper_style . '">';

        $bubble_content_style = 'background-color:' . $bubble_color . ';color:' . $text_color . ';border:' . $border_width . 'px solid ' . $border_color . ';border-radius:' . $bubble_border_radius . ';padding:15px 20px;position:relative';
        $html .= '<div style="' . $bubble_content_style . '">';
        $html .= '<div class="ksb-speech-bubble-content">' . $attrs['content'] . '</div>';
        $html .= '</div>';

        $html .= '</div>'; // .ksb-speech-bubble-bubble
        $html .= '</div>'; // .ksb-speech-bubble-inner
        $html .= '</div>';

        return "\n" . $html . "\n";
    }

    // ===== ボタンプリセット用AJAXハンドラー =====

    // ボタンプリセットを保存
    public function ajax_save_button_presets() {
        check_ajax_referer('ksb_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $presets = isset($_POST['presets']) ? json_decode(stripslashes($_POST['presets']), true) : [];

        if (!is_array($presets)) {
            wp_send_json_error(['message' => '無効なデータ形式です']);
        }

        $sanitized_presets = [];
        foreach ($presets as $preset) {
            $sanitized_presets[] = [
                'id' => sanitize_text_field($preset['id'] ?? ''),
                'name' => sanitize_text_field($preset['name'] ?? ''),
                'backgroundColor' => sanitize_hex_color($preset['backgroundColor'] ?? '#007cba'),
                'textColor' => sanitize_hex_color($preset['textColor'] ?? '#ffffff'),
                'borderColor' => sanitize_hex_color($preset['borderColor'] ?? ''),
                'borderWidth' => absint($preset['borderWidth'] ?? 2),
                'borderRadius' => absint($preset['borderRadius'] ?? 5),
                'paddingVertical' => absint($preset['paddingVertical'] ?? 12),
                'paddingHorizontal' => absint($preset['paddingHorizontal'] ?? 24),
                'fontSize' => absint($preset['fontSize'] ?? 16),
                'hasShadow' => !empty($preset['hasShadow']),
                'buttonWidth' => sanitize_text_field($preset['buttonWidth'] ?? 'auto'),
            ];
        }

        update_option(self::BUTTON_PRESETS_OPTION_KEY, $sanitized_presets);
        wp_send_json_success(['presets' => $sanitized_presets]);
    }

    // ボタンプリセットを取得
    public function ajax_get_button_presets() {
        check_ajax_referer('ksb_nonce', 'nonce');

        $presets = get_option(self::BUTTON_PRESETS_OPTION_KEY, []);
        wp_send_json_success(['presets' => $presets]);
    }

    // ボタンブロックを一括変換
    public function ajax_bulk_convert_button() {
        check_ajax_referer('ksb_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $post_ids = isset($_POST['post_ids']) ? array_map('absint', (array)$_POST['post_ids']) : [];
        $to_preset_id = isset($_POST['to_preset_id']) ? sanitize_text_field($_POST['to_preset_id']) : '';

        if (empty($post_ids) || empty($to_preset_id)) {
            wp_send_json_error(['message' => '必要なパラメータが不足しています']);
        }

        $presets = get_option(self::BUTTON_PRESETS_OPTION_KEY, []);
        $to_preset = null;

        foreach ($presets as $preset) {
            if ($preset['id'] === $to_preset_id) {
                $to_preset = $preset;
                break;
            }
        }

        if (!$to_preset) {
            wp_send_json_error(['message' => '変換先プリセットが見つかりません']);
        }

        $updated_count = 0;
        $errors = [];

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $errors[] = "投稿ID {$post_id} が見つかりません";
                continue;
            }

            $content = $post->post_content;
            $original_content = $content;

            $content = $this->convert_button_blocks($content, $to_preset);

            if ($content !== $original_content) {
                $result = wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $content
                ], true);

                if (is_wp_error($result)) {
                    $errors[] = "投稿ID {$post_id} の更新に失敗: " . $result->get_error_message();
                } else {
                    $updated_count++;
                }
            }
        }

        wp_send_json_success([
            'updated_count' => $updated_count,
            'errors' => $errors
        ]);
    }

    // ボタンブロックの属性を変換
    private function convert_button_blocks($content, $to_preset) {
        $pattern = '/(<!-- wp:kashiwazaki-seo-blocks\/button\s*)(\{[^}]*\})?(\s*-->)(.*?)(<!-- \/wp:kashiwazaki-seo-blocks\/button -->)/s';

        return preg_replace_callback($pattern, function($matches) use ($to_preset) {
            $attrs_json = $matches[2] ?? '';
            $attrs = !empty($attrs_json) ? json_decode($attrs_json, true) : [];
            $html = $matches[4];

            if (!is_array($attrs)) {
                $attrs = [];
            }

            // デフォルト値を設定
            $attrs = array_merge([
                'text' => '',
                'url' => '',
                'backgroundColor' => '#007cba',
                'textColor' => '#ffffff',
                'borderColor' => '',
                'borderWidth' => 2,
                'borderRadius' => 5,
                'paddingVertical' => 12,
                'paddingHorizontal' => 24,
                'fontSize' => 16,
                'hasShadow' => false,
                'shadowColor' => 'rgba(0,0,0,0.3)',
                'openInNewTab' => false,
                'buttonWidth' => 'auto',
            ], $attrs);

            // HTMLからテキストを抽出
            if (preg_match('/<span class="ksb-button-text">(.*?)<\/span>/s', $html, $text_match)) {
                $attrs['text'] = $text_match[1];
            }

            // スタイル属性のみ上書き（テキストとURLは保持、空でない場合のみ）
            $attrs['backgroundColor'] = !empty($to_preset['backgroundColor']) ? $to_preset['backgroundColor'] : $attrs['backgroundColor'];
            $attrs['textColor'] = !empty($to_preset['textColor']) ? $to_preset['textColor'] : $attrs['textColor'];
            $attrs['borderColor'] = isset($to_preset['borderColor']) ? $to_preset['borderColor'] : $attrs['borderColor'];
            $attrs['borderWidth'] = isset($to_preset['borderWidth']) ? intval($to_preset['borderWidth']) : $attrs['borderWidth'];
            $attrs['borderRadius'] = isset($to_preset['borderRadius']) ? intval($to_preset['borderRadius']) : $attrs['borderRadius'];
            $attrs['paddingVertical'] = isset($to_preset['paddingVertical']) ? intval($to_preset['paddingVertical']) : $attrs['paddingVertical'];
            $attrs['paddingHorizontal'] = isset($to_preset['paddingHorizontal']) ? intval($to_preset['paddingHorizontal']) : $attrs['paddingHorizontal'];
            $attrs['fontSize'] = isset($to_preset['fontSize']) ? intval($to_preset['fontSize']) : $attrs['fontSize'];
            $attrs['hasShadow'] = isset($to_preset['hasShadow']) ? (bool)$to_preset['hasShadow'] : $attrs['hasShadow'];
            $attrs['buttonWidth'] = !empty($to_preset['buttonWidth']) ? $to_preset['buttonWidth'] : $attrs['buttonWidth'];

            // HTMLを完全に再生成
            $new_html = $this->generate_button_html($attrs);

            // デフォルト値と同じ属性は保存しない
            $attrs_to_save = $this->filter_default_attrs($attrs, [
                'text' => '',
                'url' => '',
                'backgroundColor' => '#007cba',
                'textColor' => '#ffffff',
                'borderColor' => '',
                'borderWidth' => 2,
                'borderRadius' => 5,
                'paddingVertical' => 12,
                'paddingHorizontal' => 24,
                'fontSize' => 16,
                'hasShadow' => false,
                'shadowColor' => 'rgba(0,0,0,0.3)',
                'openInNewTab' => false,
                'buttonWidth' => 'auto',
            ]);

            $attrs_str = !empty($attrs_to_save) ? ' ' . wp_json_encode($attrs_to_save, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

            return '<!-- wp:kashiwazaki-seo-blocks/button' . $attrs_str . ' -->' . $new_html . '<!-- /wp:kashiwazaki-seo-blocks/button -->';
        }, $content);
    }

    // ボタンHTMLを生成
    private function generate_button_html($attrs) {
        // デフォルト値を設定
        $attrs = array_merge([
            'text' => '',
            'url' => '',
            'backgroundColor' => '#007cba',
            'textColor' => '#ffffff',
            'borderColor' => '',
            'borderWidth' => 2,
            'borderRadius' => 5,
            'paddingVertical' => 12,
            'paddingHorizontal' => 24,
            'fontSize' => 16,
            'hasShadow' => false,
            'shadowColor' => 'rgba(0,0,0,0.3)',
            'openInNewTab' => false,
            'buttonWidth' => 'auto',
        ], $attrs);

        $bg_color = esc_attr(!empty($attrs['backgroundColor']) ? $attrs['backgroundColor'] : '#007cba');
        $text_color = esc_attr(!empty($attrs['textColor']) ? $attrs['textColor'] : '#ffffff');
        $border_color = !empty($attrs['borderColor']) ? esc_attr($attrs['borderColor']) : '';
        $border_width = intval($attrs['borderWidth']);
        $border_radius = intval($attrs['borderRadius']);
        $padding_v = intval($attrs['paddingVertical']);
        $padding_h = intval($attrs['paddingHorizontal']);
        $font_size = intval($attrs['fontSize']);
        $has_shadow = !empty($attrs['hasShadow']);
        $shadow_color = esc_attr(!empty($attrs['shadowColor']) ? $attrs['shadowColor'] : 'rgba(0,0,0,0.3)');
        $button_width = $attrs['buttonWidth'] === 'full' ? '100%' : 'auto';
        $url = !empty($attrs['url']) ? esc_url($attrs['url']) : '#';
        $open_in_new_tab = !empty($attrs['openInNewTab']);

        $style_parts = [
            'background-color:' . $bg_color,
            'color:' . $text_color,
        ];
        if (!empty($border_color)) {
            $style_parts[] = 'border-color:' . $border_color;
            $style_parts[] = 'border-width:' . $border_width . 'px';
            $style_parts[] = 'border-style:solid';
        }
        $style_parts[] = 'border-radius:' . $border_radius . 'px';
        $style_parts[] = 'padding:' . $padding_v . 'px ' . $padding_h . 'px';
        $style_parts[] = 'font-size:' . $font_size . 'px';
        if ($has_shadow) {
            $style_parts[] = 'box-shadow:0 2px 5px ' . $shadow_color;
        }
        $style_parts[] = 'width:' . $button_width;
        $style_parts[] = 'display:inline-block';
        $style_parts[] = 'text-decoration:none';

        $style = implode(';', $style_parts);

        $target_attr = $open_in_new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

        $html = "\n" . '<div class="wp-block-kashiwazaki-seo-blocks-button ksb-button-wrapper">';
        $html .= '<a class="ksb-button" href="' . $url . '" style="' . $style . '"' . $target_attr . '>';
        $html .= '<span class="ksb-button-text">' . $attrs['text'] . '</span>';
        $html .= '</a>';
        $html .= '</div>' . "\n";

        return $html;
    }

    // ===== ボックスプリセット用AJAXハンドラー =====

    public function ajax_save_box_presets() {
        check_ajax_referer('ksb_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $presets = isset($_POST['presets']) ? json_decode(stripslashes($_POST['presets']), true) : [];

        if (!is_array($presets)) {
            wp_send_json_error(['message' => '無効なデータ形式です']);
        }

        $sanitized_presets = [];
        foreach ($presets as $preset) {
            $sanitized_presets[] = [
                'id' => sanitize_text_field($preset['id'] ?? ''),
                'name' => sanitize_text_field($preset['name'] ?? ''),
                'backgroundColor' => sanitize_hex_color($preset['backgroundColor'] ?? ''),
                'borderColor' => sanitize_hex_color($preset['borderColor'] ?? ''),
                'borderWidth' => absint($preset['borderWidth'] ?? 2),
                'borderRadius' => absint($preset['borderRadius'] ?? 0),
                'borderStyle' => sanitize_text_field($preset['borderStyle'] ?? 'solid'),
                'paddingTop' => absint($preset['paddingTop'] ?? 20),
                'paddingBottom' => absint($preset['paddingBottom'] ?? 20),
                'paddingLeft' => absint($preset['paddingLeft'] ?? 20),
                'paddingRight' => absint($preset['paddingRight'] ?? 20),
                'hasShadow' => !empty($preset['hasShadow']),
                'shadowSpread' => absint($preset['shadowSpread'] ?? 5),
                'backgroundGradient' => !empty($preset['backgroundGradient']),
                'gradientColor1' => sanitize_hex_color($preset['gradientColor1'] ?? '#ffffff'),
                'gradientColor2' => sanitize_hex_color($preset['gradientColor2'] ?? '#f0f0f0'),
                'hasIcon' => !empty($preset['hasIcon']),
                'iconType' => sanitize_text_field($preset['iconType'] ?? 'info'),
            ];
        }

        update_option(self::BOX_PRESETS_OPTION_KEY, $sanitized_presets);
        wp_send_json_success(['presets' => $sanitized_presets]);
    }

    public function ajax_get_box_presets() {
        check_ajax_referer('ksb_nonce', 'nonce');

        $presets = get_option(self::BOX_PRESETS_OPTION_KEY, []);
        wp_send_json_success(['presets' => $presets]);
    }

    public function ajax_bulk_convert_box() {
        check_ajax_referer('ksb_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        $post_ids = isset($_POST['post_ids']) ? array_map('absint', (array)$_POST['post_ids']) : [];
        $to_preset_id = isset($_POST['to_preset_id']) ? sanitize_text_field($_POST['to_preset_id']) : '';

        if (empty($post_ids) || empty($to_preset_id)) {
            wp_send_json_error(['message' => '必要なパラメータが不足しています']);
        }

        $presets = get_option(self::BOX_PRESETS_OPTION_KEY, []);
        $to_preset = null;

        foreach ($presets as $preset) {
            if ($preset['id'] === $to_preset_id) {
                $to_preset = $preset;
                break;
            }
        }

        if (!$to_preset) {
            wp_send_json_error(['message' => '変換先プリセットが見つかりません']);
        }

        $updated_count = 0;
        $errors = [];

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $errors[] = "投稿ID {$post_id} が見つかりません";
                continue;
            }

            $content = $post->post_content;
            $original_content = $content;

            $content = $this->convert_box_blocks($content, $to_preset);

            if ($content !== $original_content) {
                $result = wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $content
                ], true);

                if (is_wp_error($result)) {
                    $errors[] = "投稿ID {$post_id} の更新に失敗: " . $result->get_error_message();
                } else {
                    $updated_count++;
                }
            }
        }

        wp_send_json_success([
            'updated_count' => $updated_count,
            'errors' => $errors
        ]);
    }

    private function convert_box_blocks($content, $to_preset) {
        $pattern = '/(<!-- wp:kashiwazaki-seo-blocks\/box\s*)(\{[^}]*\})?(\s*-->)(.*?)(<!-- \/wp:kashiwazaki-seo-blocks\/box -->)/s';

        return preg_replace_callback($pattern, function($matches) use ($to_preset) {
            $attrs_json = $matches[2] ?? '';
            $attrs = !empty($attrs_json) ? json_decode($attrs_json, true) : [];
            $html = $matches[4];

            if (!is_array($attrs)) {
                $attrs = [];
            }

            // デフォルト値を設定
            $attrs = array_merge([
                'backgroundColor' => '#f5f5f5',
                'borderColor' => '#cccccc',
                'borderWidth' => 2,
                'borderRadius' => 0,
                'borderStyle' => 'solid',
                'paddingTop' => 20,
                'paddingBottom' => 20,
                'paddingLeft' => 20,
                'paddingRight' => 20,
                'marginTop' => 0,
                'marginBottom' => 0,
                'marginLeft' => 0,
                'marginRight' => 0,
                'hasShadow' => false,
                'shadowColor' => 'rgba(0,0,0,0.1)',
                'shadowSpread' => 5,
                'backgroundGradient' => false,
                'gradientColor1' => '#ffffff',
                'gradientColor2' => '#f0f0f0',
                'hasIcon' => false,
                'iconType' => 'info',
                'boxWidth' => 0,
                'boxWidthUnit' => '%',
                'boxAlignment' => 'none',
            ], $attrs);

            // InnerBlocksの内容を抽出（最初の<div>タグの中身）
            $inner_content = '';
            if (preg_match('/<div[^>]*class="[^"]*wp-block-kashiwazaki-seo-blocks-box[^"]*"[^>]*>(.*)<\/div>\s*$/s', $html, $inner_match)) {
                $inner_content = $inner_match[1];
            }

            // スタイル属性のみ上書き（内容は保持、空でない場合のみ）
            if (!empty($to_preset['backgroundColor'])) {
                $attrs['backgroundColor'] = $to_preset['backgroundColor'];
            }
            if (!empty($to_preset['borderColor'])) {
                $attrs['borderColor'] = $to_preset['borderColor'];
            }
            $attrs['borderWidth'] = isset($to_preset['borderWidth']) ? intval($to_preset['borderWidth']) : $attrs['borderWidth'];
            $attrs['borderRadius'] = isset($to_preset['borderRadius']) ? intval($to_preset['borderRadius']) : $attrs['borderRadius'];
            $attrs['borderStyle'] = !empty($to_preset['borderStyle']) ? $to_preset['borderStyle'] : $attrs['borderStyle'];
            $attrs['paddingTop'] = isset($to_preset['paddingTop']) ? intval($to_preset['paddingTop']) : $attrs['paddingTop'];
            $attrs['paddingBottom'] = isset($to_preset['paddingBottom']) ? intval($to_preset['paddingBottom']) : $attrs['paddingBottom'];
            $attrs['paddingLeft'] = isset($to_preset['paddingLeft']) ? intval($to_preset['paddingLeft']) : $attrs['paddingLeft'];
            $attrs['paddingRight'] = isset($to_preset['paddingRight']) ? intval($to_preset['paddingRight']) : $attrs['paddingRight'];
            $attrs['hasShadow'] = isset($to_preset['hasShadow']) ? (bool)$to_preset['hasShadow'] : $attrs['hasShadow'];
            $attrs['shadowSpread'] = isset($to_preset['shadowSpread']) ? intval($to_preset['shadowSpread']) : $attrs['shadowSpread'];
            $attrs['backgroundGradient'] = isset($to_preset['backgroundGradient']) ? (bool)$to_preset['backgroundGradient'] : $attrs['backgroundGradient'];
            if (!empty($to_preset['gradientColor1'])) {
                $attrs['gradientColor1'] = $to_preset['gradientColor1'];
            }
            if (!empty($to_preset['gradientColor2'])) {
                $attrs['gradientColor2'] = $to_preset['gradientColor2'];
            }
            $attrs['hasIcon'] = isset($to_preset['hasIcon']) ? (bool)$to_preset['hasIcon'] : $attrs['hasIcon'];
            if (!empty($to_preset['iconType'])) {
                $attrs['iconType'] = $to_preset['iconType'];
            }

            // HTMLを完全に再生成
            $new_html = $this->generate_box_html($attrs, $inner_content);

            // デフォルト値と同じ属性は保存しない
            $attrs_to_save = $this->filter_default_attrs($attrs, [
                'backgroundColor' => '#f5f5f5',
                'borderColor' => '#cccccc',
                'borderWidth' => 2,
                'borderRadius' => 0,
                'borderStyle' => 'solid',
                'paddingTop' => 20,
                'paddingBottom' => 20,
                'paddingLeft' => 20,
                'paddingRight' => 20,
                'marginTop' => 0,
                'marginBottom' => 0,
                'marginLeft' => 0,
                'marginRight' => 0,
                'hasShadow' => false,
                'shadowColor' => 'rgba(0,0,0,0.1)',
                'shadowSpread' => 5,
                'backgroundGradient' => false,
                'gradientColor1' => '#ffffff',
                'gradientColor2' => '#f0f0f0',
                'hasIcon' => false,
                'iconType' => 'info',
                'boxWidth' => 0,
                'boxWidthUnit' => '%',
                'boxAlignment' => 'none',
            ]);

            $attrs_str = !empty($attrs_to_save) ? ' ' . wp_json_encode($attrs_to_save, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

            return '<!-- wp:kashiwazaki-seo-blocks/box' . $attrs_str . ' -->' . $new_html . '<!-- /wp:kashiwazaki-seo-blocks/box -->';
        }, $content);
    }

    // ボックスHTMLを生成
    private function generate_box_html($attrs, $inner_content) {
        // デフォルト値を設定
        $attrs = array_merge([
            'backgroundColor' => '#f5f5f5',
            'borderColor' => '#cccccc',
            'borderWidth' => 2,
            'borderRadius' => 0,
            'borderStyle' => 'solid',
            'paddingTop' => 20,
            'paddingBottom' => 20,
            'paddingLeft' => 20,
            'paddingRight' => 20,
            'marginTop' => 0,
            'marginBottom' => 0,
            'marginLeft' => 0,
            'marginRight' => 0,
            'hasShadow' => false,
            'shadowColor' => 'rgba(0,0,0,0.1)',
            'shadowSpread' => 5,
            'backgroundGradient' => false,
            'gradientColor1' => '#ffffff',
            'gradientColor2' => '#f0f0f0',
            'hasIcon' => false,
            'iconType' => 'info',
            'boxWidth' => 0,
            'boxWidthUnit' => '%',
            'boxAlignment' => 'none',
        ], $attrs);

        $bg_style = !empty($attrs['backgroundGradient'])
            ? 'linear-gradient(135deg, ' . esc_attr($attrs['gradientColor1']) . ' 0%, ' . esc_attr($attrs['gradientColor2']) . ' 100%)'
            : esc_attr(!empty($attrs['backgroundColor']) ? $attrs['backgroundColor'] : '#f5f5f5');

        $has_icon = !empty($attrs['hasIcon']);
        $icon_class = $has_icon ? ' ksb-box-icon-' . esc_attr($attrs['iconType']) : '';

        $style_parts = [];
        $style_parts[] = 'background:' . $bg_style;
        if (!empty($attrs['borderColor'])) {
            $style_parts[] = 'border-color:' . esc_attr($attrs['borderColor']);
        }
        $style_parts[] = 'border-width:' . intval($attrs['borderWidth']) . 'px';
        $style_parts[] = 'border-style:' . (intval($attrs['borderWidth']) === 0 ? 'none' : esc_attr($attrs['borderStyle']));
        if (intval($attrs['borderRadius']) > 0) {
            $style_parts[] = 'border-radius:' . intval($attrs['borderRadius']) . 'px';
        }
        $style_parts[] = 'padding-top:' . intval($attrs['paddingTop']) . 'px';
        $style_parts[] = 'padding-bottom:' . intval($attrs['paddingBottom']) . 'px';
        $style_parts[] = 'padding-left:' . intval($attrs['paddingLeft']) . 'px';
        $style_parts[] = 'padding-right:' . intval($attrs['paddingRight']) . 'px';

        if (intval($attrs['marginTop']) > 0) {
            $style_parts[] = 'margin-top:' . intval($attrs['marginTop']) . 'px';
        }
        if (intval($attrs['marginBottom']) > 0) {
            $style_parts[] = 'margin-bottom:' . intval($attrs['marginBottom']) . 'px';
        }

        // 配置
        $box_alignment = $attrs['boxAlignment'] ?? 'none';
        if ($box_alignment === 'center') {
            $style_parts[] = 'margin-left:auto';
            $style_parts[] = 'margin-right:auto';
        } elseif ($box_alignment === 'left') {
            if (intval($attrs['marginLeft']) > 0) {
                $style_parts[] = 'margin-left:' . intval($attrs['marginLeft']) . 'px';
            }
            $style_parts[] = 'margin-right:auto';
        } elseif ($box_alignment === 'right') {
            $style_parts[] = 'margin-left:auto';
            if (intval($attrs['marginRight']) > 0) {
                $style_parts[] = 'margin-right:' . intval($attrs['marginRight']) . 'px';
            }
        }

        if (intval($attrs['boxWidth']) > 0) {
            $style_parts[] = 'max-width:' . intval($attrs['boxWidth']) . esc_attr($attrs['boxWidthUnit']);
        }

        if (!empty($attrs['hasShadow'])) {
            $style_parts[] = 'box-shadow:0 0 ' . intval($attrs['shadowSpread']) . 'px ' . esc_attr($attrs['shadowColor']);
        }

        $style = implode(';', $style_parts);

        $html = "\n" . '<div class="wp-block-kashiwazaki-seo-blocks-box ksb-box' . $icon_class . '" style="' . $style . '">';
        $html .= $inner_content;
        $html .= '</div>' . "\n";

        return $html;
    }
}

new KashiwazakiSeoBlocks();