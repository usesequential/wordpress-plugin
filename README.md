# Sequential for WordPress

A WordPress plugin that adds the [Sequential](https://www.usesequential.com) tag to every front-end page: session replay and product analytics for your WordPress site.

The plugin is in [`sequential-analytics/`](sequential-analytics). Its `readme.txt` describes what it does, the external service it connects to, and the FAQ.

## What it does

- Adds a **Settings → Sequential** page, where you enter your project key, the ingest host and an optional release.
- Prints the Sequential install snippet in the page head, with your saved settings.
- Prints nothing until you save a valid project key (it starts with `sq_sequential_`).
- Can skip the tag for logged-in administrators, so your own visits are not recorded. This is on by default.
- Does not collect data of its own, and does not contact Sequential from your WordPress admin.

## Install from this repository

1. Copy the `sequential-analytics` folder to `wp-content/plugins/`, or zip it and upload it in **Plugins → Add New → Upload Plugin**.
2. Activate **Sequential – Session Replay & Product Analytics**.
3. In Sequential, open **Settings → Projects → your project → Setup** and copy the `data-key` value from the install snippet.
4. In WordPress, open **Settings → Sequential**, paste the key into **Project key** and save.

Requires WordPress 5.7 or later and PHP 7.4 or later.

## Tests

`tests/smoke.sh` runs the plugin in a real WordPress with MariaDB in Docker, with no internet access for the test stack:

```sh
tests/smoke.sh                  # WordPress 6.8 (default image tag 6.8-php8.3-apache)
tests/smoke.sh php8.3-apache    # the latest WordPress image
```

Coding standards: `phpcs.xml.dist` (WordPress Coding Standards).

Questions: hello@usesequential.com

## License

GPL-2.0-or-later, like WordPress. See [LICENSE](LICENSE).
