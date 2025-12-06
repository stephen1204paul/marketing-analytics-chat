# Marketing Analytics MCP - WordPress Plugin

This is a clean development repository for the WordPress MCP Marketing Analytics Plugin.

## Setup

1. Install PHP dependencies:
```bash
composer install
```

2. Build the plugin:
```bash
./build-plugin-optimized.sh
```

This will create a distributable zip file in the `dist/` directory.

## Development

- Main plugin file: `marketing-analytics-chat.php`
- Core classes: `includes/`
- Admin UI: `admin/`
- Documentation: `docs/`

## Testing

Install the plugin in a WordPress installation:
- Copy to `wp-content/plugins/marketing-analytics-chat/`
- Or install the zip from `dist/` via WordPress admin

## Documentation

- `README.md` - Plugin documentation
- `CLAUDE.md` - AI assistant instructions
- `CHANGELOG.md` - Version history
- `docs/` - Detailed documentation

## Building

Two build scripts are available:

1. `./build-plugin.sh` - Standard build
2. `./build-plugin-optimized.sh` - Optimized build with vendor optimization

Both create a distributable zip in `dist/`.
