<?php
// Prevent ourselves from being run directly.
	defined('ABSPATH') or die("No script kiddies please!");

	/**
	 * Nachrichten Plugin widget
	 */
	class Nachrichten_Plugin_Widget extends WP_Widget {

		/**
		 * Register widget with WordPress.
		 */
		function __construct() {
			parent::__construct(
				'nachrichten_plugin_widget', esc_html( __('NachrichtenPlugin', 'nachrichten_plugin') ), array('description' => esc_html( __('Erstellen Sie benutzerdefinierte Nachrichten und lassen Sie neue, relevante Nachrichten auf Ihrer Website oder Ihrem Blog erscheinen', 'nachrichten_plugin') ),)
			);
		}

		/**
		 * Get the id for identifying this widget instance.
		 */
		private function widget_id() {
			return $this->id;
		}

		/**
		 * Get the private options specific for this widget.
		 *
		 * Note: The "public" options are stored in option widget_nachrichten_plugin_widget automatically by class WP_Widget function save_settings
		 * unless it's the "nachrichten_plugin_feed" shortcode, in which case they are put directly in the shortcode.
		 * (The "nachrichten_plugin_widgets" shortcode uses normal registered widgets and can actually be filled with other widgets as well)
		 * The widget_id appears to be short number for registered widgets and long number (number of seconds since 1969) for "nachrichten_plugin_feed" shortcodes.
		 */
		private function current_options() {
			$opts = get_option('nachrichten_plugin_widget_options', array());
			$opts = (isset($opts[$this->widget_id()])) ? $opts[$this->widget_id()] : array();
			return $opts;
		}

		/**
		 * Update the private options specific for this widget.
		 */
		private function update_options($args) {
			
			$opts = get_option('nachrichten_plugin_widget_options', array());
			$opts[$this->widget_id()] = $args;
			update_option('nachrichten_plugin_widget_options', $opts);
			return $args;
		}

		/**
		 * Get the timestamp of the last publishing in manual publishing mode.
		 */
		private function publish_time() {
			$opts = $this->current_options();
			$time = $opts['published'];
			return ( isset($time) ? $time : 0 );
		}

		/**
		 * Set the timestamp of the last publishing in manual publishing mode.
		 */
		private function update_publish_time($time) {
			
			$opts = $this->current_options();
			$opts['published'] = $time;
			$this->update_options($opts);
			return $time;
		}

		/**
		 * Prepare the args for URL managing posts of this widget.
		 */
		private function create_action_args($action, $arg = 0) {
			return array(
				'nachrichten_plugin_instance' => $this->widget_id(),
				'nachrichten_plugin_action' => $action,
				'nachrichten_plugin_arg' => $arg,
				);
		}

		/**
		 * Parse the URL args for managing posts of this widget.
		 */
		private function parse_action_args() {
			if ((!isset($_GET['nachrichten_plugin_instance'])) || ( $_GET['nachrichten_plugin_instance'] != $this->widget_id() )) {
				return array();
			}
			return array(
				'action' => isset($_GET['nachrichten_plugin_action']) ? sanitize_text_field($_GET['nachrichten_plugin_action']) : '',
				'arg' => isset($_GET['nachrichten_plugin_arg']) ? sanitize_text_field($_GET['nachrichten_plugin_arg']) : '',
				);
		}

		/**
		 * Get the action associated with given URL request, if any.
		 */
		private function current_action() {
			$args = $this->parse_action_args();
			if (!empty($args['action'])) {
				return $args['action'];
			}
		}

		/**
		 * Get the argument associated with given URL request, if any.
		 */
		private function current_arg() {
			$args = $this->parse_action_args();
			return $args['arg'];
		}

		/**
		 * Silly helper for returning caching duration for fetch_feed().
		 */
		function get_feed_caching_duration($seconds) {
			return 3600;
		}

		/**
		 * Get our data feed.
		 */
		private function get_feed($time, $opts, $limit = 100) {
			
			
			$args = array(
				
				'l' => $limit,
				'c' => $opts['count'],
				't' => $opts['title']
				// o offset
				// a after
				// b before
				);


			if (!empty($opts['age'])) {
				$args['a'] = $time - 3600 * $opts['age'];
			}

			
			if (!empty($opts['search_type'])) {
				$args['type'] = $opts['search_type'];
			}
			if (!empty($opts['link_type'])) {
				$args['link'] = $opts['link_type'];
			}
			if (!empty($opts['link_open_mode'])) {
				$args['link_open_mode'] = $opts['link_open_mode'];
			}
			if (isset($opts['show_premium_only']) && $opts['show_premium_only']!==false) {
				$args['feed_url'] ='https://nachrichten.plus/premium/feed/';
			}
			else
			{
				$args['feed_url'] = 'https://nachrichten.plus/feed/';
			}
			if (!empty($opts['partner_id'])) {
				$args['partner_id'] = $opts['partner_id'];
			}
			$args['link_follow'] = '';
			$url = add_query_arg(urlencode_deep($args), $args['feed_url'].'/feed/');

			$cache_filter = array($this, 'get_feed_caching_duration');
			add_filter('wp_feed_cache_transient_lifetime', $cache_filter);

			$feed = fetch_feed($url);

			remove_filter('wp_feed_cache_transient_lifetime', $cache_filter);

			return ( is_wp_error($feed) ? NULL : $feed );
		}

		private function compute_style_helper($style, $type) {
			if (!isset($style[$type])) {
				return ('');
			}
			$ret = '';
			if ($style[$type]['size']) {
				$ret .= 'font-size: ' .esc_html( $style[$type]['size'] ). 'px;';
			}
			if ($style[$type]['color']) {
				$ret .= 'color:#' . esc_html( $style[$type]['color'] ) . ';';
			}
			if ($style[$type]['font_family']) {
				$ret .= 'font-family:' . esc_html( $style[$type]['font_family'] ) . ';';
			}
			if (!$ret) {
				return($ret);
			}
			return( $ret );
		}

		/**
		 * Generate the feed content.
		 *
		 * @param array $opts Saved values from database.
		 */
		private function content($opts) {
			
			$time = time();

			$rss = $this->get_feed($time, $opts);

			if (!isset($rss)) {
				esc_html( _e('Feed fetch failed ', 'nachrichten_plugin') );
				return;
			}

			$limit = $opts['count'];
			$visible = $limit;
      $exclude = []; // @TODO:: what is it for?
			$count = $rss->get_item_quantity($visible + count($exclude));
			$items = $rss->get_items(0, $count);
			$index = 0;

			if ($opts['wp_uid'] && (intval($opts['wp_uid']) != 0)) {
				$userID = intval($opts['wp_uid']);
			} else {
				$userID = get_current_user_id();
			}
			$style_news = get_user_meta($userID, 'news_style_dashbord_style', 'true');

			echo '<ul>';
			for ($pass = 0; $pass < 2; $pass++) {
				foreach ($items as $item) {
					if ($index >= $visible) {
						break;
					}

					$id = md5($item->get_id(false));
					if (!empty($exclude[$id])) {
						continue;
					}

					if (!empty($favorite[$id]) xor ( $pass == 0 )) {
						continue;
					}

					if ($index == $limit) {
						echo '<hr>';
					}

					echo '<li>';
					$s_follow = '';
					if ($opts['link_open_mode']) {
						$s_target = ' target="' . esc_attr( $opts['link_open_mode'] ) . '"';
					} else {
						$s_target = '';
					}
          
				$permalink = $item->get_permalink();
					if (!empty($opts['partner_id'])) {
						$permalink_mod = trailingslashit($permalink) . '?ref='.$opts['partner_id'];
					} else {
            $permalink_mod = trailingslashit($permalink);
          }
          
		  $style = $this->compute_style_helper($style_news, 'article_headline');

					echo '<a href="' . esc_url($permalink_mod) . '"' . esc_html( $s_target ). esc_html( $s_follow ). '">';
					echo '    <span class="nachrichten-plugin-title" style="' . esc_attr($style) . '">' . esc_html($item->get_title()) . '</span>';
					echo '</a>';
		  
					if (isset($opts['show_date']) && $opts['show_date']!==false) {
						$style = $this->compute_style_helper($style_news, 'article_date');
					echo '<span class="nachrichten-plugin-date" style="' . esc_attr( $style ) . '" >';
						echo esc_html($item->get_date('d M Y H:i'));
						echo '</span>';
					}

					
						$style = $this->compute_style_helper($style_news, 'article_abstract');
					echo '<span class="nachrichten-plugin-abstract" style="' . esc_attr( $style ) . '" >';
						
            			$abstract = $item->get_description();
            			$abstract_mod = str_replace($permalink, $permalink_mod, $abstract);
						
						if (isset($opts['show_abstract']) && $opts['show_abstract']!==false) {
							$abstract_mod=$abstract_mod;
						}
						else
						{
							$location=strpos($abstract_mod,'</a>');
							if($location!==false)
							{
								$abstract_mod=str_replace(substr($abstract_mod,$location+4),'',$abstract_mod);
							}
							//$abstract_mod=str_replace('</a>','</a><br>',strip_tags($abstract_mod,'<img><a>'));
						}
						
						if (isset($opts['show_image']) && $opts['show_image']!==false) {
							echo  ( $abstract_mod );
						}
						else
						{
							$allowed_tags = '<span><div><a><p><br>';
							$cleaned_abstract = strip_tags($abstract_mod, $allowed_tags);
							$processed_abstract = str_replace('</a>', '</a><br>', $cleaned_abstract);

							echo $processed_abstract;

							//echo esc_html( str_replace( '</a>', '</a><br>', strip_tags( $abstract_mod, '<span><div><a><p><br>' ) ) );
						}
						
						echo '</span>';
					

					echo '</li>';

					$index++;
				}
			}
			echo '</ul>';
		}

		/**
		 * Front-end display of widget.
		 *
		 * @see WP_Widget::widget()
		 *
		 * @param array $args     Widget arguments.
		 * @param array $opts Saved values from database.
		 */
		public function widget($args, $opts) {
			
			
			/*$id = absint($opts['id']);
			if ($id > 0) {
				$this->_set($id);
			}*/

			$title = apply_filters('widget_title', $opts['title']);

			echo wp_kses_post($args['before_widget']);
			if (!empty($title)) {
				echo wp_kses_post($args['before_title'] . $title . $args['after_title']);
      }
			$this->content($opts);
			echo wp_kses_post($args['after_widget']);
		}

		/**
		 * Back-end widget form.
		 *
		 * @see WP_Widget::form()
		 *
		 * @param array $opts Previously saved values from database.
		 */
		public function form($opts) {
			
			if (isset($opts['title'])) {
				$title = $opts['title'];
			} else {
				$title = esc_html( __('New title', 'nachrichten_plugin') );
			}
			if (isset($opts['partner_id'])) {
				$partner_id = $opts['partner_id'];
			} else {
				$partner_id = '';
			}
			
			if (isset($opts['count'])) {
				$count = $opts['count'];
			} else {
				$count = 5;
			}
			if (isset($opts['age'])) {
				$age = $opts['age'];
			} else {
				$age = 0;
			}

			

			if (isset($opts['search_type'])) {
				$search_type = $opts['search_type'];
			} else {
				$search_type = "";
			}

			$sort_mode = "";

			if (isset($opts['link_type'])) {
				$link_type = $opts['link_type'];
			} else {
				$link_type = "";
			}
			if (isset($opts['link_open_mode'])) {
				$link_open_mode = $opts['link_open_mode'];
			} else {
				$link_open_mode = "";
			}
			if (isset($opts['link_follow'])) {
				$link_follow = $opts['link_follow'];
			} else {
				$link_follow = "";
			}

			if (isset($opts['show_date']) && $opts['show_date']) {
				$show_date = true;
			} else {
				$show_date = false;
			}
			if (isset($opts['show_abstract']) && $opts['show_abstract']) {
				$show_abstract = true;
			} else {
				$show_abstract = false;
			}
			if (isset($opts['show_image']) && $opts['show_image']) {
				$show_image = true;
			} else {
				$show_image = false;
			}
			if (isset($opts['show_premium_only']) && $opts['show_premium_only']) {
				$show_premium_only = true;
			} else {
				$show_premium_only = false;
			}
			$user_mode = 2;
			?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Nachrichten Name:' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
				<br>
				<small>Geben Sie Ihren Nachrichten einen guten Namen.</small>
				<br>
				<small>Beispiel: Spanien Nachrichten</small>
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'partner_id' ) ); ?>"><?php esc_html_e( 'Partner ID:' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'partner_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'partner_id' ) ); ?>" type="text" value="<?php echo esc_attr( $partner_id ); ?>">
				<br>
				<small>Ihre Partner ID.<br>Partner ID - Geld verdienen mit Premium Nachrichten ( Anmeldung hier: <a href="https://nachrichten.plus/partnerprogramm/" target="_blank">https://nachrichten.plus/partnerprogramm/</a></small>
				
			</p>
			<p>
				<input id="<?php echo esc_attr($this->get_field_id('show_premium_only')); ?>" name="<?php echo esc_attr($this->get_field_name('show_premium_only')); ?>" type="checkbox" <?php if ($show_premium_only) echo 'checked="checked"' ?>>
				<label for="<?php echo esc_attr( $this->get_field_id( 'show_premium_only' ) ); ?>"><?php esc_html_e( 'Nur Premium anzeigen' ); ?></label>
				<br>
			</p>
			
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Anzahl der Artikel:' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="text" value="<?php echo esc_attr( $count ); ?>">
				<br>
				<small>Legen Sie fest, wie viele Schlagzeilen in Ihrem Feed angezeigt werden sollen.</small>
				<br>
				<small>Beispiel: 10</small>
			</p>
			<p>
				<input id="<?php echo esc_attr($this->get_field_id('show_date')); ?>" name="<?php echo esc_attr($this->get_field_name('show_date')); ?>" type="checkbox" <?php if ($show_date) echo 'checked="checked"' ?>>
				<label for="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>"><?php esc_html_e( 'Zeige Datum' ); ?></label>
			</p>
			<p>
			<p>
				<input id="<?php echo esc_attr($this->get_field_id('show_abstract')); ?>" name="<?php echo esc_attr($this->get_field_name('show_abstract')); ?>" type="checkbox" <?php if ($show_abstract) echo 'checked="checked"' ?>>
				<label for="<?php echo esc_attr( $this->get_field_id( 'show_abstract' ) ); ?>"><?php esc_html_e( 'Zeige content' ); ?></label>
				<br>
				<small>Standardmäßig zeigt Ihr Feed nur Schlagzeilen an. Sie können weitere Informationen hinzufügen.</small>
			</p>
			<p>
				<input id="<?php echo esc_attr($this->get_field_id('show_image')); ?>" name="<?php echo esc_attr($this->get_field_name('show_image')); ?>" type="checkbox" <?php if ($show_image) echo 'checked="checked"' ?>>
				<label for="<?php echo esc_attr( $this->get_field_id( 'show_image' ) ); ?>"><?php esc_html_e( 'Zeige Bild' ); ?></label>
				<br>
				
			</p>
			<?php
			if ($user_mode > 0) {
				?>
				

				<?php ?>

				<p>
					<label for="<?php echo esc_attr($this->get_field_id('age')); ?>"><?php esc_html_e('Nachrichten alter (in stunden):'); ?></label>
					<input class="widefat" id="<?php echo esc_attr($this->get_field_id('age')); ?>" name="<?php echo esc_attr($this->get_field_name('age')); ?>" type="text" value="<?php echo esc_attr($age); ?>">
					<br>
					<small>Don’t show articles older than given period. 0 means no limit.</small>
				</p>
				<p>
					<label for="<?php echo esc_attr($this->get_field_id('link_open_mode')); ?>"><?php esc_html_e('Link mode:'); ?></label>
					<select class="widefat" id="<?php echo esc_attr($this->get_field_id('link_open_mode')); ?>" name="<?php echo esc_attr($this->get_field_name('link_open_mode')); ?>">
						<option value="">Default</option>
						<option value="_self" <?php if ($link_open_mode == "_self") echo 'selected="selected"' ?>>Gleiches Fenster</option>
						<option value="_blank"<?php if ($link_open_mode == "_blank") echo 'selected="selected"' ?>>Neues Fenster</option>
					</select>
					<?php
				}
			}

			/**
			 * Sanitize widget form values as they are saved.
			 *
			 * @see WP_Widget::update()
			 *
			 * @param array $new_opts Values just sent to be saved.
			 * @param array $old_opts Previously saved values from database.
			 *
			 * @return array Updated safe values to be saved.
			 */
			public function update($new_opts, $old_opts) {
				
				$opts = array();
		$opts['title']             = ( ! empty( $new_opts['title'] ) ) ? wp_strip_all_tags( $new_opts['title'] ) : '';
				$opts['show_premium_only'] = ($new_opts['show_premium_only']=='true' ||  $new_opts['show_premium_only']=='on') ? true : false;
		$opts['partner_id']        = ( ! empty( $new_opts['partner_id'] ) ) ? wp_strip_all_tags( $new_opts['partner_id'] ) : '';
				
				$opts['count'] = (!empty($new_opts['count']) ) ? absint($new_opts['count']) : 5;
				$opts['age'] = (!empty($new_opts['age']) ) ? absint($new_opts['age']) : 0;
				
		$opts['search_type']    = ( ! empty( $new_opts['search_type'] ) ) ? wp_strip_all_tags( $new_opts['search_type'] ) : '';
		$opts['link_open_mode'] = ( ! empty( $new_opts['link_open_mode'] ) ) ? wp_strip_all_tags( $new_opts['link_open_mode'] ) : '';
		$opts['link_follow']    = ( ! empty( $new_opts['link_follow'] ) ) ? wp_strip_all_tags( $new_opts['link_follow'] ) : '';
		$opts['link_type']      = ( ! empty( $new_opts['link_type'] ) ) ? wp_strip_all_tags( $new_opts['link_type'] ) : '';
		$opts['show_date']      = ( $new_opts['show_date'] == 'true' || $new_opts['show_date'] == 'on' ) ? true : false;
		$opts['show_abstract']  = ( $new_opts['show_abstract'] == 'true' || $new_opts['show_abstract'] == 'on' ) ? true : false;
		$opts['show_image']     = ( $new_opts['show_image'] == 'true' || $new_opts['show_image'] == 'on' ) ? true : false;
		$opts['feed_mode']      = ( ! empty( $new_opts['feed_mode'] ) ) ? wp_strip_all_tags( $new_opts['feed_mode'] ) : '';
		$opts['wp_uid']         = ( ! isset( $new_opts['wp_uid'] ) || empty( $new_opts['wp_uid'] ) ) ? get_current_user_id() : $new_opts['wp_uid'];

		return $opts;
	}

		}
	?>
