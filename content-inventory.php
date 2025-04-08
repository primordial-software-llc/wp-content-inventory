<?php
/*
Plugin Name: WP Content Inventory
Plugin URI: https://github.com/primordial-software/wp-content-inventory
Description: Displays a comprehensive inventory of all pages and their assigned templates or custom post types and their assigned taxonomies.
Version: 1.0.0
Author: Primordial Software LLC
Author URI: https://www.primordial-software.com/
Text Domain: wp-content-inventory
Domain Path: /languages
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin version
define('WP_CONTENT_INVENTORY_VERSION', '1.0.0');

// Define plugin directory path
define('WP_CONTENT_INVENTORY_PATH', plugin_dir_path(__FILE__));

// Define plugin directory URL
define('WP_CONTENT_INVENTORY_URL', plugin_dir_url(__FILE__));

/**
 * Check if the WordPress and PHP versions meet the plugin's requirements
 */
function wp_content_inventory_check_requirements() {
    $wordpress_version = '5.0';
    $php_version = '7.0';
    
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), $wordpress_version, '<')) {
        add_action('admin_notices', 'wp_content_inventory_wordpress_version_notice');
        return false;
    }
    
    // Check PHP version
    if (version_compare(phpversion(), $php_version, '<')) {
        add_action('admin_notices', 'wp_content_inventory_php_version_notice');
        return false;
    }
    
    return true;
}

/**
 * Display admin notice for WordPress version requirement
 */
function wp_content_inventory_wordpress_version_notice() {
    echo '<div class="notice notice-error">';
    echo '<p>' . sprintf(
        /* translators: %s: WordPress version */
        esc_html__('WP Content Inventory plugin requires WordPress version %s or higher. Please upgrade WordPress to use this plugin.', 'wp-content-inventory'),
        '5.0'
    ) . '</p>';
    echo '</div>';
}

/**
 * Display admin notice for PHP version requirement
 */
function wp_content_inventory_php_version_notice() {
    echo '<div class="notice notice-error">';
    echo '<p>' . sprintf(
        /* translators: %s: PHP version */
        esc_html__('WP Content Inventory plugin requires PHP version %s or higher. Please upgrade PHP to use this plugin.', 'wp-content-inventory'),
        '7.0'
    ) . '</p>';
    echo '</div>';
}

// Load plugin text domain
function wp_content_inventory_load_textdomain() {
    load_plugin_textdomain('wp-content-inventory', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'wp_content_inventory_load_textdomain');

// Enqueue styles
function wp_content_inventory_enqueue_styles($hook) {
    // Only load on our plugin page
    if ($hook != 'toplevel_page_wp-content-inventory') {
        return;
    }
    
    wp_enqueue_style(
        'wp-content-inventory-styles',
        WP_CONTENT_INVENTORY_URL . 'css/content-inventory.css',
        array(),
        WP_CONTENT_INVENTORY_VERSION
    );
}
add_action('admin_enqueue_scripts', 'wp_content_inventory_enqueue_styles');

// Check requirements on init
add_action('admin_init', 'wp_content_inventory_check_requirements');

/**
 * Plugin activation hook
 */
function wp_content_inventory_activation() {
    // Verify requirements on activation
    if (!wp_content_inventory_check_requirements()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('WP Content Inventory plugin requires WordPress 5.0+ and PHP 7.0+.', 'wp-content-inventory'),
            esc_html__('Plugin Activation Error', 'wp-content-inventory'),
            array('response' => 200, 'back_link' => true)
        );
    }
}
register_activation_hook(__FILE__, 'wp_content_inventory_activation');

function wp_content_inventory_plugin_menu() {
    add_menu_page(
        __('Content Inventory', 'wp-content-inventory'),
        __('Content Inventory', 'wp-content-inventory'),
        'manage_options',
        'wp-content-inventory',
        'wp_content_inventory_plugin_page',
        'dashicons-database-view'
    );
}
add_action('admin_menu', 'wp_content_inventory_plugin_menu');

/**
 * Count usage of page templates 
 */
function wp_content_inventory_get_template_counts() {
    global $wpdb;
    
    // Get page templates
    $page_templates = array_merge(
        array('default' => __('Default template', 'wp-content-inventory')),
        wp_get_theme()->get_page_templates()
    );

    // Count the usage of each template
    $template_counts = array();
    foreach ($page_templates as $template_key => $template_name) {
        $template_counts[$template_key] = 0;
    }

    $template_counts_query = "
        SELECT COALESCE(pm.meta_value, 'default') AS page_template, COUNT(*) AS count
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_page_template'
        WHERE p.post_type = 'page'
        GROUP BY COALESCE(pm.meta_value, 'default')
    ";
    $template_counts_results = $wpdb->get_results($template_counts_query, ARRAY_A);

    foreach ($template_counts_results as $result) {
        if (isset($template_counts[$result['page_template']])) {
            $template_counts[$result['page_template']] = $result['count'];
        }
    }
    
    return [
        'templates' => $page_templates,
        'counts' => $template_counts
    ];
}

function wp_content_inventory_plugin_page() {
    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'wp-content-inventory'));
    }
    
    global $wpdb;

    // Get all public post types
    $post_types = get_post_types(['public' => true], 'objects');
    
    // Handle post type filter
    $post_type_filter = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'page';
    
    // Get page templates and counts (only applies to 'page' post type)
    $page_templates = array();
    $template_counts = array();
    if ($post_type_filter === 'page') {
        $template_data = wp_content_inventory_get_template_counts();
        $page_templates = $template_data['templates'];
        $template_counts = $template_data['counts'];
    }

    // Get taxonomies for the selected post type
    $taxonomies = get_object_taxonomies($post_type_filter, 'objects');
    // Filter out post_tag and post_format taxonomies
    $excluded_taxonomies = array('post_tag', 'post_format');
    $filtered_taxonomies = array();
    foreach ($taxonomies as $taxonomy) {
        if (!in_array($taxonomy->name, $excluded_taxonomies)) {
            $filtered_taxonomies[$taxonomy->name] = $taxonomy;
        }
    }
    $taxonomies = $filtered_taxonomies;
    
    $taxonomy_filter = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : 'All';
    $term_filter = isset($_GET['term']) ? intval($_GET['term']) : 'All';
    
    // Get terms for the selected taxonomy
    $terms = array();
    if ($taxonomy_filter !== 'All') {
        $terms = get_terms([
            'taxonomy' => $taxonomy_filter,
            'hide_empty' => false,
        ]);
    }

    // Get all unique post statuses for the selected post type
    $statuses_query = "
        SELECT DISTINCT post_status AS status
        FROM {$wpdb->posts}
        WHERE post_type = '%s'
        ORDER BY status
    ";
    $statuses = $wpdb->get_results($wpdb->prepare($statuses_query, $post_type_filter), ARRAY_A);
    $statuses = array_merge(array(array('status' => 'All')), $statuses);

    $template_filter = isset($_GET['template']) ? sanitize_text_field($_GET['template']) : 'All';
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'All';

    // Build query based on filters
    $where_clause = $wpdb->prepare("WHERE p.post_type = %s", $post_type_filter);
    
    if ($post_type_filter === 'page' && $template_filter !== 'All') {
        $where_clause .= $wpdb->prepare(" AND COALESCE(pm.meta_value, 'default') = %s", $template_filter);
    }
    
    if ($status_filter !== 'All') {
        $where_clause .= $wpdb->prepare(" AND p.post_status = %s", $status_filter);
    }

    // Base query for all post types
    $query = "
        SELECT
            p.ID,
            p.post_title,
            p.post_name,
            p.post_status AS status
        FROM {$wpdb->posts} p
    ";
    
    // Add page template join if it's a page
    if ($post_type_filter === 'page') {
        $query .= " LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_page_template'";
    }
    
    // Add taxonomy filter conditions if selected
    if ($taxonomy_filter !== 'All') {
        $query .= " 
            LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        ";
        $where_clause .= $wpdb->prepare(" AND tt.taxonomy = %s", $taxonomy_filter);
        
        if ($term_filter !== 'All') {
            $query .= " LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id";
            $where_clause .= $wpdb->prepare(" AND t.term_id = %d", $term_filter);
        }
    }
    
    $query .= " {$where_clause}";
    $query .= " GROUP BY p.ID";
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    // Add additional data to results
    for ($i = 0; $i < count($results); $i++) {
        // Get URL based on post type
        if ($post_type_filter === 'page') {
            $results[$i]['url'] = get_page_link($results[$i]['ID']);
        } else {
            $results[$i]['url'] = get_permalink($results[$i]['ID']);
        }
        
        // Add page template info for pages
        if ($post_type_filter === 'page') {
            $template_meta = get_post_meta($results[$i]['ID'], '_wp_page_template', true);
            $results[$i]['page_template'] = !empty($template_meta) ? $template_meta : 'default';
            $results[$i]['page_template_name'] = isset($page_templates[$results[$i]['page_template']]) ? 
                $page_templates[$results[$i]['page_template']] : 
                __('Unknown Template', 'wp-content-inventory');
        }
        
        // Get taxonomy terms for this post
        $results[$i]['taxonomy_terms'] = array();
        foreach ($taxonomies as $taxonomy) {
            if (!in_array($taxonomy->name, $excluded_taxonomies) && is_object($taxonomy) && isset($taxonomy->name) && isset($taxonomy->label)) {
                $terms = wp_get_post_terms($results[$i]['ID'], $taxonomy->name);
                if (!empty($terms) && !is_wp_error($terms)) {
                    $term_names = array_map(function($term) { return $term->name; }, $terms);
                    $results[$i]['taxonomy_terms'][$taxonomy->label] = implode(', ', $term_names);
                }
            }
        }
    }

    // Calculate total count
    $total_items = count($results);

    // Start outputting the UI
    echo '<div class="content-inventory wrap">';
    echo '<h1>' . esc_html__('Content Inventory', 'wp-content-inventory') . '</h1>';
    
    // Dashboard stats boxes
    echo '<div class="content-inventory-dashboard">';
    
    // Total items box
    echo '<div class="content-inventory-stat-box">';
    echo '<span class="stat-count">' . $total_items . '</span>';
    echo '<span class="stat-label">' . esc_html__('Total Items', 'wp-content-inventory') . '</span>';
    echo '</div>';
    
    // Template overview for pages
    if ($post_type_filter === 'page') {
        // Sort templates by usage count
        arsort($template_counts);
        
        echo '<div class="content-inventory-stat-box template-overview">';
        echo '<span class="stat-label">' . esc_html__('Template Usage', 'wp-content-inventory') . '</span>';
        echo '<ul class="template-stats">';
        
        // Display all templates with their usage counts
        foreach ($template_counts as $template_key => $count) {
            if ($count > 0) {
                $template_name = isset($page_templates[$template_key]) ? $page_templates[$template_key] : __('Unknown', 'wp-content-inventory');
                echo '<li><span class="template-name">' . esc_html($template_name) . ':</span> <span class="template-count">' . intval($count) . '</span></li>';
            }
        }
        
        echo '</ul>';
        echo '</div>';
    }
    
    // Taxonomy counts for posts and custom post types
    elseif ($post_type_filter !== 'attachment' && !empty($taxonomies)) {
        echo '<div class="content-inventory-stat-box taxonomy-overview">';
        echo '<span class="stat-label">' . esc_html__('Taxonomy Usage', 'wp-content-inventory') . '</span>';
        echo '<ul class="taxonomy-stats">';
        
        // Get detailed taxonomy information for each taxonomy
        foreach ($taxonomies as $taxonomy) {
            if (!in_array($taxonomy->name, $excluded_taxonomies) && is_object($taxonomy) && isset($taxonomy->name) && isset($taxonomy->label)) {
                // Get terms for this taxonomy with post counts
                $terms = get_terms([
                    'taxonomy' => $taxonomy->name,
                    'hide_empty' => true
                ]);
                
                if (!is_wp_error($terms) && !empty($terms)) {
                    // Count posts that have this taxonomy assigned
                    $assignment_count_query = $wpdb->prepare("
                        SELECT COUNT(DISTINCT p.ID) as count
                        FROM {$wpdb->posts} p
                        JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        WHERE p.post_type = %s AND tt.taxonomy = %s
                    ", $post_type_filter, $taxonomy->name);
                    
                    $assignment_count = $wpdb->get_var($assignment_count_query);
                    
                    // Get term names as a list
                    $term_names = [];
                    foreach ($terms as $term) {
                        $term_names[] = $term->name;
                    }
                    
                    echo '<li>';
                    echo '<div class="taxonomy-header">';
                    echo '<span class="taxonomy-name">' . esc_html($taxonomy->label) . '</span>';
                    echo '<span class="taxonomy-count">' . intval($assignment_count) . ' ' . esc_html__('posts', 'wp-content-inventory') . '</span>';
                    echo '</div>';
                    echo '<div class="taxonomy-terms-list">' . esc_html(implode(', ', $term_names)) . '</div>';
                    echo '</li>';
                }
            }
        }
        
        echo '</ul>';
        echo '</div>';
    }
    
    // Media stats for attachments
    elseif ($post_type_filter === 'attachment') {
        global $wpdb;
        
        // Get file extension distribution
        $file_extensions_query = "
            SELECT 
                SUBSTRING_INDEX(post_mime_type, '/', -1) as file_extension,
                COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            GROUP BY file_extension
            ORDER BY count DESC
        ";
        
        $file_extensions = $wpdb->get_results($file_extensions_query);
        
        // Calculate total size
        $total_size = 0;
        $size_query = $wpdb->prepare("
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'attachment'
            LIMIT 1000
        ");
        $attachment_ids = $wpdb->get_col($size_query);
        
        foreach ($attachment_ids as $attachment_id) {
            $file_path = get_attached_file($attachment_id);
            if (file_exists($file_path)) {
                $total_size += filesize($file_path);
            }
        }
        
        // Format file size
        $formatted_size = wp_content_inventory_format_file_size($total_size);
        
        // Media size stats
        echo '<div class="content-inventory-stat-box media-overview">';
        echo '<span class="stat-label">' . esc_html__('Media Overview', 'wp-content-inventory') . '</span>';
        
        // Show total size prominently
        echo '<div class="total-media-size">';
        echo '<span class="size-value">' . esc_html($formatted_size) . '</span>';
        echo '<span class="size-label">' . esc_html__('Total Size', 'wp-content-inventory') . '</span>';
        echo '</div>';
        
        echo '<ul class="media-stats">';
        
        // Display file extensions and counts
        foreach ($file_extensions as $ext) {
            if (!empty($ext->file_extension)) {
                echo '<li><span class="file-type">' . esc_html($ext->file_extension) . ':</span> <span class="file-count">' . intval($ext->count) . '</span></li>';
            }
        }
        
        echo '</ul>';
        echo '</div>';
    }
    
    // Add template stats for pages
    if ($post_type_filter === 'page' && $template_filter !== 'All') {
        echo '<div class="content-inventory-stat-box">';
        echo '<span class="stat-text">' . esc_html($page_templates[$template_filter]) . '</span>';
        echo '<span class="stat-label">' . esc_html__('Template', 'wp-content-inventory') . '</span>';
        echo '</div>';
    }
    
    // Add taxonomy stats if filtered
    if ($taxonomy_filter !== 'All') {
        echo '<div class="content-inventory-stat-box">';
        // Check if the taxonomy exists in the array before accessing its properties
        if (isset($taxonomies[$taxonomy_filter]) && is_object($taxonomies[$taxonomy_filter])) {
            echo '<span class="stat-text">' . esc_html($taxonomies[$taxonomy_filter]->label) . '</span>';
        } else {
            echo '<span class="stat-text">' . esc_html($taxonomy_filter) . '</span>';
        }
        echo '<span class="stat-label">' . esc_html__('Taxonomy', 'wp-content-inventory') . '</span>';
        echo '</div>';
    }
    
    echo '</div>'; // End dashboard stats
    
    // Filter form with card styling
    echo '<div class="content-inventory-card">';
    echo '<h2 class="content-inventory-card-header">' . esc_html__('Filter Content', 'wp-content-inventory') . '</h2>';
    echo '<div class="content-inventory-card-body">';
    
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="wp-content-inventory">';
    
    echo '<div class="content-inventory-filters">';
    
    // Post Type filter
    echo '<div class="content-inventory-filter-group">';
    echo '<label for="post_type">' . esc_html__('Post Type:', 'wp-content-inventory') . '</label>';
    echo '<select id="post_type" name="post_type" class="content-inventory-select" onchange="this.form.submit();">';
    foreach ($post_types as $type) {
        $selected = ($type->name === $post_type_filter) ? 'selected' : '';
        echo '<option value="' . esc_attr($type->name) . '" ' . $selected . '>' . esc_html($type->label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    
    // Taxonomy filter (only show if post type has taxonomies)
    if (!empty($taxonomies)) {
        echo '<div class="content-inventory-filter-group">';
        echo '<label for="taxonomy">' . esc_html__('Taxonomy:', 'wp-content-inventory') . '</label>';
        echo '<select id="taxonomy" name="taxonomy" class="content-inventory-select" onchange="this.form.submit();">';
        echo '<option value="All">' . esc_html__('All', 'wp-content-inventory') . '</option>';
        foreach ($taxonomies as $taxonomy) {
            if (!in_array($taxonomy->name, $excluded_taxonomies) && is_object($taxonomy) && isset($taxonomy->name) && isset($taxonomy->label)) {
                $selected = ($taxonomy->name === $taxonomy_filter) ? 'selected' : '';
                echo '<option value="' . esc_attr($taxonomy->name) . '" ' . $selected . '>' . esc_html($taxonomy->label) . '</option>';
            }
        }
        echo '</select>';
        echo '</div>';
    }
    
    // Term filter (only show if taxonomy is selected)
    if ($taxonomy_filter !== 'All' && !empty($terms)) {
        echo '<div class="content-inventory-filter-group">';
        echo '<label for="term">' . esc_html__('Term:', 'wp-content-inventory') . '</label>';
        echo '<select id="term" name="term" class="content-inventory-select" onchange="this.form.submit();">';
        echo '<option value="All">' . esc_html__('All', 'wp-content-inventory') . '</option>';
        foreach ($terms as $term) {
            $selected = ($term->term_id == $term_filter) ? 'selected' : '';
            echo '<option value="' . esc_attr($term->term_id) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }
    
    // Status filter
    echo '<div class="content-inventory-filter-group">';
    echo '<label for="status">' . esc_html__('Status:', 'wp-content-inventory') . '</label>';
    echo '<select id="status" name="status" class="content-inventory-select" onchange="this.form.submit();">';
    foreach ($statuses as $status) {
        $selected = ($status['status'] === $status_filter) ? 'selected' : '';
        echo '<option value="' . esc_attr($status['status']) . '" ' . $selected . '>' . esc_html($status['status']) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    
    // Template filter (only show for pages)
    if ($post_type_filter === 'page') {
        echo '<div class="content-inventory-filter-group">';
        echo '<label for="template">' . esc_html__('Template:', 'wp-content-inventory') . '</label>';
        echo '<select id="template" name="template" class="content-inventory-select" onchange="this.form.submit();">';
        echo '<option value="All">' . esc_html__('All', 'wp-content-inventory') . '</option>';
        foreach ($page_templates as $template_key => $template_name) {
            $selected = ($template_key === $template_filter) ? 'selected' : '';
            echo '<option value="' . esc_attr($template_key) . '" ' . $selected . '>' . esc_html($template_name) . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }
    
    echo '</div>'; // End filters container
    echo '</form>';
    echo '</div>'; // End card body
    echo '</div>'; // End filter card

    // Results section
    echo '<div class="content-inventory-card">';
    echo '<div class="content-inventory-card-header-with-actions">';
    echo '<h2 class="content-inventory-card-title">' . esc_html__('Content Results', 'wp-content-inventory') . '</h2>';
    
    // Export button
    wp_content_inventory_export_button($post_type_filter, $taxonomy_filter, $term_filter, $status_filter, $template_filter);
    
    echo '</div>'; // End card header with actions
    echo '<div class="content-inventory-card-body">';

    // Results table
    echo '<div class="content-inventory-table-wrap">';
    echo '<table class="content-inventory-table widefat">';
    echo '<thead>';
    echo '<tr>';
    echo '<th scope="col">' . esc_html__('ID', 'wp-content-inventory') . '</th>';
    echo '<th scope="col">' . esc_html__('Title', 'wp-content-inventory') . '</th>';
    echo '<th scope="col">' . esc_html__('URL', 'wp-content-inventory') . '</th>';
    echo '<th scope="col">' . esc_html__('Post Name', 'wp-content-inventory') . '</th>';
    echo '<th scope="col">' . esc_html__('Status', 'wp-content-inventory') . '</th>';
    
    // Add template column for pages
    if ($post_type_filter === 'page') {
        echo '<th scope="col">' . esc_html__('Template', 'wp-content-inventory') . '</th>';
    }
    
    // Add taxonomy columns
    foreach ($taxonomies as $taxonomy) {
        if (!in_array($taxonomy->name, $excluded_taxonomies) && is_object($taxonomy) && isset($taxonomy->name) && isset($taxonomy->label)) {
            echo '<th scope="col">' . esc_html($taxonomy->label) . '</th>';
        }
    }
    
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    if (empty($results)) {
        echo '<tr><td colspan="' . (5 + count($taxonomies) + ($post_type_filter === 'page' ? 1 : 0)) . '" class="content-inventory-no-results">';
        echo esc_html__('No results found matching your criteria.', 'wp-content-inventory');
        echo '</td></tr>';
    } else {
        foreach ($results as $result) {
            echo '<tr>';
            echo '<td>' . intval($result['ID']) . '</td>';
            echo '<td><strong>' . esc_html($result['post_title']) . '</strong></td>';
            echo '<td><a target="_blank" href="' . esc_url($result['url']) . '">' . esc_url($result['url']) . '</a></td>';
            echo '<td>' . esc_html($result['post_name']) . '</td>';
            echo '<td><span class="content-inventory-status ' . esc_attr(strtolower($result['status'])) . '">' . esc_html($result['status']) . '</span></td>';
            
            // Add template column for pages
            if ($post_type_filter === 'page') {
                echo '<td>' . esc_html($result['page_template_name']) . '</td>';
            }
            
            // Add taxonomy columns
            foreach ($taxonomies as $taxonomy) {
                if (!in_array($taxonomy->name, $excluded_taxonomies) && is_object($taxonomy) && isset($taxonomy->name) && isset($taxonomy->label)) {
                    echo '<td>';
                    if (isset($result['taxonomy_terms'][$taxonomy->label])) {
                        echo '<span class="content-inventory-taxonomy-terms">' . esc_html($result['taxonomy_terms'][$taxonomy->label]) . '</span>';
                    } else {
                        echo '<span class="content-inventory-empty-term">—</span>';
                    }
                    echo '</td>';
                }
            }
            
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>'; // End table wrap
    
    echo '</div>'; // End card body
    echo '</div>'; // End results card
    
    echo '</div>'; // Close the 'wrap' div
}

// Register admin_post actions for CSV export
add_action('admin_init', 'wp_content_inventory_admin_init');

function wp_content_inventory_admin_init() {
    add_action('admin_post_export_content_inventory', 'wp_content_inventory_process_export');
}

// Update the export button in the UI
function wp_content_inventory_export_button($post_type_filter, $taxonomy_filter, $term_filter, $status_filter, $template_filter) {
    // Export button
    echo '<form method="post" action="' . admin_url('admin-post.php') . '" class="export-form">';
    echo '<input type="hidden" name="action" value="export_content_inventory">';
    echo wp_nonce_field('wp_content_inventory_export', 'wp_content_inventory_nonce', true, false);
    echo '<input type="hidden" name="post_type" value="' . esc_attr($post_type_filter) . '">';
    if ($taxonomy_filter !== 'All') {
        echo '<input type="hidden" name="taxonomy" value="' . esc_attr($taxonomy_filter) . '">';
    }
    if ($term_filter !== 'All') {
        echo '<input type="hidden" name="term" value="' . esc_attr($term_filter) . '">';
    }
    if ($status_filter !== 'All') {
        echo '<input type="hidden" name="status" value="' . esc_attr($status_filter) . '">';
    }
    if ($post_type_filter === 'page' && isset($template_filter) && $template_filter !== 'All') {
        echo '<input type="hidden" name="template" value="' . esc_attr($template_filter) . '">';
    }
    echo '<input type="submit" class="button button-secondary" value="' . esc_attr__('Export to CSV', 'wp-content-inventory') . '">';
    echo '</form>';
}

// Process the export action
function wp_content_inventory_process_export() {
    // Check capabilities and nonce
    if (!current_user_can('manage_options') || 
        !isset($_POST['wp_content_inventory_nonce']) || 
        !wp_verify_nonce($_POST['wp_content_inventory_nonce'], 'wp_content_inventory_export')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'wp-content-inventory'));
    }
    
    $post_type_filter = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'page';
    $taxonomy_filter = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : 'All';
    $term_filter = isset($_POST['term']) ? intval($_POST['term']) : 'All';
    $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'All';
    $template_filter = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : 'All';
    
    // Get the data for export
    $export_data = wp_content_inventory_get_data_for_export($post_type_filter, $taxonomy_filter, $term_filter, $status_filter, $template_filter);
    
    // Create the CSV file
    $filename = sanitize_file_name($post_type_filter . '_inventory_' . date('Y-m-d') . '.csv');
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/' . $filename;
    
    $fp = fopen($file_path, 'w');
    
    // Build headers based on post type
    $headers = array(
        __('URL', 'wp-content-inventory'),
        __('ID', 'wp-content-inventory'),
        __('Title', 'wp-content-inventory'),
        __('Post Name', 'wp-content-inventory'),
        __('Status', 'wp-content-inventory')
    );
    
    // Add template header for pages
    if ($post_type_filter === 'page') {
        $headers[] = __('Template', 'wp-content-inventory');
    }
    
    // Add taxonomy headers
    foreach ($export_data['taxonomies'] as $taxonomy) {
        if (!in_array($taxonomy->name, $export_data['excluded_taxonomies'])) {
            $headers[] = $taxonomy->label;
        }
    }
    
    fputcsv($fp, $headers);
    
    foreach ($export_data['results'] as $row) {
        $row_data = array(
            $row['url'],
            $row['ID'],
            str_replace(array("\r", "\n"), array('', ''), $row['post_title']),
            str_replace(array("\r", "\n"), array('', ''), $row['post_name']),
            $row['status']
        );
        
        // Add template data for pages
        if ($post_type_filter === 'page') {
            $row_data[] = str_replace(array("\r", "\n"), array('', ''), $row['page_template_name']);
        }
        
        // Add taxonomy data
        foreach ($export_data['taxonomies'] as $taxonomy) {
            if (!in_array($taxonomy->name, $export_data['excluded_taxonomies']) && is_object($taxonomy) && isset($taxonomy->name) && isset($taxonomy->label)) {
                if (isset($row['taxonomy_terms'][$taxonomy->label])) {
                    $row_data[] = str_replace(array("\r", "\n"), array('', ''), $row['taxonomy_terms'][$taxonomy->label]);
                } else {
                    $row_data[] = '';
                }
            }
        }
        
        fputcsv($fp, $row_data);
    }
    
    fclose($fp);
    
    // Deliver the file to the browser using WordPress functions
    if (file_exists($file_path)) {
        // Set necessary headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file_path));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        ob_clean(); // Clean output buffer
        flush(); // Flush system output buffer
        readfile($file_path);
        unlink($file_path); // Delete the file after sending
        exit;
    } else {
        wp_die(__('Error creating export file.', 'wp-content-inventory'));
    }
}

/**
 * Get data for export based on filters
 */
function wp_content_inventory_get_data_for_export($post_type_filter, $taxonomy_filter, $term_filter, $status_filter, $template_filter) {
    global $wpdb;
    
    // Get taxonomies for the selected post type
    $taxonomies = get_object_taxonomies($post_type_filter, 'objects');
    // Filter out post_tag and post_format taxonomies
    $excluded_taxonomies = array('post_tag', 'post_format');
    $filtered_taxonomies = array();
    foreach ($taxonomies as $taxonomy) {
        if (!in_array($taxonomy->name, $excluded_taxonomies)) {
            $filtered_taxonomies[$taxonomy->name] = $taxonomy;
        }
    }
    $taxonomies = $filtered_taxonomies;
    
    // Get page templates (only applies to 'page' post type)
    $page_templates = array();
    if ($post_type_filter === 'page') {
        $template_data = wp_content_inventory_get_template_counts();
        $page_templates = $template_data['templates'];
    }
    
    // Build query based on filters
    $where_clause = $wpdb->prepare("WHERE p.post_type = %s", $post_type_filter);
    
    if ($post_type_filter === 'page' && $template_filter !== 'All') {
        $where_clause .= $wpdb->prepare(" AND COALESCE(pm.meta_value, 'default') = %s", $template_filter);
    }
    
    if ($status_filter !== 'All') {
        $where_clause .= $wpdb->prepare(" AND p.post_status = %s", $status_filter);
    }

    // Base query for all post types
    $query = "
        SELECT
            p.ID,
            p.post_title,
            p.post_name,
            p.post_status AS status
        FROM {$wpdb->posts} p
    ";
    
    // Add page template join if it's a page
    if ($post_type_filter === 'page') {
        $query .= " LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_page_template'";
    }
    
    // Add taxonomy filter conditions if selected
    if ($taxonomy_filter !== 'All') {
        $query .= " 
            LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        ";
        $where_clause .= $wpdb->prepare(" AND tt.taxonomy = %s", $taxonomy_filter);
        
        if ($term_filter !== 'All') {
            $query .= " LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id";
            $where_clause .= $wpdb->prepare(" AND t.term_id = %d", $term_filter);
        }
    }
    
    $query .= " {$where_clause}";
    $query .= " GROUP BY p.ID";
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    // Add additional data to results
    for ($i = 0; $i < count($results); $i++) {
        // Get URL based on post type
        if ($post_type_filter === 'page') {
            $results[$i]['url'] = get_page_link($results[$i]['ID']);
        } else {
            $results[$i]['url'] = get_permalink($results[$i]['ID']);
        }
        
        // Add page template info for pages
        if ($post_type_filter === 'page') {
            $template_meta = get_post_meta($results[$i]['ID'], '_wp_page_template', true);
            $results[$i]['page_template'] = !empty($template_meta) ? $template_meta : 'default';
            $results[$i]['page_template_name'] = isset($page_templates[$results[$i]['page_template']]) ? 
                $page_templates[$results[$i]['page_template']] : 
                __('Unknown Template', 'wp-content-inventory');
        }
        
        // Get taxonomy terms for this post
        $results[$i]['taxonomy_terms'] = array();
        foreach ($taxonomies as $taxonomy) {
            if (!in_array($taxonomy->name, $excluded_taxonomies) && is_object($taxonomy) && isset($taxonomy->name) && isset($taxonomy->label)) {
                $terms = wp_get_post_terms($results[$i]['ID'], $taxonomy->name);
                if (!empty($terms) && !is_wp_error($terms)) {
                    $term_names = array_map(function($term) { return $term->name; }, $terms);
                    $results[$i]['taxonomy_terms'][$taxonomy->label] = implode(', ', $term_names);
                }
            }
        }
    }
    
    return array(
        'results' => $results,
        'taxonomies' => $taxonomies,
        'excluded_taxonomies' => $excluded_taxonomies
    );
}

/**
 * Format a file size in bytes to a human-readable format
 */
function wp_content_inventory_format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
