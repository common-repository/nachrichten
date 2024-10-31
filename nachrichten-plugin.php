<?php
	/*
	  Plugin Name: Nachrichten
	  Plugin URI: https://Nachrichten.plus/
	  Description: Mit dem Nachrichten.Plus-Plugin können Sie aktuelle Nachrichten auf Ihren Wordpress-Blog mühelos einfügen und über unser Partnerprogramm zusätzlich mit Premium-Nachrichten Geld verdienen.

	  Author: Nachrichten.plus
	  Version: 1.0.4
	  License: GPLv2 or later
	  Author URI: https://Nachrichten.plus
	  Disclaimer: Use at your own risk. No warranty expressed or implied is provided.
      Text Domain: newsletter
	  Requires at least: 5.0
      Requires PHP: 7.0
	  
	  Copyright 2023 The Nachrichten.plus Team (email: kontakt@nachrichten.plus, web: https://nachrichten.plus)

  Nachrichten is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 2 of the License, or
  any later version.

  Nachrichten is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with Nachrichten. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
	 */

	// Prevent from being run directly.
	defined('ABSPATH') or die("No chance!");

	// Include the fetch_feed functionality (to be replaced eventually).
	include_once( ABSPATH . WPINC . '/feed.php' );

	require_once( plugin_dir_path(__FILE__) . 'nachrichten-plugin-widget.php' );
	require_once( plugin_dir_path(__FILE__) . 'nachrichten-plugin-utils.php' );

  /*
  add_filter( 'the_permalink_rss', 'nachrichten_change_feed_item_url' );  
  function nachrichten_change_feed_item_url( $url ) {
    $parts = parse_url( $url );
    return $parts['scheme'] . '://' . $parts['host'] . $parts['path'] . '/#!view';
  }
  */
  
	class Nachrichten_Plugin {

		function __construct() {
			// Widgets.
			add_action('widgets_init', array($this, 'widgets_init'));
			add_action('admin_init', array($this, 'admin_init'));
			add_action('admin_menu', array($this, 'admin_menu'));
			add_action('admin_init', array(&$this, 'register_help_section'));
			add_action('admin_init', array(&$this, 'register_shortcode_section'));
			//add_action('admin_init', array(&$this, 'register_style_section'));
			add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts'));
			add_action('wp_enqueue_scripts', array($this, 'register_styles'));

			add_action('admin_init', array($this, 'refresh_plugin_version'));

			register_deactivation_hook(__FILE__, array($this, 'nachrichten_deactivation'));
		}

		function nachrichten_deactivation() {
			
		}

		function refresh_plugin_version() {
			if (function_exists('get_plugin_data')) {
				$xtime = get_option('nachrichten_plugin_version_taken');
				$mtime = filemtime(plugin_dir_path(__FILE__) . 'nachrichten-plugin.php');
				if ($mtime > $xtime) {
					Nachrichten_Plugin_Utils::np_version_hard();
				}
			}
		}

		/**
		 * Register the plugin widget, widget areas and widget shorcodes.
		 */
		function widgets_init() {
			register_widget('Nachrichten_Plugin_Widget');
			for ($area = 1; $area <= 4; $area++) {
				register_sidebar(array(
					'name' => "Nachrichten Widget Area ".esc_html( $area ),
					'id' => "nachrichten_plugin_widgets_".esc_html( $area ),
					'description' => "Verwenden Sie den [nachrichten_plugin_widgets&nbsp;area=".esc_html( $area )."] Shortcode, um die Nachrichten überall anzuzeigen, wo Sie möchten.",
					'before_widget' => '<div id="%1$s" class="widget %2$s">',
					'after_widget' => '</div>'
				));
			}
			add_shortcode('nachrichten_plugin_widgets', array($this, 'widget_area_shortcode'));
			add_shortcode('nachrichten_plugin_feed', array($this, 'feed_shortcode'));
		}

		/**
		 * Process the widget area shortcode.
		 */
		function widget_area_shortcode($attrs) {
			$a = shortcode_atts(array('area' => '1'), $attrs);
			$sidebar = "nachrichten_plugin_widgets_".esc_html( $a['area'] );
			ob_start();
			if (is_active_sidebar($sidebar)) {
				echo '<div class="nachrichten_plugin_widget_area">';
				dynamic_sidebar($sidebar);
				echo '</div>';
			}
			return ob_get_clean();
		}

		//[feed_shortcode title="" keywords="News" count="" age="" search_mode="" search_type="" link_type="" show_date="" show_abstract=""]

		/**
		 * Process the newsfeed shortcode.
		 */
		function feed_shortcode($attrs) {
			$attrs = shortcode_atts(array(
				'id' => '',
				'title' => 'Nachrichten',
				'partner_id' => '',
				'count' => '',
				'age' => '',
				'search_type' => '',
				'link_open_mode' => '',
				'link_follow' => '',
				'link_type' => '',
				'show_date' => '',
				'show_abstract' => '',
				'show_premium_only' => '',
				'show_image' => '',
				'wp_uid' => ''
				), $attrs);
			$wid = new Nachrichten_Plugin_Widget();
			$a = $wid->update($attrs, array());
			$a['id'] = $attrs['id'];
			ob_start();
			the_widget('Nachrichten_Plugin_Widget', $a, array());
			return ob_get_clean();
		}

		/**
		 * Register the plugin CSS style.
		 */
		function register_styles() {
			$assets_path = plugins_url('Nachrichten/assets/');
			wp_register_style('nachrichten-plugin', $assets_path . 'css/nachrichten-plugin.css', array(), "0.1");
			wp_enqueue_style('nachrichten-plugin');
		}

		function register_admin_scripts() {
			$assets_path = plugins_url('Nachrichten/assets/');
			wp_enqueue_script('nachrichten-plugin', $assets_path . 'js/nachrichten-plugin.js');
		}

		/**
		 * Register the plugin options.
		 */
		function admin_init() {
			add_settings_section(
				'default', NULL, NULL, 'nachrichten-plugin-settings'
			);
		}

		/**
		 * Register the plugin menu.
		 */
		function admin_menu() {
			add_menu_page(
				esc_html( __('Nachrichten Settings', 'nachrichten_plugin') ), esc_html( __('Nachrichten', 'nachrichten_plugin') ), 'manage_options', 'nachrichten-plugin-settings', array($this, 'nachrichten_plugin_options_page'), 'dashicons-rss', '3'
			);
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
		}

		/*
		 * For easier overriding I declared the keys
		 * here as well as our tabs array which is populated
		 * when registering settings
		 */

		private $status_settings_key = 'newsplugin_status_settings';
		private $feed_settings_key = 'nachrichten_plugin_feed_settings';
		//private $style_settings_key = 'newsplugin_style_settings';
		private $activation_settings_key = 'newsplugin_activation_settings';
		private $shortcode_settings_key = 'newsplugin_shortcode_settings';
		private $help_settings_key = 'newsplugin_help_settings';
		private $plugin_options_key = 'nachrichten-plugin-settings';
		private $plugin_settings_tabs = array();

		function register_shortcode_section() {
			$this->plugin_settings_tabs[$this->shortcode_settings_key] = 'Shortcode erstellen';
		}

		function register_help_section() {
			$this->plugin_settings_tabs[$this->help_settings_key] = 'Anweisungen';
		}

		function get_with_default($arr, $a, $b, $def) {
			if (!is_array($arr)) {
				return $def;
			}
			if (!isset($arr[$a])) {
				return $def;
			}
			if (!isset($arr[$a][$b])) {
				return $def;
			}
			return $arr[$a][$b];
		}

		/*
		 * Plugin Options page
		 */

		function nachrichten_plugin_options_page() {
			$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : $this->help_settings_key;
			?>
			<div class="wrap">
				<h2>Nachrichten Settings</h2>
				<?php $this->nachrichten_plugin_options_tabs($tab); ?>
				<?php
				$key = 'uE6DXfQFbDXY4QE1BKQNoaxCdPq_GDOA';
				if ($tab === $this->activation_settings_key) {
					?>
					<form method="post" action="options.php">
						<?php wp_nonce_field('update-options'); ?>
						<?php settings_fields($this->plugin_options_key); ?>
						<?php do_settings_sections($this->plugin_options_key); ?>
					<?php submit_button(); ?>
					</form>
			<?php } else if ($tab === $this->shortcode_settings_key && !empty($key)) { ?>
					<table id="shortcodeTable" class="form-table">
						<tr>
							<th scope="row">
								<label for="newsplugin_title">NachrichtenTitle: </label>
							</th>
							<td>
								<input type="text" id="newsplugin_title" name="newsplugin_title" value="Nachrichten" class="regular-text" onclick="validationFocus('newsplugin_title')" onfocus="validationFocus('newsplugin_title')">
								<p class="description">Gib deinem Feed einen guten Namen. Zum Beispiel: Spanien Nachrichten</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Nur Premium Nachrichten:</th>
							<td>
								<fieldset>
									<label for="newsplugin_more_premium"><input type="checkbox" checked id="newsplugin_more_premium" name="newsplugin_more_premium"></label>
									<br>
									
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="newsplugin_url">Partner ID: </label>
							</th>
							<td>
								<input type="text" id="newsplugin_partner_id" name="newsplugin_partner_id" value="" class="regular-text">
								<p class="description">Ihre Partner ID. Geld verdienen mit Premium Nachrichten ( Anmeldung hier: <a href="https://nachrichten.es/partnerprogramm/" target="_blank">https://nachrichten.es/partnerprogramm/</a> ).</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="newsplugin_articles">Anzahl von Artikeln: </label>
							</th>
							<td>
								<input type="text" id="newsplugin_articles" name="newsplugin_articles" value="10" class="regular-text" onclick="validationFocus('newsplugin_articles')" onfocus="validationFocus('newsplugin_articles')">
								<p class="description">Anzahl der Artikel. Beispiel: 10</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Information anzeigen:</th>
							<td>
								<fieldset>
									<label for="newsplugin_more_dates"><input type="checkbox" checked id="newsplugin_more_dates" name="newsplugin_more_dates">Datum anzeigen</label>
									<br>
									<label for="newsplugin_more_abstracts"><input type="checkbox" checked id="newsplugin_more_abstracts" name="newsplugin_more_abstracts">Content anzeigen</label>
									<br>
									<p class="description">Standardmäßig zeigt der Feed Schlagzeilen, Datum und Inhalte an</p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">Bild:</th>
							<td>
								<fieldset>
									<label for="newsplugin_more_image"><input type="checkbox" checked id="newsplugin_more_image" name="newsplugin_more_image">Bild anzeigen</label>
									<br>
									<p class="description">Standardmäßig zeigt der Feed ein Bild an</p>
								</fieldset>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="newsplugin_link_open">Link-Open Mode: </label>
							</th>
							<td>
								<select id="newsplugin_link_open" name="newsplugin_link_open">
									<option value="_blank">Neues Fenster</option>
									<option value="_self">Gleiches Fenster</option>
								</select>
								<p class="description">Link im selben Fenster oder in neuem Tab öffnen. Die Standardeinstellung ist Neuer Tab.</p>
							</td>
						</tr>
					</table>
					<p class="submit">
				<?php add_thickbox(); ?>
					<div id="shortcode-generated" style="display:none;"></div>
					<input type="button" id="shortcode_button" value="Generate Shortcode" class="button button-primary" onclick="validateShortcode(<?php echo esc_attr(get_current_user_id()); ?>)">
					</p>
					
			<?php } else if ($tab === $this->help_settings_key) { ?>
					<h3>Anweisungen</h3>
					<p>Bitte lesen Sie die folgenden Anweisungen sorgfältig durch, um Nachrichten.plus einfach einzurichten und zu verwenden Plugin.</p>
					<p><strong>Erstellen Sie Nachrichten-Feeds:</strong><br>Erstellen Sie Ihre Nachrichten, indem Sie einen Shortcode generieren <a href="<?php echo admin_url('admin.php?page=nachrichten-plugin-settings&tab=' . esc_attr( $this->shortcode_settings_key ) ) ?>">Shortcode generieren</a> tab. Fügen Sie diesen Shortcode in Beiträge oder Seiten ein, auf denen Sie Ihren Nachrichten feed anzeigen möchten.<br>ODER<br>erstellen Sie Ihren Nachrichten-Feed <a href="<?php echo esc_attr( admin_url('widgets.php') ); ?>">Appearance &gt; Widgets</a>. Ziehen Sie aus dem Widgets-Panel das Widget „Nachrichten Plugin“ in die gewünschte Seitenleiste oder den gewünschten Widget-Bereich, wo Sie Ihren Nachrichten feed anzeigen möchten. Bearbeiten Sie die Widget-Funktionen, um den Nachrichten feed zu erstellen/bearbeiten. <br><br>Partner ID - Verdienen Sie Geld mit Premium-Nachrichten ( Anmeldung hier: <a href="https://nachrichten.plus/partnerprogramm/" target="_blank">https://nachrichten.plus/partnerprogramm/</a> ).</p>
					<?php }
				?>
			</div>
			<?php
		}

		function nachrichten_plugin_options_tabs($current_tab) {
			echo '<h2 class="nav-tab-wrapper">';
			foreach ($this->plugin_settings_tabs as $tab_key => $tab_caption) {
				$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
				echo wp_kses_post('<a class="nav-tab ' . esc_attr( $active ) . '" href="?page=' . esc_attr( $this->plugin_options_key ) . '&tab=' . esc_attr( $tab_key ) . '">' . $tab_caption . '</a>');
			}
			echo '</h2>';
		}

		function add_action_links($default_links) {
			$links = array(
				'<a href="' . esc_attr( admin_url('admin.php?page=nachrichten-plugin-settings') ) . '">Settings</a>',
			);
			return array_merge($links, $default_links);
		}

	}

	new Nachrichten_Plugin();
?>
