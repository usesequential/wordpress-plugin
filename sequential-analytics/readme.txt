=== Sequential – Session Replay & Product Analytics ===
Contributors: usesequential
Tags: session replay, analytics, product analytics, session recording, user behavior
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds the Sequential tag to your site: session replay and product analytics for your WordPress site.

== Description ==

[Sequential](https://www.usesequential.com) records how people use your site and replays their sessions. It also gives you product analytics, and it ranks the problems that your visitors hit, with the sessions that show each one. Sequential is a hosted preview.

This plugin adds the Sequential tag to the head of every front-end page of your WordPress site. You do not edit theme files.

What the plugin does:

* Adds a Settings > Sequential page, where you enter your project key.
* Prints the Sequential install snippet in the page head, with your saved settings.
* Prints nothing until you save a valid project key.
* Can skip the tag for logged-in administrators, so your own visits are not recorded. This is on by default.

What the plugin does not do:

* It does not collect data of its own and does not contact any server from your WordPress admin.
* It does not change your content or your theme.

You need a Sequential account and a project. See the [install guide](https://www.usesequential.com/docs/install) and the [session replay guide](https://www.usesequential.com/docs/session-replay).

= External service =

This plugin connects your site to Sequential, an external service. Read the External services section below before you activate it.

== Installation ==

1. Install and activate the plugin from Plugins > Add New, or upload the `sequential-analytics` folder to `/wp-content/plugins/`.
2. In Sequential, open Settings, then Projects, then your project, then Setup. Copy the `data-key` value from the install snippet. It starts with `sq_sequential_`.
3. In WordPress, go to Settings > Sequential and paste the key into Project key.
4. Keep the ingest host at `https://api.usesequential.com` unless Sequential gave you another host.
5. Optional: enter your site's version in Release.
6. Click Save Changes. Open your site in a private window, then look for the session in Sequential.

== Frequently Asked Questions ==

= Where do I find my project key? =

In Sequential, open Settings, then Projects, then your project, then Setup. It is the `data-key` value in the install snippet, and it starts with `sq_sequential_`. The plugin does not save a key in a different format.

= Why is nothing recorded when I visit my own site? =

The setting "Do not add the tag for logged-in administrators" is on by default. Log out, or use a private browser window, to test. You can also turn the setting off.

= What is the ingest host? =

It is the address where the Sequential tag sends data. For the hosted service it is `https://api.usesequential.com`. The plugin accepts only `https://` addresses.

= What does the Release field do? =

The tag puts this value on every event, so you can compare sessions from different versions of your site. It is optional.

= Can I call the Sequential API from my theme or other plugins? =

Yes. The tag defines `window.sequential` with these methods: `capture`, `identify`, `account`, `register`, `unregister`, `reset`, `optOut`, `optIn`, `captureException` and `flush`. Calls made before the SDK file loads are queued and run when it loads. See the [Sequential docs](https://www.usesequential.com/docs).

= I use a caching or optimization plugin. Do I need to change anything? =

If that plugin combines, defers or delays scripts, exclude the Sequential tag from it: the inline script that defines `window.sequential` and the script from `cdn.usesequential.com`. After a change, clear the page cache so visitors get the new head.

= Does the plugin ask visitors for consent? =

No. The plugin does not show a consent banner. If your site needs consent before recording, use your consent tool to control the tag, or call `sequential.optOut()` and `sequential.optIn()`. You are responsible for telling your visitors what your site collects.

= Where can I get help? =

Email hello@usesequential.com.

== External services ==

This plugin connects your site to Sequential, an external service run by Sequential (https://www.usesequential.com). Sequential records sessions for session replay and product analytics. The plugin needs the service: without it, the plugin does nothing.

The plugin adds nothing to your pages until you save a valid project key. After that, every front-end page that it adds the tag to does these things in the visitor's browser:

* It loads the Sequential SDK file from `https://cdn.usesequential.com/sdk/sequential/v1/sequential.js`. The visitor's browser sends the usual request data, such as the IP address and the user agent, to that host.
* The SDK asks the ingest host for your project's settings. Then it sends analytics events, such as page views and clicks, with your project key and the release (if you set one), to that host. You set the ingest host in the plugin; the default is `https://api.usesequential.com`.
* Only when session replay is on for your project in Sequential: the SDK loads the session replay recorder from the ingest host (`<ingest host>/rec/recorder.js/...`), the recorder loads the rrweb recording library from `https://cdn.usesequential.com/sdk/rrweb/...`, and the recorder sends the recorded session to the ingest host. When session replay is off, nothing is recorded for replay.

The plugin itself does not collect data, does not send data from your WordPress server, and does not contact Sequential from your WordPress admin.

To learn how Sequential handles recorded data, read [Sequential privacy features](https://www.usesequential.com/privacy-features). Sequential has not published a privacy policy page or terms of service page yet. Contact hello@usesequential.com with questions.

Tell your visitors about Sequential in your site's privacy policy.

== Changelog ==

= 1.0.0 =
* First release: Settings > Sequential page, and the Sequential tag in the page head.

== Upgrade Notice ==

= 1.0.0 =
First release.
