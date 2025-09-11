<?php
/**
 * Plugin Name: Kashiwazaki SEO Blocks
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: シンプルで使いやすいブロックエディタ対応のカスタムブロックプラグイン。見出しバー、ボタン、汎用ボックスを簡単に作成できます。
 * Version: 1.0.0
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
define('KSB_PLUGIN_VERSION', '1.0.0');

class KashiwazakiSeoBlocks {
    
    public function __construct() {
        add_action('init', [$this, 'register_blocks']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_block_assets']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_filter('block_categories_all', [$this, 'add_block_category'], 10, 2);
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
            'nonce' => wp_create_nonce('ksb_nonce')
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
        add_menu_page(
            'Kashiwazaki SEO Blocks',
            'Kashiwazaki SEO Blocks',
            'manage_options',
            'kashiwazaki-seo-blocks',
            [$this, 'admin_page'],
            'dashicons-layout',
            81
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Kashiwazaki SEO Blocks</h1>
            <div class="ksb-admin-content">
                <h2>プラグインについて</h2>
                <p>Kashiwazaki SEO Blocksは、WordPressのブロックエディタ（Gutenberg）用のカスタムブロックコレクションです。</p>
                <p>SEOに配慮したコンテンツ作成を支援し、美しいデザインと高いカスタマイズ性を提供します。</p>
                
                <h2>利用可能なブロック</h2>
                <ul>
                    <li><strong>見出しバー</strong> - H1〜H6まで選択可能な装飾付き見出し。背景色、枠線、影などをカスタマイズ可能</li>
                    <li><strong>ボタン</strong> - リンク付きのカスタマイズ可能なボタン。新規タブ開きや全幅表示にも対応</li>
                    <li><strong>汎用ボックス</strong> - 情報、警告、成功、ヒントなどのアイコン付きボックス。グラデーション背景も設定可能</li>
                    <li><strong>ボーダー</strong> - ページ区切り用のカスタマイズ可能な区切り線</li>
                    <li><strong>テーブル</strong> - レスポンシブ対応のテーブル。ヘッダー、ストライプ、ホバー効果、横スクロール機能付き</li>
                    <li><strong>吹き出し</strong> - アバター画像付きの会話形式コンテンツ。通常の吹き出しと考え事スタイルに対応</li>
                </ul>
                
                <h2>主な機能</h2>
                <ul>
                    <li>全ブロックで背景色、文字色、枠線をカスタマイズ可能</li>
                    <li>余白（パディング・マージン）の細かい調整</li>
                    <li>影の効果や角丸の設定</li>
                    <li>レスポンシブデザイン対応</li>
                    <li>専用カテゴリー「Kashiwazaki SEO Blocks」で整理</li>
                </ul>
                
                <h2>使い方</h2>
                <ol>
                    <li>投稿や固定ページの編集画面を開く</li>
                    <li>ブロック追加ボタン（＋）をクリック</li>
                    <li>「Kashiwazaki SEO Blocks」カテゴリーから使用したいブロックを選択</li>
                    <li>右側のサイドバーで各種設定をカスタマイズ</li>
                </ol>
                
                <h2>バージョン情報</h2>
                <p>現在のバージョン: 1.0.0</p>
                <p>対応WordPress: 6.0以上</p>
                <p>対応PHP: 7.4以上</p>
            </div>
        </div>
        <?php
    }
}

new KashiwazakiSeoBlocks();