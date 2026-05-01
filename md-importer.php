<?php
/**
 * Plugin Name: Markdown Importer
 * Plugin URI: https://example.com/plugins/markdown-importer
 * Description: Import Markdown files into WordPress posts from an admin import screen.
 * Version: 0.1.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: md-importer
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'MD_Importer' ) ) {

    class MD_Importer {

        const VERSION = '0.1.0';
        const SLUG    = 'md-importer';

        public function __construct() {
            add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
            add_action( 'admin_post_md_importer_upload', array( $this, 'handle_upload' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        }

        public function enqueue_admin_assets( $hook_suffix ) {
            if ( 'toplevel_page_' . self::SLUG !== $hook_suffix ) {
                return;
            }

            wp_enqueue_style(
                self::SLUG . '-admin',
                plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
                array(),
                self::VERSION
            );

            wp_enqueue_script(
                self::SLUG . '-admin',
                plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
                array(),
                self::VERSION,
                true
            );
        }

        public function register_admin_page() {
            add_menu_page(
                __( 'Markdown Importer', 'md-importer' ),
                __( 'Markdown Importer', 'md-importer' ),
                'manage_options',
                self::SLUG,
                array( $this, 'render_admin_page' ),
                'dashicons-star-filled',
                80
            );
        }

        public function render_admin_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $message = '';
            if ( isset( $_GET['md_importer_status'] ) ) {
                $status = sanitize_text_field( wp_unslash( $_GET['md_importer_status'] ) );
                if ( 'success' === $status ) {
                    $success_count = isset( $_GET['success_count'] ) ? absint( $_GET['success_count'] ) : 0;
                    $error_count   = isset( $_GET['error_count'] ) ? absint( $_GET['error_count'] ) : 0;

                    if ( $success_count > 0 ) {
                        $message = '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d file imported successfully.', '%d files imported successfully.', $success_count, 'md-importer' ), $success_count ) ) . '</p></div>';
                    }

                    if ( $error_count > 0 && isset( $_GET['message'] ) ) {
                        $message .= '<div class="notice notice-warning is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['message'] ) ) ) . '</p></div>';
                    }
                } elseif ( 'error' === $status && isset( $_GET['message'] ) ) {
                    $message = '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['message'] ) ) ) . '</p></div>';
                }
            }

            $current_tab = $this->get_current_tab();

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Markdown Importer', 'md-importer' ) . '</h1>';
            echo $message;
            $this->render_tabs( $current_tab );
            echo '<div class="md-importer-tab-content">';
            switch ( $current_tab ) {
                case 'article-overview':
                    $this->render_article_overview_tab();
                    break;
                case 'cta-buttons':
                    $this->render_cta_buttons_tab();
                    break;
                case 'upgrade-articles':
                    $this->render_upgrade_articles_tab();
                    break;
                case 'upload':
                default:
                    $this->render_upload_tab();
                    break;
            }
            echo '</div>';
            echo '</div>';
        }

        private function get_current_tab() {
            if ( isset( $_GET['tab'] ) ) {
                $tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
                $allowed = array( 'upload', 'article-overview', 'cta-buttons', 'upgrade-articles' );
                if ( in_array( $tab, $allowed, true ) ) {
                    return $tab;
                }
            }
            return 'upload';
        }

        private function render_tabs( $current_tab ) {
            $base_url = admin_url( 'admin.php?page=' . self::SLUG );
            $tabs = array(
                'upload'           => __( 'Upload', 'md-importer' ),
                'article-overview' => __( 'Article Overview', 'md-importer' ),
                'cta-buttons'      => __( 'CTA Buttons', 'md-importer' ),
                'upgrade-articles' => __( 'Upgrade Articles', 'md-importer' ),
            );

            echo '<h2 class="nav-tab-wrapper">';
            foreach ( $tabs as $tab_key => $tab_label ) {
                $class = 'nav-tab';
                if ( $tab_key === $current_tab ) {
                    $class .= ' nav-tab-active';
                }
                echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( add_query_arg( 'tab', $tab_key, $base_url ) ) . '">' . esc_html( $tab_label ) . '</a>';
            }
            echo '</h2>';
        }

        private function render_upload_tab() {
            $recent_uploads = $this->get_recent_uploads();

            if ( ! empty( $recent_uploads ) ) {
                echo '<div class="md-importer-table-wrap">';
                echo '<div class="md-importer-table-toolbar">';
                echo '<label for="md-importer-table-search">' . esc_html__( 'Search', 'md-importer' ) . ':</label> ';
                echo '<input id="md-importer-table-search" class="md-importer-table-search" type="search" placeholder="' . esc_attr__( 'Search uploaded files...', 'md-importer' ) . '" />';
                echo '</div>';
                echo '<table class="wp-list-table widefat fixed striped md-importer-table">';
                echo '<thead><tr><th>' . esc_html__( 'ID', 'md-importer' ) . '</th><th>' . esc_html__( 'Keyword', 'md-importer' ) . '</th><th>' . esc_html__( 'URL Slug', 'md-importer' ) . '</th><th>' . esc_html__( 'Action', 'md-importer' ) . '</th></tr></thead>';
                echo '<tbody>';
                foreach ( $recent_uploads as $upload ) {
                    echo '<tr>';
                    echo '<td>' . esc_html( $upload['id'] ) . '</td>';
                    echo '<td>' . esc_html( $upload['keyword'] ) . '</td>';
                    echo '<td>' . esc_html( $upload['slug'] ) . '</td>';
                    echo '<td>' . wp_kses_post( $upload['action'] ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                echo '<p><a class="button" href="' . esc_url( add_query_arg( array( 'tab' => 'upload', 'clear_recent_uploads' => '1' ), admin_url( 'admin.php?page=' . self::SLUG ) ) ) . '">' . esc_html__( 'Upload more files', 'md-importer' ) . '</a></p>';
                echo '</div>';

                return;
            }

            echo '<p>' . esc_html__( 'Upload one or more Markdown files (.md or .markdown) to create draft posts.', 'md-importer' ) . '</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
            echo '<input type="hidden" name="action" value="md_importer_upload">';
            wp_nonce_field( 'md_importer_upload_action', 'md_importer_upload_nonce' );
            echo '<div id="md-importer-dropzone" class="md-importer-dropzone" tabindex="0">' . esc_html__( 'Drop Markdown files here or click to select them.', 'md-importer' ) . '</div>';
            echo '<div id="md-importer-file-list" class="md-importer-file-list" aria-live="polite"></div>';
            echo '<input name="md_import_file[]" type="file" id="md_import_file" accept=".md,.markdown,text/markdown" multiple required style="display:none;" />';
            submit_button( __( 'Import Markdown Files', 'md-importer' ) );
            echo '</form>';
        }

        private function get_recent_uploads() {
            $user_id = get_current_user_id();
            if ( ! $user_id ) {
                return array();
            }

            if ( isset( $_GET['clear_recent_uploads'] ) ) {
                delete_transient( 'md_importer_recent_upload_' . $user_id );
                return array();
            }

            $recent_uploads = get_transient( 'md_importer_recent_upload_' . $user_id );
            if ( ! is_array( $recent_uploads ) ) {
                return array();
            }

            return $recent_uploads;
        }

        private function render_article_overview_tab() {
            echo '<p>' . esc_html__( 'Article Overview will show imported post summaries and status.', 'md-importer' ) . '</p>';
            echo '<p>' . esc_html__( 'This area can later display a list of imported articles, their draft status, and metadata.', 'md-importer' ) . '</p>';
        }

        private function render_cta_buttons_tab() {
            echo '<p>' . esc_html__( 'CTA Buttons settings let you configure import call-to-action links and button text.', 'md-importer' ) . '</p>';
            echo '<p>' . esc_html__( 'Add custom buttons or messaging for your content import workflow.', 'md-importer' ) . '</p>';
        }

        private function render_upgrade_articles_tab() {
            echo '<p>' . esc_html__( 'Upgrade Articles provides tools for boosting content after import.', 'md-importer' ) . '</p>';
            echo '<p>' . esc_html__( 'Future enhancements can include article optimization, category suggestions, and upgrade workflows.', 'md-importer' ) . '</p>';
        }

        public function handle_upload() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'md-importer' ) );
            }

            check_admin_referer( 'md_importer_upload_action', 'md_importer_upload_nonce' );

            if ( empty( $_FILES['md_import_file'] ) ) {
                $this->redirect_with_error( __( 'No file uploaded.', 'md-importer' ) );
            }

            $files = $this->normalize_files_array( $_FILES['md_import_file'] );
            if ( empty( $files ) ) {
                $this->redirect_with_error( __( 'No valid files were uploaded.', 'md-importer' ) );
            }

            $success_count = 0;
            $error_count   = 0;
            $error_messages = array();
            $recent_uploads = array();

            foreach ( $files as $file ) {
                if ( ! isset( $file['tmp_name'] ) || empty( $file['tmp_name'] ) ) {
                    $error_count++;
                    $error_messages[] = sprintf( __( 'File %s could not be uploaded.', 'md-importer' ), esc_html( $file['name'] ) );
                    continue;
                }

                if ( $file['error'] !== UPLOAD_ERR_OK ) {
                    $error_count++;
                    $error_messages[] = sprintf( __( 'Error uploading %s.', 'md-importer' ), esc_html( $file['name'] ) );
                    continue;
                }

                $mime_type = wp_check_filetype( $file['name'] );
                $extension = strtolower( $mime_type['ext'] );
                if ( empty( $extension ) ) {
                    $extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
                }

                if ( ! in_array( $extension, array( 'md', 'markdown', 'txt' ), true ) ) {
                    $error_count++;
                    $error_messages[] = sprintf( __( '%s is not a Markdown file.', 'md-importer' ), esc_html( $file['name'] ) );
                    continue;
                }

                $content = file_get_contents( $file['tmp_name'] );
                if ( false === $content ) {
                    $error_count++;
                    $error_messages[] = sprintf( __( 'Unable to read %s.', 'md-importer' ), esc_html( $file['name'] ) );
                    continue;
                }

                $post_title   = $this->get_title_from_markdown( $content );
                $post_content = $this->convert_markdown_to_html( $content );

                $post_id = wp_insert_post( array(
                    'post_title'   => $post_title,
                    'post_content' => $post_content,
                    'post_status'  => 'draft',
                    'post_type'    => 'post',
                ) );

                if ( is_wp_error( $post_id ) || ! $post_id ) {
                    $error_count++;
                    $error_messages[] = sprintf( __( 'Could not create a post for %s.', 'md-importer' ), esc_html( $file['name'] ) );
                    continue;
                }

                $success_count++;
                $recent_uploads[] = array(
                    'id'      => $post_id,
                    'keyword' => $file['name'],
                    'slug'    => get_post_field( 'post_name', $post_id ),
                    'action'  => sprintf(
                        '<a href="%s" target="_blank">%s</a>',
                        esc_url( get_edit_post_link( $post_id ) ),
                        esc_html__( 'Edit', 'md-importer' )
                    ),
                );
            }

            if ( $success_count > 0 ) {
                set_transient( 'md_importer_recent_upload_' . get_current_user_id(), $recent_uploads, HOUR_IN_SECONDS );
            }

            $redirect_url = add_query_arg( array(
                'md_importer_status' => 'success',
                'success_count'      => $success_count,
                'error_count'        => $error_count,
            ), admin_url( 'admin.php?page=' . self::SLUG ) );

            if ( $error_count > 0 ) {
                $redirect_url = add_query_arg( 'message', rawurlencode( implode( ' ', array_slice( $error_messages, 0, 3 ) ) ), $redirect_url );
            }

            wp_safe_redirect( $redirect_url );
            exit;
        }

        private function normalize_files_array( $files ) {
            $normalized = array();

            if ( is_array( $files['name'] ) ) {
                foreach ( $files['name'] as $index => $name ) {
                    $normalized[] = array(
                        'name'     => $name,
                        'type'     => $files['type'][ $index ],
                        'tmp_name' => $files['tmp_name'][ $index ],
                        'error'    => $files['error'][ $index ],
                        'size'     => $files['size'][ $index ],
                    );
                }
            } else {
                $normalized[] = $files;
            }

            return $normalized;
        }

        private function redirect_with_error( $message ) {
            wp_safe_redirect( add_query_arg( array(
                'md_importer_status' => 'error',
                'message'            => rawurlencode( $message ),
            ), admin_url( 'admin.php?page=' . self::SLUG ) ) );
            exit;
        }

        private function get_title_from_markdown( $content ) {
            $lines = preg_split( '/\r?\n/', trim( $content ) );
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( strpos( $line, '# ' ) === 0 ) {
                    return trim( substr( $line, 2 ) );
                }
            }
            return wp_trim_words( strip_tags( $content ), 8, '...' );
        }

        private function convert_markdown_to_html( $content ) {
            $content = esc_html( $content );
            $content = preg_replace( '/^######\s+(.*)$/m', '<h6>$1</h6>', $content );
            $content = preg_replace( '/^#####\s+(.*)$/m', '<h5>$1</h5>', $content );
            $content = preg_replace( '/^####\s+(.*)$/m', '<h4>$1</h4>', $content );
            $content = preg_replace( '/^###\s+(.*)$/m', '<h3>$1</h3>', $content );
            $content = preg_replace( '/^##\s+(.*)$/m', '<h2>$1</h2>', $content );
            $content = preg_replace( '/^#\s+(.*)$/m', '<h1>$1</h1>', $content );
            $content = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content );
            $content = preg_replace( '/\*(.*?)\*/', '<em>$1</em>', $content );
            $content = preg_replace( '/^>\s+(.*)$/m', '<blockquote>$1</blockquote>', $content );
            $content = preg_replace( '/(^|\r?\n)([^<\n][^\n]+)(\r?\n|$)/', '$1<p>$2</p>$3', $content );
            $content = str_replace( array( '<p></p>', '<p> </p>' ), '', $content );
            return wp_kses_post( $content );
        }
    }
}

new MD_Importer();
