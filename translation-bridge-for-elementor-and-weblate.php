<?php
/**
 * Plugin Name:       Translation Bridge for Elementor and Weblate
 * Description:       Export translatable Elementor content to Gettext POT for Weblate and import translated PO files into Polylang translations.
 * Version:           0.1.6
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  elementor
 * Author:            Resistine
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       translation-bridge-for-elementor-and-weblate
 *
 * @package TranslationBridgeForElementorWeblate
 * @copyright 2026 Resistine
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EWB_Elementor_Weblate_Bridge {
    const VERSION   = '0.1.6';
    const MENU_SLUG = 'translation-bridge-for-elementor-and-weblate';

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_post_ewb_export_pot', array( $this, 'handle_export' ) );
        add_action( 'admin_post_ewb_import_po', array( $this, 'handle_import' ) );
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
        add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );
        add_action( 'admin_head-plugins.php', 'add_thickbox' );
    }

    public function admin_menu() {
        add_management_page(
            __( 'Translation Bridge for Elementor and Weblate', 'translation-bridge-for-elementor-and-weblate' ),
            __( 'Elementor → Weblate', 'translation-bridge-for-elementor-and-weblate' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render_admin_page' )
        );
    }

    private function elementor_available() {
        return did_action( 'elementor/loaded' ) && class_exists( '\\Elementor\\Plugin' );
    }

    private function polylang_available() {
        return function_exists( 'pll_get_post' )
            && function_exists( 'pll_get_post_translations' )
            && function_exists( 'pll_save_post_translations' )
            && function_exists( 'pll_set_post_language' );
    }

    public function plugin_row_meta( $links, $file ) {
        if ( plugin_basename( __FILE__ ) !== $file ) {
            return $links;
        }

        $url = add_query_arg(
            array(
                'tab'       => 'plugin-information',
                'plugin'    => self::MENU_SLUG,
                'TB_iframe' => 'true',
                'width'     => 772,
                'height'    => 840,
            ),
            self_admin_url( 'plugin-install.php' )
        );

        $links[] = sprintf(
            '<a href="%1$s" class="thickbox open-plugin-details-modal" aria-label="%2$s">%3$s</a>',
            esc_url( $url ),
            esc_attr__( 'View details about Translation Bridge for Elementor and Weblate', 'translation-bridge-for-elementor-and-weblate' ),
            esc_html__( 'View details', 'translation-bridge-for-elementor-and-weblate' )
        );
        return $links;
    }

    public function plugin_information( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || self::MENU_SLUG !== $args->slug ) {
            return $result;
        }
        $sections = $this->readme_sections();
        return (object) array(
            'name'              => 'Translation Bridge for Elementor and Weblate',
            'slug'              => self::MENU_SLUG,
            'version'           => self::VERSION,
            'author'            => 'Resistine',
            'requires'          => '6.4',
            'requires_php'      => '7.4',
            'short_description' => 'Export Elementor page text to Weblate as gettext POT and import translated PO files into Polylang Elementor pages.',
            'sections'          => array(
                'description'  => isset( $sections['Description'] ) ? $sections['Description'] : '',
                'installation' => isset( $sections['Installation'] ) ? $sections['Installation'] : '',
                'faq'          => isset( $sections['Frequently Asked Questions'] ) ? $sections['Frequently Asked Questions'] : '',
                'changelog'    => isset( $sections['Changelog'] ) ? $sections['Changelog'] : '',
            ),
        );
    }

    private function readme_sections() {
        $file = plugin_dir_path( __FILE__ ) . 'readme.txt';
        if ( ! is_readable( $file ) ) {
            return array();
        }
        $contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $contents ) {
            return array();
        }
        $parts = preg_split( '/^==(?!=)\\s*(.+?)\\s*==\\s*$/m', $contents, -1, PREG_SPLIT_DELIM_CAPTURE );
        if ( ! is_array( $parts ) || count( $parts ) < 3 ) {
            return array();
        }
        $sections = array();
        for ( $i = 1; $i + 1 < count( $parts ); $i += 2 ) {
            $sections[ trim( $parts[ $i ] ) ] = $this->readme_markup_to_html( trim( $parts[ $i + 1 ] ) );
        }
        return $sections;
    }

    private function readme_markup_to_html( $text ) {
        $lines = preg_split( '/\\R/', (string) $text );
        $html  = '';
        $list  = '';
        $close_list = static function() use ( &$html, &$list ) {
            if ( $list ) {
                $html .= '</' . $list . '>';
                $list = '';
            }
        };
        foreach ( (array) $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line ) {
                $close_list();
                continue;
            }
            if ( preg_match( '/^=\\s*(.+?)\\s*=$/', $line, $m ) ) {
                $close_list();
                $html .= '<h4>' . esc_html( $m[1] ) . '</h4>';
                continue;
            }
            if ( preg_match( '/^\\*\\s+(.+)$/', $line, $m ) ) {
                if ( 'ul' !== $list ) {
                    $close_list();
                    $html .= '<ul>';
                    $list = 'ul';
                }
                $html .= '<li>' . $this->readme_inline_markup( $m[1] ) . '</li>';
                continue;
            }
            if ( preg_match( '/^\\d+\\.\\s+(.+)$/', $line, $m ) ) {
                if ( 'ol' !== $list ) {
                    $close_list();
                    $html .= '<ol>';
                    $list = 'ol';
                }
                $html .= '<li>' . $this->readme_inline_markup( $m[1] ) . '</li>';
                continue;
            }
            $close_list();
            $html .= '<p>' . $this->readme_inline_markup( $line ) . '</p>';
        }
        $close_list();
        return $html;
    }

    private function readme_inline_markup( $text ) {
        $text = esc_html( (string) $text );
        $text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
        $text = preg_replace( '/\\*\\*([^*]+)\\*\\*/', '<strong>$1</strong>', $text );
        return wp_kses_post( $text );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $elementor        = $this->elementor_available();
        $polylang         = $this->polylang_available();
        $languages        = $polylang ? $this->get_polylang_languages() : array();
        $default_language = $polylang && function_exists( 'pll_default_language' ) ? (string) pll_default_language( 'slug' ) : '';
        $documents        = $elementor ? $this->get_elementor_documents() : array();
        $notice           = isset( $_GET['ewb_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['ewb_notice'] ) ) : '';
        $type             = isset( $_GET['ewb_type'] ) ? sanitize_key( $_GET['ewb_type'] ) : 'success';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Translation Bridge for Elementor and Weblate', 'translation-bridge-for-elementor-and-weblate' ); ?></h1>
            <p><?php esc_html_e( 'Export Elementor content as gettext POT for Weblate and import translated PO files into corresponding Polylang pages.', 'translation-bridge-for-elementor-and-weblate' ); ?></p>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( 'error' === $type ? 'error' : ( 'warning' === $type ? 'warning' : 'success' ) ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <table class="widefat striped" style="max-width:760px;margin:18px 0;"><tbody>
                <tr><td><strong>Elementor</strong></td><td><?php echo $elementor ? '✓ active' : '✗ required'; ?></td></tr>
                <tr><td><strong>Polylang</strong></td><td><?php echo $polylang ? '✓ active' : '— optional for export; required for automatic import mapping'; ?></td></tr>
                <tr><td><strong><?php esc_html_e( 'Elementor documents found', 'translation-bridge-for-elementor-and-weblate' ); ?></strong></td><td><?php echo esc_html( number_format_i18n( count( $documents ) ) ); ?></td></tr>
            </tbody></table>

            <hr>
            <h2><?php esc_html_e( '1. Export to Weblate', 'translation-bridge-for-elementor-and-weblate' ); ?></h2>
            <?php if ( $elementor ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="ewb_export_pot">
                    <?php wp_nonce_field( 'ewb_export_pot' ); ?>

                    <?php if ( $polylang && $languages ) : ?>
                        <p><label for="ewb_export_lang"><strong><?php esc_html_e( 'Export language', 'translation-bridge-for-elementor-and-weblate' ); ?></strong></label><br>
                        <select id="ewb_export_lang" name="export_lang">
                            <option value=""><?php esc_html_e( 'All languages', 'translation-bridge-for-elementor-and-weblate' ); ?></option>
                            <?php foreach ( $languages as $lang ) : ?>
                                <option value="<?php echo esc_attr( $lang['slug'] ); ?>" <?php selected( $default_language, $lang['slug'] ); ?>><?php echo esc_html( $lang['name'] . ' (' . $lang['slug'] . ')' ); ?></option>
                            <?php endforeach; ?>
                        </select></p>
                        <p class="description"><?php esc_html_e( 'Only Elementor documents assigned to the selected Polylang language will be exported. The default language is selected initially.', 'translation-bridge-for-elementor-and-weblate' ); ?></p>
                    <?php endif; ?>

                    <p><label><input type="checkbox" name="export_post_titles" value="1"> <?php esc_html_e( 'Export WordPress post/page titles', 'translation-bridge-for-elementor-and-weblate' ); ?></label></p>
                    <p class="description"><?php esc_html_e( 'Off by default so imports do not rename administrative translated pages such as “Home - Polski”. Elementor headings are exported regardless.', 'translation-bridge-for-elementor-and-weblate' ); ?></p>

                    <p><label for="ewb_post_ids"><strong><?php esc_html_e( 'Post IDs', 'translation-bridge-for-elementor-and-weblate' ); ?></strong></label><br>
                    <input id="ewb_post_ids" name="post_ids" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'Leave empty to export all matching Elementor content', 'translation-bridge-for-elementor-and-weblate' ); ?>"></p>
                    <p class="description"><?php esc_html_e( 'Optional comma-separated IDs. The language and Post ID filters are combined.', 'translation-bridge-for-elementor-and-weblate' ); ?></p>
                    <?php submit_button( __( 'Download messages.pot', 'translation-bridge-for-elementor-and-weblate' ), 'primary', 'submit', false ); ?>
                </form>
            <?php else : ?>
                <div class="notice notice-error inline"><p><?php esc_html_e( 'Activate Elementor before exporting.', 'translation-bridge-for-elementor-and-weblate' ); ?></p></div>
            <?php endif; ?>

            <hr>
            <h2><?php esc_html_e( '2. Import from Weblate', 'translation-bridge-for-elementor-and-weblate' ); ?></h2>
            <?php if ( $elementor && $polylang ) : ?>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="ewb_import_po">
                    <?php wp_nonce_field( 'ewb_import_po' ); ?>
                    <p><label for="ewb_target_lang"><strong><?php esc_html_e( 'Target language', 'translation-bridge-for-elementor-and-weblate' ); ?></strong></label><br>
                    <select id="ewb_target_lang" name="target_lang" required><option value=""><?php esc_html_e( 'Select language…', 'translation-bridge-for-elementor-and-weblate' ); ?></option>
                    <?php foreach ( $languages as $lang ) : ?><option value="<?php echo esc_attr( $lang['slug'] ); ?>"><?php echo esc_html( $lang['name'] . ' (' . $lang['slug'] . ')' ); ?></option><?php endforeach; ?>
                    </select></p>
                    <p><label for="ewb_po_file"><strong><?php esc_html_e( 'Translated PO file', 'translation-bridge-for-elementor-and-weblate' ); ?></strong></label><br>
                    <input id="ewb_po_file" name="po_file" type="file" accept=".po,text/x-gettext-translation,text/plain" required></p>
                    <p><label><input type="checkbox" name="create_missing" value="1" checked> <?php esc_html_e( 'Create missing Polylang translations as drafts by cloning the Elementor layout', 'translation-bridge-for-elementor-and-weblate' ); ?></label></p>
                    <p><label><input type="checkbox" name="sync_layout" value="1"> <?php esc_html_e( 'Overwrite existing target Elementor layouts from the source before applying translations', 'translation-bridge-for-elementor-and-weblate' ); ?></label></p>
                    <p class="description"><?php esc_html_e( 'Leave overwrite off unless target element IDs no longer match the source.', 'translation-bridge-for-elementor-and-weblate' ); ?></p>
                    <?php submit_button( __( 'Import translations', 'translation-bridge-for-elementor-and-weblate' ), 'primary', 'submit', false ); ?>
                </form>
            <?php else : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e( 'Automatic import requires Polylang. Export remains available with Elementor alone.', 'translation-bridge-for-elementor-and-weblate' ); ?></p></div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_polylang_languages() {
        if ( ! function_exists( 'pll_languages_list' ) ) {
            return array();
        }
        $slugs = pll_languages_list( array( 'hide_empty' => 0, 'fields' => 'slug' ) );
        $names = pll_languages_list( array( 'hide_empty' => 0, 'fields' => 'name' ) );
        $out = array();
        foreach ( (array) $slugs as $i => $slug ) {
            $out[] = array( 'slug' => (string) $slug, 'name' => isset( $names[ $i ] ) ? (string) $names[ $i ] : (string) $slug );
        }
        return $out;
    }

    private function get_elementor_documents( $ids = array(), $language = '' ) {
        $post_types = get_post_types( array( 'show_ui' => true ), 'names' );
        $post_types = array_values( array_unique( array_merge( $post_types, array( 'elementor_library' ) ) ) );
        $args = array(
            'post_type'      => $post_types,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'meta_query'     => array( array( 'key' => '_elementor_edit_mode', 'value' => 'builder', 'compare' => '=' ) ),
        );
        if ( $ids ) {
            $args['post__in'] = array_map( 'absint', $ids );
        }
        $query = new WP_Query( $args );
        $documents = array_map( 'absint', $query->posts );
        if ( $language && function_exists( 'pll_get_post_language' ) ) {
            $documents = array_values( array_filter( $documents, function( $post_id ) use ( $language ) {
                return (string) pll_get_post_language( $post_id, 'slug' ) === (string) $language;
            } ) );
        }
        return $documents;
    }

    public function handle_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'translation-bridge-for-elementor-and-weblate' ) );
        }
        check_admin_referer( 'ewb_export_pot' );
        if ( ! $this->elementor_available() ) {
            $this->redirect_notice( 'Elementor is not active.', 'error' );
        }

        $ids = array();
        if ( ! empty( $_POST['post_ids'] ) ) {
            $raw = preg_split( '/\\s*,\\s*/', sanitize_text_field( wp_unslash( $_POST['post_ids'] ) ) );
            $ids = array_values( array_filter( array_map( 'absint', $raw ) ) );
        }
        $export_lang = isset( $_POST['export_lang'] ) ? sanitize_key( wp_unslash( $_POST['export_lang'] ) ) : '';
        if ( $export_lang ) {
            if ( ! $this->polylang_available() ) {
                $this->redirect_notice( 'Polylang is required to filter the export by language.', 'error' );
            }
            $valid_languages = wp_list_pluck( $this->get_polylang_languages(), 'slug' );
            if ( ! in_array( $export_lang, $valid_languages, true ) ) {
                $this->redirect_notice( 'Unknown Polylang export language.', 'error' );
            }
        }
        $export_titles = ! empty( $_POST['export_post_titles'] );
        $documents = $this->get_elementor_documents( $ids, $export_lang );
        if ( ! $documents ) {
            $this->redirect_notice( 'No matching Elementor documents were found.', 'warning' );
        }
        $entries = array();
        foreach ( $documents as $post_id ) {
            $entries = array_merge( $entries, $this->extract_post_entries( $post_id, $export_titles ) );
        }
        $pot = $this->build_pot( $entries, $export_lang );
        nocache_headers();
        header( 'Content-Type: text/x-gettext-translation-template; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="messages.pot"' );
        header( 'Content-Length: ' . strlen( $pot ) );
        echo $pot; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private function extract_post_entries( $post_id, $export_titles ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array();
        }
        $entries = array();
        $title = trim( (string) $post->post_title );
        if ( $export_titles && '' !== $title ) {
            $entries[] = array(
                'context'  => 'ewb|post=' . $post_id . '|postfield=title',
                'msgid'    => $this->normalize_po_text( $title ),
                'comment'  => 'Post title: ' . $title,
                'location' => 'wordpress/post-' . $post_id . '/title',
            );
        }
        $excerpt = trim( (string) $post->post_excerpt );
        if ( '' !== $excerpt ) {
            $entries[] = array(
                'context'  => 'ewb|post=' . $post_id . '|postfield=excerpt',
                'msgid'    => $this->normalize_po_text( $excerpt ),
                'comment'  => 'Post excerpt: ' . $title,
                'location' => 'wordpress/post-' . $post_id . '/excerpt',
            );
        }

        $document = \Elementor\Plugin::instance()->documents->get( $post_id );
        if ( ! $document || ! method_exists( $document, 'get_elements_data' ) ) {
            return $entries;
        }
        foreach ( (array) $document->get_elements_data() as $element ) {
            $this->extract_element_entries( $post_id, $element, $entries, $title );
        }
        return $entries;
    }

    private function extract_element_entries( $post_id, $element, &$entries, $post_title ) {
        if ( ! is_array( $element ) ) {
            return;
        }
        $element_id = isset( $element['id'] ) ? (string) $element['id'] : '';
        $widget = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : ( isset( $element['elType'] ) ? (string) $element['elType'] : 'element' );
        $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

        if ( $element_id && $settings ) {
            $this->walk_settings( $settings, array(), function( $value, $path ) use ( $post_id, $element_id, $widget, &$entries, $post_title ) {
                $value = $this->normalize_po_text( $value );
                if ( ! $this->is_translatable_setting( $path, $value, $widget ) ) {
                    return;
                }
                $pointer = $this->encode_pointer( $path );
                $last = end( $path );
                $entries[] = array(
                    'context'  => 'ewb|post=' . $post_id . '|element=' . rawurlencode( $element_id ) . '|path=' . rawurlencode( $pointer ),
                    'msgid'    => $value,
                    'comment'  => sprintf( 'Page: %s | Widget: %s | Setting: %s', $post_title, $widget, (string) $last ),
                    'location' => 'wordpress/post-' . $post_id . '/element-' . $element_id . $pointer,
                );
            } );
        }
        if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
            foreach ( $element['elements'] as $child ) {
                $this->extract_element_entries( $post_id, $child, $entries, $post_title );
            }
        }
    }

    private function walk_settings( $value, $path, $callback ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $child ) {
                $next = $path;
                $next[] = (string) $key;
                $this->walk_settings( $child, $next, $callback );
            }
            return;
        }
        if ( is_string( $value ) ) {
            $callback( $value, $path );
        }
    }

    private function normalize_po_text( $value ) {
        return str_replace( array( "\r\n", "\r", "\u{2028}", "\u{2029}" ), "\n", (string) $value );
    }

    private function is_translatable_setting( $path, $value, $widget = '' ) {
        $value = trim( (string) $value );
        if ( '' === $value || empty( $path ) ) {
            return false;
        }

        $widget      = strtolower( (string) $widget );
        $last        = strtolower( (string) end( $path ) );
        $path_string = strtolower( implode( '/', $path ) );

        foreach ( $path as $segment ) {
            if ( isset( $segment[0] ) && '_' === $segment[0] ) {
                return $this->setting_filter_override( false, $path, $value, $widget );
            }
        }

        $positive = array( 'title','subtitle','sub_title','heading','subheading','headline','text','editor','content','description','desc','label','placeholder','caption','before','after','before_text','after_text','prefix','suffix','html','button_text','button_label','link_text','item_text','tab_title','tab_content','accordion_title','accordion_content','testimonial_content','testimonial_name','testimonial_job','name','job','company','quote','field_label','field_placeholder','submit_text','success_message','error_message','required_message','invalid_message','message','tooltip','aria_label','alt','image_alt','footer_additional_info','currency_format','rotating_text','highlighted_text','ribbon_title','badge_text','sale_text' );
        $human_key = in_array( $last, $positive, true ) || (bool) preg_match( '/(?:^|_)(title|subtitle|heading|headline|text|content|description|label|placeholder|caption|message|tooltip|quote|name|job)$/', $last );

        $technical_exact = array( 'url','link','href','css','class','classes','element_id','anchor','color','size','width','height','margin','padding','border','radius','align','justify','position','animation','transition','transform','icon','image','media','attachment','query','taxonomy','category','template_id','shortcode','dynamic','responsive','visibility','motion','breakpoint','columns','rows','gap','order','z_index','selector','html_tag','tag_name','_elementor','autoplay','duration','delay','speed','easing','effect','slides_per_view','navigation','pagination','skin','layout','display','overflow','object_fit','object-position','direction','reverse','loop','sticky','offset','ratio','aspect_ratio','target','rel','role','schema','lightbox','lazyload','mousewheel' );
        $technical_prefix = '/^(?:background|typography|font|margin|padding|border|radius|align|justify|position|animation|transition|transform|responsive|motion|query|taxonomy|template|display|overflow|object|grid|flex|gap|z_index|breakpoint|icon|media|attachment)(?:_|-)/';
        foreach ( $path as $i => $segment ) {
            $segment = strtolower( (string) $segment );
            if ( in_array( $segment, $technical_exact, true ) ) {
                return $this->setting_filter_override( false, $path, $value, $widget );
            }
            // A human-facing final key such as link_text or image_alt must not be
            // rejected merely because its name contains a technical word.
            $is_last = ( $i === count( $path ) - 1 );
            if ( ( ! $is_last || ! $human_key ) && preg_match( $technical_prefix, $segment ) ) {
                return $this->setting_filter_override( false, $path, $value, $widget );
            }
        }

        // Known template/editor noise found in real exports.
        if ( 'divider' === $widget && 'text' === $last && 0 === strcasecmp( $value, 'Trenner' ) ) {
            return $this->setting_filter_override( false, $path, $value, $widget );
        }

        $visible_text = trim( html_entity_decode( wp_strip_all_tags( $value, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

        // Pure markup and punctuation/symbol-only values do not need translation.
        if ( false !== strpos( $value, '<' ) && false !== strpos( $value, '>' ) && ! preg_match( '/[\\p{L}\\p{N}]/u', $visible_text ) ) {
            return $this->setting_filter_override( false, $path, $value, $widget );
        }
        if ( ! preg_match( '/\\p{L}/u', $visible_text ) ) {
            return $this->setting_filter_override( false, $path, $value, $widget );
        }

        if ( preg_match( '~^(https?:)?//|^mailto:|^tel:~i', $value ) ) {
            return $this->setting_filter_override( false, $path, $value, $widget );
        }

        if ( $this->is_hard_technical_value( $value ) ) {
            return $this->setting_filter_override( false, $path, $value, $widget );
        }

        // These appeared as implementation labels in previous exports, but can be genuine
        // visible copy. Skip only when they occur outside an explicitly human-facing field,
        // or inside a structural/container element.
        $ambiguous = array( 'card', 'html', 'item' );
        if ( in_array( strtolower( $value ), $ambiguous, true ) && ( ! $human_key || in_array( $widget, array( 'container', 'section', 'column' ), true ) ) ) {
            return $this->setting_filter_override( false, $path, $value, $widget );
        }

        $decision = (bool) $human_key;
        if ( ! $decision && $this->looks_like_human_text( $visible_text ) ) {
            $decision = true;
        }
        return $this->setting_filter_override( $decision, $path, $value, $widget );
    }

    private function setting_filter_override( $default, $path, $value, $widget ) {
        $forced = apply_filters( 'ewb_is_translatable_setting', null, $path, $value, $widget );
        return null === $forced ? (bool) $default : (bool) $forced;
    }

    private function is_hard_technical_value( $value ) {
        $lower = strtolower( trim( (string) $value ) );
        $tokens = array(
            'a','abbr','address','article','aside','b','blockquote','br','button','code','div','em','figcaption','figure','footer','form','h1','h2','h3','h4','h5','h6','header','hr','i','label','li','main','nav','ol','p','pre','section','small','span','strong','sub','sup','table','tbody','td','th','thead','tr','u','ul',
            'left','right','center','start','end','top','bottom','middle','stretch','baseline','flex-start','flex-end','space-between','space-around','space-evenly','row','column','row-reverse','column-reverse','wrap','nowrap','horizontal','vertical','vertical-padding','horizontal-padding',
            'block','inline','inline-block','flex','inline-flex','grid','inline-grid','none','auto','normal','inherit','initial','unset','relative','absolute','fixed','sticky','static','hidden','visible','scroll','cover','contain','repeat','repeat-x','repeat-y','no-repeat','solid','dashed','dotted','double',
            'default','yes','no','on','off','true','false','desktop','tablet','mobile','classic','gradient','global','custom','boxed','full_width','full-width','fit-to-screen','new-tab','nofollow','noopener','noreferrer',
            'trenner',
        );
        if ( in_array( $lower, $tokens, true ) ) {
            return true;
        }
        if ( preg_match( '/^[a-z][a-z0-9_]*(?:[-_][a-z0-9_]+)+$/', $lower ) ) {
            return true;
        }
        if ( preg_match( '/(?:^|[;{])\\s*--?[a-z0-9_-]+\\s*:/i', $value )
            || preg_match( '/\\b(?:var|calc|min|max|clamp|rgb|rgba|hsl|hsla|linear-gradient|radial-gradient|cubic-bezier|translate|translatex|translatey|scale|rotate)\\s*\\(/i', $value )
            || preg_match( '/^[.#][a-z_][a-z0-9_-]*(?:\\s*[>+~ ]\\s*[.#a-z_][a-z0-9_-]*)*$/i', $value ) ) {
            return true;
        }
        return false;
    }

    private function looks_like_human_text( $value ) {
        $value = trim( (string) $value );
        if ( strlen( $value ) < 4 || ! preg_match( '/\\s/u', $value ) ) {
            return false;
        }
        if ( preg_match( '/[{};]|(?:^|\\s)--[a-z0-9_-]+|\\b(?:px|rem|em|vh|vw|deg|ms)\\b/i', $value ) ) {
            return false;
        }
        preg_match_all( '/\\p{L}[\\p{L}\\p{M}\'’\\-]*/u', $value, $matches );
        return isset( $matches[0] ) && count( $matches[0] ) >= 2;
    }

    private function encode_pointer( $segments ) {
        $escaped = array_map( function( $segment ) { return str_replace( array( '~', '/' ), array( '~0', '~1' ), (string) $segment ); }, $segments );
        return '/' . implode( '/', $escaped );
    }

    private function decode_pointer( $pointer ) {
        if ( '' === (string) $pointer || '/' !== $pointer[0] ) {
            return array();
        }
        return array_map( function( $segment ) { return str_replace( array( '~1', '~0' ), array( '/', '~' ), $segment ); }, explode( '/', substr( $pointer, 1 ) ) );
    }

    private function build_pot( $entries, $source_language = '' ) {
        $out  = "msgid \"\"\nmsgstr \"\"\n";
        $out .= '"Project-Id-Version: Translation Bridge for Elementor and Weblate ' . self::VERSION . '\\n"' . "\n";
        $out .= '"MIME-Version: 1.0\\n"' . "\n";
        $out .= '"Content-Type: text/plain; charset=UTF-8\\n"' . "\n";
        $out .= '"Content-Transfer-Encoding: 8bit\\n"' . "\n";
        $out .= '"X-Generator: Translation Bridge for Elementor and Weblate ' . self::VERSION . '\\n"' . "\n";
        if ( $source_language ) {
            $out .= '"X-Source-Language: ' . $this->po_escape_header( $source_language ) . '\\n"' . "\n";
        }
        $out .= "\n";

        $seen = array();
        foreach ( $entries as $entry ) {
            if ( isset( $seen[ $entry['context'] ] ) ) {
                continue;
            }
            $seen[ $entry['context'] ] = true;
            $out .= '#. ' . str_replace( array( "\r", "\n" ), ' ', (string) $entry['comment'] ) . "\n";
            $out .= '#: ' . preg_replace( '/\\s+/', '-', (string) $entry['location'] ) . "\n";
            if ( false !== strpos( (string) $entry['msgid'], '<' ) && false !== strpos( (string) $entry['msgid'], '>' ) ) {
                $out .= "#. HTML: translate visible text only; keep tags and attributes unchanged.\n";
                $out .= "#, html\n";
            }
            $out .= 'msgctxt ' . $this->po_quote( $entry['context'] ) . "\n";
            $out .= 'msgid ' . $this->po_quote( $entry['msgid'] ) . "\n";
            $out .= "msgstr \"\"\n\n";
        }
        return $out;
    }

    private function po_escape_header( $value ) {
        return str_replace( array( "\r", "\n", '"', '\\' ), '', (string) $value );
    }

    private function po_quote( $value ) {
        $value = $this->normalize_po_text( $value );
        $value = str_replace( array( '\\', '"', "\t", "\n" ), array( '\\\\', '\\"', '\\t', '\\n' ), $value );
        return '"' . $value . '"';
    }

    public function handle_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'translation-bridge-for-elementor-and-weblate' ) );
        }
        check_admin_referer( 'ewb_import_po' );
        if ( ! $this->elementor_available() || ! $this->polylang_available() ) {
            $this->redirect_notice( 'Elementor and Polylang are required for import.', 'error' );
        }
        $target_lang = isset( $_POST['target_lang'] ) ? sanitize_key( wp_unslash( $_POST['target_lang'] ) ) : '';
        $valid = wp_list_pluck( $this->get_polylang_languages(), 'slug' );
        if ( ! $target_lang || ! in_array( $target_lang, $valid, true ) ) {
            $this->redirect_notice( 'Select a valid Polylang target language.', 'error' );
        }
        if ( empty( $_FILES['po_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['po_file']['tmp_name'] ) ) {
            $this->redirect_notice( 'Upload a valid PO file.', 'error' );
        }
        $filename = isset( $_FILES['po_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['po_file']['name'] ) ) : '';
        if ( 'po' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
            $this->redirect_notice( 'The uploaded file must have a .po extension.', 'error' );
        }
        if ( ! class_exists( 'PO' ) ) {
            require_once ABSPATH . WPINC . '/pomo/po.php';
        }
        $po = new PO();
        if ( ! $po->import_from_file( $_FILES['po_file']['tmp_name'] ) ) {
            $this->redirect_notice( 'Could not parse the PO file.', 'error' );
        }
        $parsed     = $this->po_to_operations( $po );
        $operations = $parsed['operations'];
        if ( ! $operations ) {
            $this->redirect_notice( 'No valid translated bridge entries were found in the PO file.', 'warning' );
        }
        $result = $this->apply_operations( $operations, $target_lang, ! empty( $_POST['create_missing'] ), ! empty( $_POST['sync_layout'] ) );
        $result['skipped'] += $parsed['skipped'];
        $this->redirect_notice( sprintf( 'Imported %1$d translations into %2$d page(s); created %3$d page(s); skipped %4$d entries.', $result['updated'], $result['posts'], $result['created'], $result['skipped'] ), $result['updated'] ? 'success' : 'warning' );
    }

    private function po_to_operations( $po ) {
        $operations = array();
        $skipped    = 0;

        foreach ( (array) $po->entries as $entry ) {
            if ( empty( $entry->context ) || empty( $entry->translations ) || ! isset( $entry->translations[0] ) || '' === (string) $entry->translations[0] ) {
                continue;
            }

            $translation = $this->normalize_po_text( (string) $entry->translations[0] );
            $source      = isset( $entry->singular ) ? $this->normalize_po_text( (string) $entry->singular ) : '';

            // Elementor text-editor values often contain formatting HTML. The Weblate
            // translation may change visible text, but the tag/attribute sequence must
            // remain byte-for-byte equivalent so importing cannot damage the layout.
            if ( $source && false !== strpos( $source, '<' ) && false !== strpos( $source, '>' ) ) {
                if ( $this->html_tag_sequence( $source ) !== $this->html_tag_sequence( $translation ) ) {
                    ++$skipped;
                    continue;
                }
            }

            $context = (string) $entry->context;
            if ( preg_match( '/^ewb\|post=(\d+)\|postfield=(title|excerpt)$/', $context, $m ) ) {
                $operations[ absint( $m[1] ) ][] = array( 'type' => 'postfield', 'field' => $m[2], 'translation' => $translation );
            } elseif ( preg_match( '/^ewb\|post=(\d+)\|element=([^|]+)\|path=(.+)$/', $context, $m ) ) {
                $path = $this->decode_pointer( rawurldecode( $m[3] ) );
                if ( $path ) {
                    $operations[ absint( $m[1] ) ][] = array( 'type' => 'element', 'element_id' => rawurldecode( $m[2] ), 'path' => $path, 'translation' => $translation );
                } else {
                    ++$skipped;
                }
            }
        }

        return array( 'operations' => $operations, 'skipped' => $skipped );
    }

    private function html_tag_sequence( $value ) {
        preg_match_all( '/<[^>]+>/u', (string) $value, $matches );
        return isset( $matches[0] ) ? $matches[0] : array();
    }

    private function apply_operations( $operations, $target_lang, $create_missing, $sync_layout ) {
        $result = array( 'updated' => 0, 'skipped' => 0, 'posts' => 0, 'created' => 0 );
        foreach ( $operations as $source_id => $post_ops ) {
            $source = get_post( $source_id );
            if ( ! $source ) {
                $result['skipped'] += count( $post_ops );
                continue;
            }
            if ( function_exists( 'pll_get_post_language' ) && pll_get_post_language( $source_id, 'slug' ) === $target_lang ) {
                $result['skipped'] += count( $post_ops );
                continue;
            }
            $target_id = absint( pll_get_post( $source_id, $target_lang ) );
            if ( ! $target_id && $create_missing ) {
                $target_id = $this->create_polylang_translation( $source_id, $target_lang );
                if ( $target_id ) {
                    ++$result['created'];
                }
            }
            if ( ! $target_id ) {
                $result['skipped'] += count( $post_ops );
                continue;
            }
            $source_doc = \Elementor\Plugin::instance()->documents->get( $source_id );
            $target_doc = \Elementor\Plugin::instance()->documents->get( $target_id );
            if ( ! $source_doc || ! $target_doc ) {
                $result['skipped'] += count( $post_ops );
                continue;
            }
            $target_elements = method_exists( $target_doc, 'get_elements_data' ) ? $target_doc->get_elements_data() : array();
            if ( $sync_layout || empty( $target_elements ) ) {
                $target_elements = $source_doc->get_elements_data();
            }
            $changed_elements = false;
            $post_update = array( 'ID' => $target_id );
            $post_changed = false;
            foreach ( $post_ops as $op ) {
                if ( 'postfield' === $op['type'] ) {
                    if ( 'title' === $op['field'] ) {
                        $post_update['post_title'] = $op['translation'];
                        $post_changed = true;
                        ++$result['updated'];
                    } elseif ( 'excerpt' === $op['field'] ) {
                        $post_update['post_excerpt'] = $op['translation'];
                        $post_changed = true;
                        ++$result['updated'];
                    }
                } elseif ( $this->set_element_translation( $target_elements, $op['element_id'], $op['path'], $op['translation'] ) ) {
                    $changed_elements = true;
                    ++$result['updated'];
                } else {
                    ++$result['skipped'];
                }
            }
            if ( $post_changed ) {
                wp_update_post( wp_slash( $post_update ) );
            }
            if ( $changed_elements || $sync_layout ) {
                if ( method_exists( $target_doc, 'set_is_built_with_elementor' ) ) {
                    $target_doc->set_is_built_with_elementor( true );
                }
                $target_doc->save( array( 'elements' => $target_elements ) );
            }
            ++$result['posts'];
        }
        return $result;
    }

    private function set_element_translation( &$elements, $element_id, $path, $translation ) {
        foreach ( $elements as &$element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            if ( isset( $element['id'] ) && (string) $element['id'] === (string) $element_id ) {
                return isset( $element['settings'] ) && is_array( $element['settings'] ) ? $this->set_path_value( $element['settings'], $path, $translation ) : false;
            }
            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && $this->set_element_translation( $element['elements'], $element_id, $path, $translation ) ) {
                return true;
            }
        }
        unset( $element );
        return false;
    }

    private function set_path_value( &$array, $path, $translation ) {
        $ref =& $array;
        $last = count( $path ) - 1;
        foreach ( $path as $i => $segment ) {
            $key = ctype_digit( (string) $segment ) ? (int) $segment : $segment;
            if ( $i === $last ) {
                if ( ! is_array( $ref ) || ! array_key_exists( $key, $ref ) || ! is_string( $ref[ $key ] ) ) {
                    return false;
                }
                $ref[ $key ] = $translation;
                return true;
            }
            if ( ! is_array( $ref ) || ! array_key_exists( $key, $ref ) || ! is_array( $ref[ $key ] ) ) {
                return false;
            }
            $ref =& $ref[ $key ];
        }
        return false;
    }

    private function create_polylang_translation( $source_id, $target_lang ) {
        $source = get_post( $source_id );
        if ( ! $source ) {
            return 0;
        }
        $target_parent = $source->post_parent ? absint( pll_get_post( $source->post_parent, $target_lang ) ) : 0;
        $new_id = wp_insert_post( wp_slash( array(
            'post_type'      => $source->post_type,
            'post_status'    => 'draft',
            'post_title'     => $source->post_title,
            'post_excerpt'   => $source->post_excerpt,
            'post_content'   => $source->post_content,
            'post_parent'    => $target_parent,
            'menu_order'     => $source->menu_order,
            'comment_status' => $source->comment_status,
            'ping_status'    => $source->ping_status,
        ) ), true );
        if ( is_wp_error( $new_id ) ) {
            return 0;
        }
        $new_id = absint( $new_id );
        foreach ( array( '_elementor_template_type', '_wp_page_template' ) as $meta_key ) {
            $value = get_post_meta( $source_id, $meta_key, true );
            if ( '' !== $value ) {
                update_post_meta( $new_id, $meta_key, $value );
            }
        }
        $thumbnail_id = get_post_thumbnail_id( $source_id );
        if ( $thumbnail_id ) {
            set_post_thumbnail( $new_id, $thumbnail_id );
        }
        pll_set_post_language( $new_id, $target_lang );
        $translations = pll_get_post_translations( $source_id );
        $translations[ $target_lang ] = $new_id;
        pll_save_post_translations( $translations );

        $source_doc = \Elementor\Plugin::instance()->documents->get( $source_id );
        $target_doc = \Elementor\Plugin::instance()->documents->get( $new_id );
        if ( $source_doc && $target_doc ) {
            if ( method_exists( $target_doc, 'set_is_built_with_elementor' ) ) {
                $target_doc->set_is_built_with_elementor( true );
            }
            $save_data = array( 'elements' => $source_doc->get_elements_data() );
            $page_settings = get_post_meta( $source_id, '_elementor_page_settings', true );
            if ( is_array( $page_settings ) && $page_settings ) {
                $save_data['settings'] = $page_settings;
            }
            $target_doc->save( $save_data );
        }
        return $new_id;
    }

    private function redirect_notice( $message, $type = 'success' ) {
        wp_safe_redirect( add_query_arg( array( 'page' => self::MENU_SLUG, 'ewb_notice' => $message, 'ewb_type' => sanitize_key( $type ) ), admin_url( 'tools.php' ) ) );
        exit;
    }
}

add_action( 'plugins_loaded', function() {
    EWB_Elementor_Weblate_Bridge::instance();
} );
