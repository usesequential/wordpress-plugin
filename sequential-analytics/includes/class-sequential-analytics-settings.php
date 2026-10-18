<?php
/**
 * The Settings > Sequential page.
 *
 * @package SequentialAnalytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the setting, its fields and the options page.
 */
final class Sequential_Analytics_Settings {

	/**
	 * Slug of the options page and of its settings group.
	 */
	const PAGE = 'sequential-analytics';

	/**
	 * Capability needed to see the page and to save the settings.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * What the sanitize callback last returned during this request.
	 *
	 * On the first save, WordPress runs the sanitize callback twice: update_option()
	 * hands the already-sanitized value to add_option(), which sanitizes it again.
	 * After a rejected key, that second pass would see an empty key and add a wrong
	 * "Enter a project key" error. Returning the second pass unchanged prevents that.
	 *
	 * @var array|null
	 */
	private static $last_output = null;

	/**
	 * Hooks the page into WordPress.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SEQUENTIAL_ANALYTICS_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Registers the option, the section and the fields.
	 */
	public static function register() {
		register_setting(
			self::PAGE,
			Sequential_Analytics_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => Sequential_Analytics_Options::defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'sequential_analytics_main',
			__( 'Tag settings', 'sequential-analytics' ),
			array( __CLASS__, 'render_section' ),
			self::PAGE
		);

		add_settings_field(
			'sequential_analytics_key',
			__( 'Project key', 'sequential-analytics' ),
			array( __CLASS__, 'render_key_field' ),
			self::PAGE,
			'sequential_analytics_main',
			array( 'label_for' => 'sequential_analytics_key' )
		);

		add_settings_field(
			'sequential_analytics_api',
			__( 'Ingest host', 'sequential-analytics' ),
			array( __CLASS__, 'render_api_field' ),
			self::PAGE,
			'sequential_analytics_main',
			array( 'label_for' => 'sequential_analytics_api' )
		);

		add_settings_field(
			'sequential_analytics_release',
			__( 'Release (optional)', 'sequential-analytics' ),
			array( __CLASS__, 'render_release_field' ),
			self::PAGE,
			'sequential_analytics_main',
			array( 'label_for' => 'sequential_analytics_release' )
		);

		add_settings_field(
			'sequential_analytics_skip_admins',
			__( 'Administrators', 'sequential-analytics' ),
			array( __CLASS__, 'render_skip_admins_field' ),
			self::PAGE,
			'sequential_analytics_main',
			array( 'label_for' => 'sequential_analytics_skip_admins' )
		);
	}

	/**
	 * Suggests text for the site's privacy policy (Settings > Privacy > Policy Guide).
	 */
	public static function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p class="privacy-policy-tutorial">%1$s</p><p>%2$s</p><p>%3$s</p>',
			esc_html__( 'This site uses Sequential for session replay and product analytics. Change the suggested text to match the data your site sends.', 'sequential-analytics' ),
			esc_html__( 'We use Sequential to record how visitors use this site and to replay those sessions. When you visit a page, your browser loads a script from cdn.usesequential.com and sends data about your visit, such as the pages you view and how you interact with them, to Sequential.', 'sequential-analytics' ),
			sprintf(
				/* translators: %s: link to the Sequential privacy features page. */
				esc_html__( 'To learn how Sequential handles this data, see %s.', 'sequential-analytics' ),
				'<a href="' . esc_url( 'https://www.usesequential.com/privacy-features' ) . '">' . esc_html__( 'Sequential privacy features', 'sequential-analytics' ) . '</a>'
			)
		);

		wp_add_privacy_policy_content( __( 'Sequential', 'sequential-analytics' ), wp_kses_post( $content ) );
	}

	/**
	 * Adds Settings > Sequential.
	 */
	public static function add_page() {
		add_options_page(
			__( 'Sequential', 'sequential-analytics' ),
			__( 'Sequential', 'sequential-analytics' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Adds a "Settings" link to the plugin's row on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift(
			$links,
			sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'Settings', 'sequential-analytics' ) )
		);

		return $links;
	}

	/**
	 * Validates the submitted settings.
	 *
	 * A rejected key or ingest host keeps the value saved before, and the page shows why.
	 *
	 * @param mixed $input Submitted values.
	 * @return array{key: string, api: string, release: string, skip_admins: bool}
	 */
	public static function sanitize( $input ) {
		if ( null !== self::$last_output && $input === self::$last_output ) {
			return $input;
		}

		$previous = Sequential_Analytics_Options::get();
		if ( ! is_array( $input ) ) {
			return $previous;
		}

		$output = $previous;

		$key = isset( $input['key'] ) && is_string( $input['key'] ) ? trim( $input['key'] ) : '';
		if ( '' === $key ) {
			$output['key'] = '';
			self::report(
				'sequential_analytics_key_missing',
				__( 'Enter a project key. Without a key, the plugin adds nothing to your pages.', 'sequential-analytics' )
			);
		} elseif ( Sequential_Analytics_Options::is_valid_key( $key ) ) {
			$output['key'] = $key;
		} else {
			self::report(
				'sequential_analytics_key_invalid',
				__( 'The project key was not saved. A project key starts with sq_sequential_, followed by letters, digits, - or _.', 'sequential-analytics' )
			);
		}

		$api_input = isset( $input['api'] ) && is_string( $input['api'] ) ? trim( $input['api'] ) : '';
		if ( '' === $api_input ) {
			$output['api'] = Sequential_Analytics_Options::DEFAULT_API;
		} else {
			$api = Sequential_Analytics_Options::normalize_api( $api_input );
			if ( '' !== $api ) {
				$output['api'] = $api;
			} else {
				self::report(
					'sequential_analytics_api_invalid',
					__( 'The ingest host was not saved. Enter an https:// address, for example https://api.usesequential.com.', 'sequential-analytics' )
				);
			}
		}

		$output['release'] = isset( $input['release'] )
			? Sequential_Analytics_Options::sanitize_release( $input['release'] )
			: '';

		$output['skip_admins'] = ! empty( $input['skip_admins'] );

		self::$last_output = $output;

		return $output;
	}

	/**
	 * Adds a settings error for the options page.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message shown to the user.
	 */
	private static function report( $code, $message ) {
		add_settings_error( Sequential_Analytics_Options::OPTION, $code, $message, 'error' );
	}

	/**
	 * Prints the options page.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php self::render_status(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Says whether the tag is on the site's pages now.
	 */
	private static function render_status() {
		$settings = Sequential_Analytics_Options::get();
		if ( Sequential_Analytics_Options::is_valid_key( $settings['key'] ) ) {
			$message = $settings['skip_admins']
				? __( 'The Sequential tag is on your site\'s pages. It is not added for logged-in administrators.', 'sequential-analytics' )
				: __( 'The Sequential tag is on your site\'s pages, for all visitors.', 'sequential-analytics' );
			printf( '<div class="notice notice-success inline"><p>%s</p></div>', esc_html( $message ) );
		} else {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html__( 'The Sequential tag is not on your pages. Enter your project key to add it.', 'sequential-analytics' )
			);
		}
	}

	/**
	 * Prints the text under the section heading.
	 */
	public static function render_section() {
		printf(
			'<p>%1$s <a href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></p>',
			esc_html__( 'The plugin adds the Sequential tag to the head of every front-end page. You can find your project key in Sequential, under Settings, Projects, your project, Setup.', 'sequential-analytics' ),
			esc_url( 'https://www.usesequential.com/docs/install' ),
			esc_html__( 'Read the install guide', 'sequential-analytics' )
		);
	}

	/**
	 * Prints the project key field.
	 */
	public static function render_key_field() {
		$settings = Sequential_Analytics_Options::get();
		printf(
			'<input type="text" id="sequential_analytics_key" name="%1$s[key]" value="%2$s" class="regular-text code" required autocomplete="off" spellcheck="false" placeholder="sq_sequential_..." aria-describedby="sequential_analytics_key_help" />',
			esc_attr( Sequential_Analytics_Options::OPTION ),
			esc_attr( $settings['key'] )
		);
		printf(
			'<p class="description" id="sequential_analytics_key_help">%s</p>',
			esc_html__( 'Required. Your project\'s Sequential ingest key. It starts with sq_sequential_.', 'sequential-analytics' )
		);
	}

	/**
	 * Prints the ingest host field.
	 */
	public static function render_api_field() {
		$settings = Sequential_Analytics_Options::get();
		printf(
			'<input type="url" id="sequential_analytics_api" name="%1$s[api]" value="%2$s" class="regular-text code" placeholder="%3$s" aria-describedby="sequential_analytics_api_help" />',
			esc_attr( Sequential_Analytics_Options::OPTION ),
			esc_attr( $settings['api'] ),
			esc_attr( Sequential_Analytics_Options::DEFAULT_API )
		);
		printf(
			'<p class="description" id="sequential_analytics_api_help">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: the default ingest host URL. */
					__( 'Where the tag sends data. It must be an https:// address. Keep %s unless Sequential gave you another host.', 'sequential-analytics' ),
					Sequential_Analytics_Options::DEFAULT_API
				)
			)
		);
	}

	/**
	 * Prints the release field.
	 */
	public static function render_release_field() {
		$settings = Sequential_Analytics_Options::get();
		printf(
			'<input type="text" id="sequential_analytics_release" name="%1$s[release]" value="%2$s" class="regular-text" maxlength="%3$d" aria-describedby="sequential_analytics_release_help" />',
			esc_attr( Sequential_Analytics_Options::OPTION ),
			esc_attr( $settings['release'] ),
			(int) Sequential_Analytics_Options::RELEASE_MAX_LENGTH
		);
		printf(
			'<p class="description" id="sequential_analytics_release_help">%s</p>',
			esc_html__( 'Optional. Your site\'s version, for example 2.4.1. The tag puts it on every event. Leave it empty if you do not need it.', 'sequential-analytics' )
		);
	}

	/**
	 * Prints the administrators toggle.
	 */
	public static function render_skip_admins_field() {
		$settings = Sequential_Analytics_Options::get();
		printf(
			'<label for="sequential_analytics_skip_admins"><input type="checkbox" id="sequential_analytics_skip_admins" name="%1$s[skip_admins]" value="1" %2$s /> %3$s</label>',
			esc_attr( Sequential_Analytics_Options::OPTION ),
			checked( $settings['skip_admins'], true, false ),
			esc_html__( 'Do not add the tag for logged-in administrators', 'sequential-analytics' )
		);
	}
}
