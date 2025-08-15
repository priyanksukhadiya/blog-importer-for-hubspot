# Blog Importer for HubSpot – HubSpot Blog Importer Plugin for WordPress

A comprehensive **HubSpot blog importer plugin** for WordPress that seamlessly imports, migrates, and manages **HubSpot blog posts** in WordPress with **automatic synchronization** capabilities.

Ideal for anyone looking to **migrate HubSpot blogs to WordPress**, this plugin ensures your content is transferred quickly, cleanly, and without errors.

## Features

- **Easy Setup**: Simple configuration with HubSpot Private App Access Token
- **Flexible Import Options**: Choose post type, status, and import settings
- **Automatic Sync**: Schedule automatic imports using WordPress Cron
- **Manual Import**: Run imports on-demand with a single click
- **Import Statistics**: Track import history and status
- **Content Preservation**: Maintains original publish dates, featured images, and metadata
- **Duplicate Prevention**: Automatically detects and updates existing posts
- **Clean Content**: Removes HubSpot-specific CSS and formatting for clean WordPress integration
- **HubSpot to WordPress Migration**: Quickly transfer your blogs without manual copy-paste
- **SEO-Friendly Imports**: Preserve URLs and meta data for better rankings

## Installation

1. Upload the `blog-importer-for-hubspot` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Blog Importer for HubSpot' in the WordPress admin menu
4. Configure your settings and start importing!

## Configuration

### HubSpot API Key Setup

1. Log in to your HubSpot account
2. Go to Settings > Integrations > Private Apps
3. Create a new Private App or use an existing one
4. Ensure the app has the following scopes:
   - `content`
5. Copy the Access Token and paste it into the plugin settings

### Plugin Settings

- **HubSpot API Key**: Your HubSpot Private App Access Token
- **Import To Post Type**: Choose which WordPress post type to import to (Posts, Pages, Custom Post Types)
- **Post Status on Import**: Set the status for imported posts (Published, Draft, Pending Review, Private)
- **Enable Sync (WP-Cron)**: Enable automatic synchronization
- **Sync Interval**: How often to check for new posts (Hourly, Twice Daily, Daily, Weekly)

## Usage

### Manual Import

1. Configure your HubSpot API Key in the settings
2. Choose your import preferences
3. Click "Run Import Now" to **import all published HubSpot blog posts into WordPress**

### Automatic Sync

1. Enable "Enable Sync (WP-Cron)" in the settings
2. Choose your preferred sync interval
3. The plugin will automatically check for new and updated HubSpot posts based on your schedule

### Import Statistics

The plugin provides detailed statistics including:
- Total number of imported posts
- Last manual import time
- Last automatic import time
- Next scheduled import time
- Auto sync status

## Technical Details

- Works with **HubSpot CMS API v3** for blog retrieval
- Handles pagination for large collections
- Includes proper error handling and logging
- Respects API rate limits
- Built for **HubSpot to WordPress migration** without losing important content details

## Troubleshooting

**Import fails with "Invalid API Key"**
- Verify your HubSpot Private App Access Token
- Ensure the Private App has the required scopes
- Check that the token hasn't expired

**No posts imported**
- Verify you have published blog posts in HubSpot
- Check the WordPress error log for detailed error messages
- Ensure your server can make outbound HTTPS requests

**Cron jobs not running**
- Verify WordPress Cron is working on your site
- Check if any caching plugins are interfering
- Consider using a real cron job instead of WordPress Cron for better reliability

## Security

- Secure API key storage in WordPress database
- Sanitization and validation for all user inputs
- Nonce verification for admin actions
- Capability checks for authorized access

## Uninstallation

When you uninstall the plugin:
- All plugin settings are removed
- Scheduled cron jobs are cleared
- Imported posts remain (optional cleanup available)

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Active HubSpot account with blog posts
- HubSpot Private App with `content` permissions

## Support

Need help? Check the plugin documentation or contact the developer for **HubSpot blog migration support**.

## Changelog

### Version 1.0.0
- Initial release
- HubSpot blog import functionality
- Automatic sync with WordPress Cron
- Import statistics and status tracking
- Comprehensive admin interface

## License

This plugin is licensed under the GPL v2 or later.

---

**Note**: This plugin is not officially affiliated with HubSpot. HubSpot is a trademark of HubSpot, Inc.
