<?php
/**
 * Uninstall Content Inventory Plugin
 *
 * @package Content Inventory
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Currently, the plugin doesn't create any database entries, tables, or options
// This file is included for future development and to follow WordPress.org guidelines 