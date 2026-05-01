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
            add_action( 'admin_post_md_importer_delete', array( $this, 'handle_delete' ) );
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

            if ( isset( $_GET['md_importer_deleted'] ) ) {
                $message .= '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Post deleted successfully.', 'md-importer' ) . '</p></div>';
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
            echo '<p>' . esc_html__( 'Upload one or more Markdown files (.md or .markdown), review metadata, and confirm before posting.', 'md-importer' ) . '</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" id="md-importer-upload-form">';
            echo '<input type="hidden" name="action" value="md_importer_upload">';
            wp_nonce_field( 'md_importer_upload_action', 'md_importer_upload_nonce' );
            echo '<div id="md-importer-dropzone" class="md-importer-dropzone" tabindex="0">' . esc_html__( 'Drop Markdown files here or click to select them.', 'md-importer' ) . '</div>';
            echo '<div id="md-importer-file-list" class="md-importer-file-list" aria-live="polite"></div>';
            echo '<div id="md-importer-pending-uploads" class="md-importer-pending-uploads"></div>';
            echo '<div id="md-importer-hidden-fields"></div>';
            echo '<div class="md-importer-actions">';
            echo '<button type="submit" id="md-importer-confirm" class="button button-primary" disabled>' . esc_html__( 'Confirm', 'md-importer' ) . '</button>';
            echo ' ';
            echo '<button type="button" id="md-importer-cancel" class="button" disabled>' . esc_html__( 'Cancel', 'md-importer' ) . '</button>';
            echo '</div>';
            echo '<input name="md_import_file[]" type="file" id="md_import_file" accept=".md,.markdown,text/markdown" multiple style="display:none;" />';
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
            $entries = $this->get_article_overview_entries();

            if ( empty( $entries ) ) {
                echo '<p>' . esc_html__( 'No confirmed uploads available yet. After confirming files in the Upload tab, they will appear here.', 'md-importer' ) . '</p>';
                return;
            }

            echo '<div class="md-importer-table-wrap">';
            echo '<div class="md-importer-table-toolbar">';
            echo '<label for="md-importer-table-search">' . esc_html__( 'Search', 'md-importer' ) . ':</label> ';
            echo '<input id="md-importer-table-search" class="md-importer-table-search" type="search" placeholder="' . esc_attr__( 'Search confirmed articles...', 'md-importer' ) . '" />';
            echo '</div>';
            echo '<table class="wp-list-table widefat fixed striped md-importer-table">';
            echo '<thead><tr><th>' . esc_html__( 'ID', 'md-importer' ) . '</th><th>' . esc_html__( 'Keyword', 'md-importer' ) . '</th><th>' . esc_html__( 'URL Slug', 'md-importer' ) . '</th><th>' . esc_html__( 'RELEASE_DATE', 'md-importer' ) . '</th><th>' . esc_html__( 'Action', 'md-importer' ) . '</th></tr></thead>';
            echo '<tbody>';
            foreach ( $entries as $entry ) {
                echo '<tr>';
                echo '<td>' . esc_html( $entry['id'] ) . '</td>';
                echo '<td>' . esc_html( $entry['keyword'] ) . '</td>';
                echo '<td>' . esc_html( $entry['slug'] ) . '</td>';
                echo '<td>' . esc_html( $entry['release_date'] ) . '</td>';
                echo '<td>' . wp_kses_post( $entry['action'] ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }

        private function get_article_overview_entries() {
            $user_id = get_current_user_id();
            if ( ! $user_id ) {
                return array();
            }

            $entries = get_transient( 'md_importer_article_overview_' . $user_id );
            if ( ! is_array( $entries ) ) {
                return array();
            }

            return $entries;
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

            $release_dates = isset( $_POST['release_date'] ) && is_array( $_POST['release_date'] ) ? $_POST['release_date'] : array();
            $url_slugs      = isset( $_POST['url_slug'] ) && is_array( $_POST['url_slug'] ) ? $_POST['url_slug'] : array();
            $keywords       = isset( $_POST['keyword'] ) && is_array( $_POST['keyword'] ) ? $_POST['keyword'] : array();

            $success_count = 0;
            $error_count   = 0;
            $error_messages = array();
            $recent_uploads = array();
            $article_overview = get_transient( 'md_importer_article_overview_' . get_current_user_id() );
            if ( ! is_array( $article_overview ) ) {
                $article_overview = array();
            }

            foreach ( $files as $index => $file ) {
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

                $lines = preg_split( '/\r?\n/', trim( $content ) );

                // Extract RELEASE_DATE from line 1
                $release_date = '';
                if ( isset( $lines[0] ) && preg_match( '/\[\[([^\]]+)\]\]/', $lines[0], $matches ) ) {
                    $release_date = $matches[1];
                }

                // Extract URL-slug from line 5
                $url_slug = '';
                if ( isset( $lines[4] ) ) {
                    $url_slug = trim( $lines[4] );
                }

                // Extract Keyword from line 7
                $keyword = '';
                if ( isset( $lines[6] ) && strpos( $lines[6], '# ' ) === 0 ) {
                    $keyword = trim( substr( $lines[6], 2 ) );
                }

                $release_date = isset( $release_dates[ $index ] ) ? sanitize_text_field( wp_unslash( $release_dates[ $index ] ) ) : $release_date;
                $url_slug      = isset( $url_slugs[ $index ] ) ? sanitize_text_field( wp_unslash( $url_slugs[ $index ] ) ) : $url_slug;
                $keyword       = isset( $keywords[ $index ] ) ? sanitize_text_field( wp_unslash( $keywords[ $index ] ) ) : $keyword;

                $post_title   = $keyword ?: $this->get_title_from_markdown( $content );
                $post_content = $this->convert_markdown_to_html( $content );

                $post_id = wp_insert_post( array(
                    'post_title'   => $post_title,
                    'post_content' => $post_content,
                    'post_status'  => 'draft',
                    'post_type'    => 'post',
                    'post_name'    => $url_slug ? sanitize_title( $url_slug ) : sanitize_title( $post_title ),
                ) );

                if ( is_wp_error( $post_id ) || ! $post_id ) {
                    $error_count++;
                    $error_messages[] = sprintf( __( 'Could not create a post for %s.', 'md-importer' ), esc_html( $file['name'] ) );
                    continue;
                }

                update_post_meta( $post_id, '_md_importer_release_date', $release_date );
                update_post_meta( $post_id, '_md_importer_url_slug', $url_slug );
                update_post_meta( $post_id, '_md_importer_keyword', $keyword );
                update_post_meta( $post_id, '_md_importer_imported', time() );

                $success_count++;
                $delete_url = add_query_arg( array(
                    'action'                    => 'md_importer_delete',
                    'post_id'                   => $post_id,
                    'md_importer_delete_nonce' => wp_create_nonce( 'md_importer_delete_' . $post_id ),
                ), admin_url( 'admin-post.php' ) );

                $recent_uploads[] = array(
                    'id'           => $post_id,
                    'keyword'      => $keyword ?: $file['name'],
                    'slug'         => $url_slug ?: get_post_field( 'post_name', $post_id ),
                    'release_date' => $release_date,
                    'action'       => sprintf(
                        '<a href="%s" target="_blank" class="button button-small">%s</a> <a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\');">%s</a>',
                        esc_url( get_edit_post_link( $post_id ) ),
                        esc_html__( 'Edit', 'md-importer' ),
                        esc_url( $delete_url ),
                        esc_js( __( 'Are you sure you want to delete this post?', 'md-importer' ) ),
                        esc_html__( 'Delete', 'md-importer' )
                    ),
                );

                $article_overview[] = array(
                    'id'           => $post_id,
                    'keyword'      => $keyword ?: $file['name'],
                    'slug'         => $url_slug ?: get_post_field( 'post_name', $post_id ),
                    'release_date' => $release_date,
                    'action'       => $recent_uploads[ $success_count - 1 ]['action'],
                );
            }

            if ( $success_count > 0 ) {
                set_transient( 'md_importer_recent_upload_' . get_current_user_id(), $recent_uploads, HOUR_IN_SECONDS );
                set_transient( 'md_importer_article_overview_' . get_current_user_id(), $article_overview, HOUR_IN_SECONDS );
            }

            $redirect_url = add_query_arg( array(
                'md_importer_status' => 'success',
                'success_count'      => $success_count,
                'error_count'        => $error_count,
                'tab'                => 'article-overview',
            ), admin_url( 'admin.php?page=' . self::SLUG ) );

            if ( $error_count > 0 ) {
                $redirect_url = add_query_arg( 'message', rawurlencode( implode( ' ', array_slice( $error_messages, 0, 3 ) ) ), $redirect_url );
            }

            wp_safe_redirect( $redirect_url );
            exit;
        }

        public function handle_delete() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have sufficient permissions to delete posts.', 'md-importer' ) );
            }

            if ( ! isset( $_GET['post_id'] ) || ! isset( $_GET['md_importer_delete_nonce'] ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
                exit;
            }

            $post_id = absint( $_GET['post_id'] );
            $nonce   = sanitize_text_field( wp_unslash( $_GET['md_importer_delete_nonce'] ) );

            if ( ! wp_verify_nonce( $nonce, 'md_importer_delete_' . $post_id ) ) {
                wp_die( esc_html__( 'Security check failed.', 'md-importer' ) );
            }

            $post = get_post( $post_id );
            if ( ! $post || $post->post_type !== 'post' ) {
                wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
                exit;
            }

            wp_delete_post( $post_id, true );

            $user_id = get_current_user_id();
            $recent_uploads = get_transient( 'md_importer_recent_upload_' . $user_id );

            if ( is_array( $recent_uploads ) ) {
                $recent_uploads = array_filter( $recent_uploads, function( $upload ) use ( $post_id ) {
                    return (int) $upload['id'] !== $post_id;
                } );

                if ( ! empty( $recent_uploads ) ) {
                    set_transient( 'md_importer_recent_upload_' . $user_id, $recent_uploads, HOUR_IN_SECONDS );
                } else {
                    delete_transient( 'md_importer_recent_upload_' . $user_id );
                }
            }

            $article_overview = get_transient( 'md_importer_article_overview_' . $user_id );
            if ( is_array( $article_overview ) ) {
                $article_overview = array_filter( $article_overview, function( $entry ) use ( $post_id ) {
                    return (int) $entry['id'] !== $post_id;
                } );

                if ( ! empty( $article_overview ) ) {
                    set_transient( 'md_importer_article_overview_' . $user_id, $article_overview, HOUR_IN_SECONDS );
                } else {
                    delete_transient( 'md_importer_article_overview_' . $user_id );
                }
            }

            wp_safe_redirect( add_query_arg( 'md_importer_deleted', '1', admin_url( 'admin.php?page=' . self::SLUG ) ) );
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
