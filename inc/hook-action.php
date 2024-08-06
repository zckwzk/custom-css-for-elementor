<?php

namespace Custom_Css_FEle\Inc;

// If this file is called directly, abort.
defined('ABSPATH') || exit;

use Elementor\Controls_Manager;
use Elementor\Controls_Stack;
use Elementor\Core\DynamicTags\Dynamic_CSS;
use Elementor\Plugin as Elementor_Plugin;

use Wikimedia\CSS\Parser\Parser;
use Wikimedia\CSS\Sanitizer\StylesheetSanitizer;
use Wikimedia\CSS\Util;

class Hook_Action
{
    /**
     * @var Singleton The reference the *Singleton* instance of this class
     */
    public static $instance;

    /**
     * this class initialize function
     *
     * @return void
     */
    public function init()
    {
        add_action('elementor/element/common/_section_responsive/after_section_end', [$this, 'register_controls'], 10, 2);
        add_action('elementor/element/section/_section_responsive/after_section_end', [$this, 'register_controls'], 10, 2);
        add_action('elementor/element/column/_section_responsive/after_section_end', [$this, 'register_controls'], 10, 2);

        add_action('elementor/element/container/_section_responsive/after_section_end', [$this, 'register_controls'], 10, 2);

        add_action('elementor/element/parse_css', [$this, 'add_post_css'], 10, 2);
        add_action('elementor/css-file/post/parse', [$this, 'add_page_settings_css']);

        add_action('elementor/frontend/after_enqueue_scripts', [$this, 'add_custom_css_for_editor']);
    }

    /**
     * register controls to elementor widget function
     *
     * @param Controls_Stack $element
     * @param [type] $section_id
     * @return void
     */
    public function register_controls(Controls_Stack $element, $section_id)
    {
        if (!current_user_can('edit_pages') && !current_user_can('unfiltered_html')) {
            return;
        }


        $element->start_controls_section(
            '_custom_css_f_ele',
            [
                'label' => esc_html__('Custom CSS for Elementor', 'custom-css-for-elementor'),
                'tab' => Controls_Manager::TAB_ADVANCED,
            ]
        );

        $element->start_controls_tabs('style_tabs');

        $results = [];  // Initialize an empty array to store the result

        // Get active breakpoints from Elementor
        $breakpoints = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();

        foreach ($breakpoints as $breakpoint_key => $breakpoint) {
            // Create an associative array for each breakpoint
            $result = [
                'value' => $breakpoint->get_value(),
                'label' => $breakpoint->get_label(),
                'enabled' => $breakpoint->is_enabled(),
                'direction' => $breakpoint->get_direction(),
                'icon' => \Elementor\Plugin::$instance->breakpoints->get_responsive_icons_classes_map($breakpoint->get_name())
            ];

            // Push the associative array to the results array
            $results[] = $result;
        }

        $desktop_min = \Elementor\Plugin::$instance->breakpoints->get_desktop_min_point();

        $desktop = [
            'value' => $desktop_min,
            'label' => 'desktop',
            'enabled' => 1,
            'direction' => "min",
            'icon' => \Elementor\Plugin::$instance->breakpoints->get_responsive_icons_classes_map("desktop")
        ];

        // Add the desktop breakpoint to the results array
        $results[] = $desktop;

        // Reverse the order of the results array
        $results = array_reverse($results);

        // Iterate over the results array and create controls for each device
        foreach ($results as $result) {
            $device_key = strtolower($result['label']);  // Use label as device key, converted to lowercase
            $device_label = ucfirst($result['label']);  // Capitalize the first letter of the label

            $key_css = preg_replace('/\s+/', '_', $device_key);

            $element->start_controls_tab(
                '_custom_css_' . $key_css,
                [
                    'label' => '<span class="eicon-device-' . $device_key . '" title="' . esc_html__($device_label, 'custom-css-for-elementor') . '"></span>',
                ]
            );

            $element->add_control(
                '_custom_css_f_ele_title_' . $key_css,
                [
                    'label' => esc_html__('Custom CSS (' . $device_label . ') ('  . $result["direction"] . ": " . $result["value"] . "px)", 'custom-css-for-elementor'),
                    'type' => Controls_Manager::HEADING,
                ]
            );

            // Add input control for device value
            $element->add_control(
                '_custom_css_f_ele_value_' . $key_css,
                [
                    'label' => esc_html__('Device Value (px)', 'custom-css-for-elementor'),
                    'type' => Controls_Manager::NUMBER,
                    'default' => $result['value'],
                    'description' => esc_html__('Set the device value in pixels.', 'custom-css-for-elementor'),
                ]
            );

            $element->add_control(
                '_custom_css_f_ele_css_' . $key_css,
                [
                    'type' => Controls_Manager::CODE,
                    'label' => esc_html__('Custom CSS (' . $device_label . ')', 'custom-css-for-elementor'),
                    'language' => 'css',
                    'render_type' => 'ui',
                    'show_label' => false,
                    'separator' => 'none',
                ]
            );

            $element->end_controls_tab();
        }
        $element->end_controls_tabs();

        $element->add_control(
            '_custom_css_f_ele_description',
            [
                'raw' => esc_html__('Use "selector" to target wrapper element. Examples:<br>selector {color: red;} // For main element<br>selector .child-element {margin: 10px;} // For child element<br>.my-class {text-align: center;} // Or use any custom selector', 'custom-css-for-elementor'),
                'type' => Controls_Manager::RAW_HTML,
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $element->add_control(
            '_custom_css_f_ele_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => esc_html__('If the CSS is not reflecting in the editor panel or frontend, you need to write a more specific CSS selector.', 'custom-css-for-elementor'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );

        $element->end_controls_section();

    }

    /**
     * add custom css function to post function
     *
     * @param [type] $post_css
     * @param [type] $element
     * @return void
     */
    public function add_post_css($post_css, $element)
    {
        if ($post_css instanceof Dynamic_CSS) {
            return;
        }

        $element_settings = $element->get_settings();

        $sanitize_css = $this->parse_css_to_remove_injecting_code($element_settings, $post_css->get_element_unique_selector($element));

        $post_css->get_stylesheet()->add_raw_css($sanitize_css);
    }

    /**
     * add custom css function to page function
     *
     * @param [type] $post_css
     * @return void
     */
    public function add_page_settings_css($post_css)
    {

        $document = Elementor_Plugin::instance()->documents->get($post_css->get_post_id());

        $element_settings = $document->get_settings();

        $sanitize_css = $this->parse_css_to_remove_injecting_code($element_settings, $document->get_css_wrapper_selector());

        $post_css->get_stylesheet()->add_raw_css($sanitize_css);
    }

    /**
     * validate css and sanitize css for avoiding injection of malicious code function
     *
     * @param [type] $raw_css
     * @return void
     */
    public function parse_css_to_remove_injecting_code($element_settings, $unique_selector)
    {

        $custom_css = '';

        // Get active breakpoints from Elementor
        $breakpoints = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();

        // Define default desktop breakpoint
        $devices = [
            'desktop' => [
                'media' => '',
                'setting' => '_custom_css_f_ele_css_desktop',
            ]
        ];

        // Add breakpoints to the devices array
        foreach ($breakpoints as $breakpoint_key => $breakpoint) {
            // Add breakpoint settings
            $media_query = " @media (max-width: {$breakpoint->get_value()}px) { ";
            $device_key = strtolower($breakpoint->get_label());
            $key_css = preg_replace('/\s+/', '_', $device_key);
            $devices[$breakpoint_key] = [
                'media' => $media_query,
                'setting' => '_custom_css_f_ele_css_' . $key_css,
                'value' => $breakpoint->get_value()

            ];
        }

        // Extract the 'desktop' element
        $desktop = $devices['desktop'];
        unset($devices['desktop']);

        // Sort the remaining devices by value in descending order
        usort($devices, function ($a, $b) {
            return $b['value'] - $a['value'];
        });

        error_log(print_r($element_settings, true));
        // Prepend the 'desktop' element
        $sorted_devices = ['desktop' => $desktop] + $devices;



        $custom_css_parts = [];

        // Loop through each device and check for custom CSS
        foreach ($sorted_devices as $device => $data) {
            if (!empty($element_settings[$data['setting']])) {
                $css_code = trim($element_settings[$data['setting']]);
                if (!empty($css_code)) {
                    $custom_css_parts[] = $data['media'] . $css_code . ($device != 'desktop' ? ' }' : '');
                }
            }
        }

        if (empty($custom_css_parts)) {
            return;
        }
        // Combine all parts of custom CSS
        $custom_css = implode('', $custom_css_parts);


        $custom_css = str_replace('selector', $unique_selector, $custom_css);

        $remove_tags_css = wp_kses($custom_css, []);
        $parser = Parser::newFromString($remove_tags_css);
        $parsed_css = $parser->parseStylesheet();

        $sanitizer = StylesheetSanitizer::newDefault();
        $sanitized_css = $sanitizer->sanitize($parsed_css);
        $minified_css = Util::stringify($sanitized_css, ['minify' => true]);

        return $minified_css;
    }

    public function get_script_depends()
    {
        return ['editor-css-script'];
    }

    public function add_custom_css_for_editor()
    {
        wp_enqueue_script(
            'purify',
            CUSTOM_CSS_FELE_PLUGIN_URL . 'assets/js/purify.min.js',
            [],
            '3.0.6',
            true
        );

        wp_enqueue_script(
            'editor-css-script',
            CUSTOM_CSS_FELE_PLUGIN_URL . 'assets/js/editor-css-script.js',
            ['elementor-frontend', 'purify'],
            CUSTOM_CSS_FELE_VERSION,
            true
        );

        wp_localize_script(
            'editor-css-script',
            'modelData',
            array(
                'postID' => get_the_ID()
            )
        );
    }

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
