<?php
/*
Plugin Name: KISS - Maintenance Mode
Description: Puts the site into maintenance mode and displays a custom splash page for non-logged-in visitors.
Version: 1.4
Author: Hypercart DBA Neochrome
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maintenance_Mode_Splash {
    private $option_name = 'maintenance_mode_splash_settings';

    public function __construct() {
        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Add menu page
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Frontend maintenance mode check
        add_action( 'template_redirect', array( $this, 'maintenance_mode_redirect' ) );

        // Enqueue scripts for admin (including color picker and media uploader)
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
    }

    public function admin_scripts( $hook ) {
        if ( 'settings_page_maintenance-mode-splash' === $hook ) {
            // Enqueue media for image upload
            wp_enqueue_media();
            // Enqueue color picker
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );

            // Add inline script to initialize color picker and image upload
            add_action( 'admin_print_footer_scripts', array( $this, 'admin_inline_js' ) );
        }
    }

    public function admin_inline_js() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($){
            // Image upload
            var file_frame;
            $('#upload_image_button').on('click', function(e) {
                e.preventDefault();
                if (file_frame) {
                    file_frame.open();
                    return;
                }
                file_frame = wp.media.frames.file_frame = wp.media({
                    title: 'Select Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });
                file_frame.on('select', function() {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    $('#splash_image').val(attachment.url);
                });
                file_frame.open();
            });

            // Color picker
            $('.mms-color-field').wpColorPicker();
        });
        </script>
        <?php
    }

    public function register_settings() {
        register_setting( $this->option_name, $this->option_name, array( $this, 'sanitize_settings' ) );

        add_settings_section( 'mms_section', 'Maintenance Mode Settings', null, $this->option_name );

        // Enable/Disable
        add_settings_field(
            'enabled',
            'Enable Maintenance Mode',
            array( $this, 'field_enabled' ),
            $this->option_name,
            'mms_section'
        );

        // Splash Image
        add_settings_field(
            'splash_image',
            'Splash Image (500x500 recommended)',
            array( $this, 'field_splash_image' ),
            $this->option_name,
            'mms_section'
        );

        // Headline text
        add_settings_field(
            'headline_text',
            'Headline Text',
            array( $this, 'field_headline_text' ),
            $this->option_name,
            'mms_section'
        );

        // Headline font size
        add_settings_field(
            'headline_font_size',
            'Headline Font Size (default: 100px)',
            array( $this, 'field_headline_font_size' ),
            $this->option_name,
            'mms_section'
        );

        // Headline font family
        add_settings_field(
            'headline_font',
            'Headline Font Family',
            array( $this, 'field_headline_font' ),
            $this->option_name,
            'mms_section'
        );

        // Headline alignment
        add_settings_field(
            'headline_alignment',
            'Headline Text Alignment',
            array( $this, 'field_headline_alignment' ),
            $this->option_name,
            'mms_section'
        );

        // Contact text
        add_settings_field(
            'contact_text',
            'Contact Text (HTML allowed)',
            array( $this, 'field_contact_text' ),
            $this->option_name,
            'mms_section'
        );

        // Contact font size
        add_settings_field(
            'contact_font_size',
            'Contact Font Size (default: 16px)',
            array( $this, 'field_contact_font_size' ),
            $this->option_name,
            'mms_section'
        );

        // Contact font family
        add_settings_field(
            'contact_font',
            'Contact Font Family',
            array( $this, 'field_contact_font' ),
            $this->option_name,
            'mms_section'
        );

        // Contact alignment
        add_settings_field(
            'contact_alignment',
            'Contact Text Alignment',
            array( $this, 'field_contact_alignment' ),
            $this->option_name,
            'mms_section'
        );

        // Background color
        add_settings_field(
            'background_color',
            'Background Color (Hex)',
            array( $this, 'field_background_color' ),
            $this->option_name,
            'mms_section'
        );
    }

    public function sanitize_settings( $input ) {
        $old_options = get_option( $this->option_name );

        $input['enabled'] = isset( $input['enabled'] ) ? (bool) $input['enabled'] : false;
        $input['splash_image'] = esc_url_raw( $input['splash_image'] );
        $input['headline_text'] = sanitize_text_field( $input['headline_text'] );
        $input['headline_font_size'] = sanitize_text_field( $input['headline_font_size'] );
        $input['headline_font'] = sanitize_text_field( $input['headline_font'] );
        $input['headline_alignment'] = sanitize_text_field( $input['headline_alignment'] );
        $input['contact_text'] = wp_kses_post( $input['contact_text'] );
        $input['contact_font_size'] = sanitize_text_field( $input['contact_font_size'] );
        $input['contact_font'] = sanitize_text_field( $input['contact_font'] );
        $input['contact_alignment'] = sanitize_text_field( $input['contact_alignment'] );
        $input['background_color'] = preg_match('/^#[a-fA-F0-9]{6}$/', $input['background_color']) ? $input['background_color'] : '#ffffff';

        // Check if something changed to avoid duplicates
        if ( isset($_POST[$this->option_name]) ) {
            add_settings_error(
                $this->option_name, // slug
                'mms_custom_updated', // unique code to prevent duplication
                'Settings saved. If you have enabled Maintenance Mode below, please open an Incognito or Private Web Browser window to review your Splash page. You may need to clear both your server and/or browser caches several times to see it.',
                'updated'
            );
        }

        return $input;
    }

    public function add_admin_menu() {
        add_options_page(
            'Maintenance Mode Splash',
            'Maintenance Mode Splash',
            'manage_options',
            'maintenance-mode-splash',
            array( $this, 'settings_page_html' )
        );
    }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Maintenance Mode Splash Settings</h1>
            <?php settings_errors( $this->option_name ); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( $this->option_name );
                do_settings_sections( $this->option_name );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // Field Callbacks
    public function field_enabled() {
        $options = get_option( $this->option_name );
        $enabled = ! empty( $options['enabled'] ) ? $options['enabled'] : false;
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enabled]" value="1" <?php checked($enabled, 1); ?> />
        <?php
    }

    public function field_splash_image() {
        $options = get_option( $this->option_name );
        $image = ! empty( $options['splash_image'] ) ? $options['splash_image'] : '';
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[splash_image]" id="splash_image" value="<?php echo esc_attr($image); ?>" style="width:60%;" />
        <button type="button" class="button" id="upload_image_button">Upload Image</button>
        <?php
    }

    public function field_headline_text() {
        $options = get_option( $this->option_name );
        $value = isset($options['headline_text']) && $options['headline_text'] !== '' ? $options['headline_text'] : 'Under Construction';
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[headline_text]" value="<?php echo esc_attr($value); ?>" style="width:60%;" />
        <?php
    }

    public function field_headline_font_size() {
        $options = get_option( $this->option_name );
        $value = ! empty( $options['headline_font_size'] ) ? $options['headline_font_size'] : '100px';
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[headline_font_size]" value="<?php echo esc_attr($value); ?>" style="width:60%;" />
        <?php
    }

    public function field_headline_font() {
        $options = get_option( $this->option_name );
        $value = ! empty( $options['headline_font'] ) ? $options['headline_font'] : '';

        $fonts = $this->get_theme_fonts();
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[headline_font]" style="width:60%;">
            <?php foreach ( $fonts as $font ) : ?>
                <option value="<?php echo esc_attr($font); ?>" <?php selected($value, $font); ?>><?php echo esc_html($font); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function field_headline_alignment() {
        $options = get_option( $this->option_name );
        $value = ! empty( $options['headline_alignment'] ) ? $options['headline_alignment'] : 'center';
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[headline_alignment]" style="width:60%;">
            <option value="left" <?php selected($value, 'left'); ?>>Left</option>
            <option value="center" <?php selected($value, 'center'); ?>>Center</option>
            <option value="right" <?php selected($value, 'right'); ?>>Right</option>
        </select>
        <?php
    }

    public function field_contact_text() {
        $options = get_option( $this->option_name );
        $value = isset($options['contact_text']) && $options['contact_text'] !== '' ? $options['contact_text'] : 'Email us at <a href="mailto:info@company.com">info@company.com</a>';
        ?>
        <textarea name="<?php echo esc_attr($this->option_name); ?>[contact_text]" rows="5" style="width:60%;"><?php echo esc_textarea($value); ?></textarea>
        <?php
    }

    public function field_contact_font_size() {
        $options = get_option( $this->option_name );
        $value = ! empty( $options['contact_font_size'] ) ? $options['contact_font_size'] : '16px';
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[contact_font_size]" value="<?php echo esc_attr($value); ?>" style="width:60%;" />
        <?php
    }

    public function field_contact_font() {
        $options = get_option( $this->option_name );
        $value = ! empty( $options['contact_font'] ) ? $options['contact_font'] : '';

        $fonts = $this->get_theme_fonts();
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[contact_font]" style="width:60%;">
            <?php foreach ( $fonts as $font ) : ?>
                <option value="<?php echo esc_attr($font); ?>" <?php selected($value, $font); ?>><?php echo esc_html($font); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function field_contact_alignment() {
        $options = get_option( $this->option_name );
        $value = ! empty( $options['contact_alignment'] ) ? $options['contact_alignment'] : 'center';
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[contact_alignment]" style="width:60%;">
            <option value="left" <?php selected($value, 'left'); ?>>Left</option>
            <option value="center" <?php selected($value, 'center'); ?>>Center</option>
            <option value="right" <?php selected($value, 'right'); ?>>Right</option>
        </select>
        <?php
    }

    public function field_background_color() {
        $options = get_option( $this->option_name );
        $value = ! empty( $options['background_color'] ) ? $options['background_color'] : '#ffffff';
        ?>
        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[background_color]" class="mms-color-field" value="<?php echo esc_attr($value); ?>" style="width:60%;" placeholder="#ffffff" />
        <?php
    }

    private function get_theme_fonts() {
        // In a real scenario, you'd parse theme.json or use theme support features.
        // For demonstration, we provide a static list or attempt to fetch from theme.
        $fonts = array('Arial', 'Helvetica', 'Times New Roman', 'Courier New', 'Verdana'); // fallback
        
        if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
            $theme_json = WP_Theme_JSON_Resolver::get_merged_data();
            $settings = $theme_json->get_settings();
            if ( isset( $settings['typography']['fontFamilies'] ) && is_array( $settings['typography']['fontFamilies'] ) ) {
                $theme_fonts = $settings['typography']['fontFamilies']['theme'] ?? array();
                $extracted_fonts = array_map( function( $f ) {
                    return isset($f['name']) ? $f['name'] : '';
                }, $theme_fonts );
                $extracted_fonts = array_filter($extracted_fonts);
                if ( ! empty( $extracted_fonts ) ) {
                    $fonts = $extracted_fonts;
                }
            }
        }
        
        return $fonts;
    }

    // Helper to determine best contrast color (black or white) based on background color
    private function get_contrast_color( $hexcolor ) {
        // Remove '#' if present
        $hexcolor = ltrim($hexcolor, '#');

        // Convert to RGB
        $r = hexdec(substr($hexcolor, 0, 2));
        $g = hexdec(substr($hexcolor, 2, 2));
        $b = hexdec(substr($hexcolor, 4, 2));

        // Calculate brightness
        // Formula: https://www.w3.org/TR/AERT/#color-contrast
        $brightness = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;

        // Return black for light backgrounds, white for dark
        return ($brightness > 155) ? '#000000' : '#ffffff';
    }

    public function maintenance_mode_redirect() {
        $options = get_option( $this->option_name );
        $enabled = ! empty( $options['enabled'] ) ? $options['enabled'] : false;

        if ( ! $enabled ) {
            return; // Maintenance mode off, do nothing.
        }

        // Allowed roles: administrator, editor, author, shop_manager
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( array_intersect( array('administrator', 'editor', 'author', 'shop_manager'), (array) $user->roles ) ) {
                return; // Allowed roles see the site normally
            }
        }

        // If here, maintenance mode ON and visitor not allowed
        $this->render_splash();
        exit;
    }

    private function render_splash() {
        $options = get_option( $this->option_name );

        $image = ! empty( $options['splash_image'] ) ? $options['splash_image'] : '';
        $headline_text = ! empty( $options['headline_text'] ) ? $options['headline_text'] : 'Under Construction';
        $headline_font_size = ! empty( $options['headline_font_size'] ) ? $options['headline_font_size'] : '100px';
        $headline_font = ! empty( $options['headline_font'] ) ? $options['headline_font'] : 'Arial';
        $headline_alignment = ! empty( $options['headline_alignment'] ) ? $options['headline_alignment'] : 'center';

        $contact_text = ! empty( $options['contact_text'] ) ? $options['contact_text'] : 'Email us at <a href="mailto:info@company.com">info@company.com</a>';
        $contact_font_size = ! empty( $options['contact_font_size'] ) ? $options['contact_font_size'] : '16px';
        $contact_font = ! empty( $options['contact_font'] ) ? $options['contact_font'] : 'Arial';
        $contact_alignment = ! empty( $options['contact_alignment'] ) ? $options['contact_alignment'] : 'center';

        $background_color = ! empty( $options['background_color'] ) ? $options['background_color'] : '#ffffff';

        // Determine text color based on background color
        $text_color = $this->get_contrast_color($background_color);

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title><?php esc_html_e('Maintenance Mode', 'maintenance-mode-splash'); ?></title>
            <style>
                body {
                    margin:0;
                    padding:0;
                    background-color: <?php echo esc_attr($background_color); ?>;
                    color: <?php echo esc_attr($text_color); ?>;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    flex-direction:column;
                    text-align:center;
                    height:100vh;
                    font-family: <?php echo esc_html($headline_font); ?>, sans-serif;
                }
                .mms-container {
                    max-width: 90%;
                    margin:0 auto;
                    text-align:center;
                }
                .mms-image {
                    max-width:500px;
                    max-height:500px;
                    width:auto;
                    height:auto;
                    margin:0 auto 20px;
                    display:block;
                }
                .mms-headline {
                    font-size: <?php echo esc_html($headline_font_size); ?>;
                    text-align: <?php echo esc_html($headline_alignment); ?>;
                    font-family: <?php echo esc_html($headline_font); ?>, sans-serif;
                    margin-bottom:20px;
                    word-wrap: break-word;
                    color: <?php echo esc_attr($text_color); ?>;
                }
                .mms-contact {
                    font-size: <?php echo esc_html($contact_font_size); ?>;
                    text-align: <?php echo esc_html($contact_alignment); ?>;
                    font-family: <?php echo esc_html($contact_font); ?>, sans-serif;
                    word-wrap: break-word;
                    color: <?php echo esc_attr($text_color); ?>;
                }
                .mms-contact a {
                    color: <?php echo esc_attr($text_color); ?>;
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class="mms-container">
                <?php if ( $image ) : ?>
                    <img src="<?php echo esc_url($image); ?>" alt="Maintenance Image" class="mms-image" />
                <?php endif; ?>
                <?php if ( $headline_text ) : ?>
                    <div class="mms-headline"><?php echo esc_html($headline_text); ?></div>
                <?php endif; ?>
                <?php if ( $contact_text ) : ?>
                    <div class="mms-contact"><?php echo wp_kses_post($contact_text); ?></div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
}

new Maintenance_Mode_Splash();
