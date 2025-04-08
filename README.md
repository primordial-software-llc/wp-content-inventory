# WP Content Inventory

A comprehensive WordPress plugin for displaying and analyzing all pages, posts, and custom post types in your WordPress site.

## Description

WP Content Inventory gives site administrators a powerful way to audit and inventory all content on their WordPress site. It provides a clean, modern interface for reviewing pages, posts, and custom post types along with their templates, taxonomies, and other metadata.

### Features

- View a comprehensive list of all your WordPress content in one place
- Filter content by post type, taxonomy, term, status, and template
- See stats and counts for templates and taxonomies
- Export your content inventory to CSV for further analysis
- Clean, modern UI that integrates with the WordPress admin
- Responsive design that works on all screen sizes

## Installation

### Automatic Installation

1. Log in to your WordPress admin panel
2. Go to Plugins > Add New
3. Search for "WP Content Inventory"
4. Click "Install Now" and then "Activate"

### Manual Installation

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Go to Plugins > Add New > Upload Plugin
4. Upload the ZIP file and click "Install Now"
5. Activate the plugin

### From Source

1. Clone the repository: `git clone https://github.com/primordial-software/wp-content-inventory.git`
2. Rename the folder to `wp-content-inventory`
3. Upload the folder to your `/wp-content/plugins/` directory
4. Activate the plugin through the 'Plugins' menu in WordPress

## Usage

1. After activation, go to your WordPress admin panel
2. Click on "Content Inventory" in the main menu
3. Use the filters to narrow down content by:
   - Post Type
   - Taxonomy
   - Term
   - Status
   - Template (for pages)
4. View the results in the table below
5. Export the filtered results to CSV for external analysis

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## Development

### Setting up a test environment

The plugin includes a PowerShell script `setup-test-site.ps1` to help you set up a local test environment using IIS:

1. Make sure you have IIS and PHP installed on your Windows machine
2. Run PowerShell as Administrator
3. Navigate to the plugin directory
4. Run `.\setup-test-site.ps1`
5. Follow the instructions to complete the setup

### CSS Customization

The plugin uses a modern card-based UI with styles defined in `css/content-inventory.css`. You can customize the appearance by modifying this file.

## License

This project is licensed under the GPL-2.0+ License - see the LICENSE file for details.

## Credits

Developed by [Primordial Software LLC](https://www.primordial-software.com/)