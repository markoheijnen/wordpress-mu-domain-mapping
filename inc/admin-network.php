<?php

class WordPress_MU_Domain_Mapping_Admin_Network {

	public function __construct() {

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'network_admin_menu', array( $this, 'network_menu' ) );
		add_action( 'manage_sites_custom_column', array( $this, 'sites_custom_column' ), 1, 3 );

		add_filter( 'wpmu_blogs_columns', array( $this, 'sites_columns' ) );

	} // END __construct()

	public function network_menu() {

		add_submenu_page(
			'settings.php',
			__( 'Domain Mapping', 'wordpress-mu-domain-mapping' ),
			__( 'Domain Mapping', 'wordpress-mu-domain-mapping' ),
			'manage_network',
			'dm_admin_page',
			array( $this, 'admin_page' )
		);

		add_submenu_page(
			'settings.php',
			__( 'Domains', 'wordpress-mu-domain-mapping' ),
			__( 'Domains', 'wordpress-mu-domain-mapping' ),
			'manage_network',
			'dm_domains_admin',
			array( $this, 'domains_page' )
		);

	} // END network_menu()

	public function register_settings() {

		// __( 'The information you enter here will be shown to your users so they can configure their DNS correctly. It is for informational purposes only', 'wordpress-mu-domain-mapping' );

		add_settings_section(
			'ip-mapping',
			__( 'IP Mapping', 'wordpress-mu-domain-mapping' ),
			array( $this, 'desc_ip_mapping' ),
			'dm-network-settings'
		);

		add_settings_field(
			'dm-ipaddress',
			__( 'Server IP Address', 'wordpress-mu-domain-mapping' ),
			array( $this, 'generate_option' ),
			'dm-network-settings',
			'ip-mapping',
			array(
				'type'  => 'text',
				'id'    => 'ipaddress',
				'value' => get_site_option( 'dm_ipaddress' ),
				'desc'  => __( 'If you use round robin DNS or another load balancing technique with more than one IP, enter each address, separating them by commas.', 'wordpress-mu-domain-mapping' ),
			)
		);

		add_settings_section(
			'cname-mapping',
			__( 'CNAME Mapping', 'wordpress-mu-domain-mapping' ),
			array( $this, 'desc_cname_mapping' ),
			'dm-network-settings'
		);

		add_settings_field(
			'dm-cname',
			__( 'Server CNAME domain', 'wordpress-mu-domain-mapping' ),
			array( $this, 'generate_option' ),
			'dm-network-settings',
			'cname-mapping',
			array(
				'type'  => 'text',
				'id'    => 'cname',
				'value' => get_site_option( 'dm_cname' ),
				'desc'  => sprintf( __( 'International Domain Names should be in <a href="%s">punycode</a> format.', 'wordpress-mu-domain-mapping' ), 'http://api.webnic.cc/idnconversion.html' ),
			)
		);

		add_settings_section(
			'domain-options',
			__( 'Domain Options', 'wordpress-mu-domain-mapping' ),
			'__return_empty_string',
			'dm-network-settings'
		);

		add_settings_field(
			'dm-mapping-behavior',
			__( 'Mapping Behavior', 'wordpress-mu-domain-mapping' ),
			array( $this, 'generate_option' ),
			'dm-network-settings',
			'domain-options',
			array(
				'type'   => 'checkboxes',
				'fields' => array(
					array(
						'id'    => 'dm_remote_login',
						'value' => get_site_option( 'dm_remote_login' ),
						'label'  => __( 'Remote Login', 'wordpress-mu-domain-mapping' ),
					),
					array(
						'id'    => 'dm_301_redirect',
						'value' => get_site_option( 'dm_301_redirect' ),
						'label'  => __( "Permanent redirect (better for your blogger's pagerank)", 'wordpress-mu-domain-mapping' ),
					),
					array(
						'id'    => 'dm_user_settings',
						'value' => get_site_option( 'dm_user_settings' ),
						'label'  => __( 'User domain mapping page', 'wordpress-mu-domain-mapping' ),
					),
					array(
						'id'    => 'dm_redirect_admin',
						'value' => get_site_option( 'dm_redirect_admin' ),
						'label'  => __( "Redirect administration pages to site's original domain (remote login disabled if this redirect is disabled)", 'wordpress-mu-domain-mapping' ),
					),
					array(
						'id'    => 'dm_no_primary_domain',
						'value' => get_site_option( 'dm_no_primary_domain' ),
						'label'  => __( 'Disable primary domain check. Sites will not redirect to one domain name. May cause duplicate content issues.', 'wordpress-mu-domain-mapping' ),
					),
				),
			)
		);

	} // END register_settings()

	public function generate_option( $args ) {

		switch ( $args['type'] ) {

			case 'checkboxes' :
				echo '<fieldset>';
					foreach ( $args['fields'] as $field ) {
						echo '<label for="' . $field['id'] . '">';
							echo '<input id="' . $field['id'] . '" type="checkbox" value="1" name="' . $field['id'] . '"' . checked( $field['value'], 1, false ) . '>';
							if ( isset( $field['label'] ) ) {
								echo $field['label'];
							}
						echo '</label><br />';
					}
				if ( isset( $args['desc'] ) ) {
					echo '<p class="description">' . $args['desc'] . '</p>';
				}
				echo '</fieldset>';
				break;

			case 'text' :
				echo '<input id="' . $args['id'] . '" type="text" value="' . $args['value'] . '" name="' . $args['id'] . '" size="50">';
				if ( isset( $args['desc'] ) ) {
					echo '<p class="description">' . $args['desc'] . '</p>';
				}
				break;

		} // END switch

	} // END generate_option()

	public function desc_ip_mapping() {

		echo '<p>' . __( "As a super admin on this network you can set the IP address users need to point their DNS A records at <em>or</em> the domain to point CNAME record at. If you don't know what the IP address is, ping this blog to get it.", 'wordpress-mu-domain-mapping' ) . '</p>';

	} // END desc_ip_mapping()

	public function desc_cname_mapping() {

		echo '<p>' . __( 'If you prefer the use of a CNAME record, you can set the domain here. This domain must be configured with an A record or ANAME pointing at an IP address. Visitors may experience problems if it is a CNAME of another domain.', 'wordpress-mu-domain-mapping' ) . "</p>";
		echo '<p>' . __( 'NOTE, this voids the use of any IP address set above', 'wordpress-mu-domain-mapping' ) . '</p>';

	} // END desc_cname_mapping()

	public function admin_page() {

		global $wpdb, $current_site;

		if ( ! is_super_admin() ) {
			return false;
		}

		WordPress_MU_Domain_Mapping::sunrise_warning();
		$this->maybe_create_db();

		if ( $current_site->path != "/" ) {
			wp_die( sprintf( __( "<strong>Warning!</strong> This plugin will only work if WordPress is installed in the root directory of your webserver. It is currently installed in &#8217;%s&#8217;.", "wordpress-mu-domain-mapping" ), $current_site->path ) );
		}
		// set up some defaults
		if ( get_site_option( 'dm_remote_login', 'NA' ) == 'NA' ) {
			add_site_option( 'dm_remote_login', 1 );
		}
		if ( get_site_option( 'dm_redirect_admin', 'NA' ) == 'NA' ) {
			add_site_option( 'dm_redirect_admin', 1 );
		}
		if ( get_site_option( 'dm_user_settings', 'NA' ) == 'NA' ) {
			add_site_option( 'dm_user_settings', 1 );
		}
		if ( ! empty( $_POST[ 'action' ] ) ) {

			check_admin_referer( 'domain_mapping' );

			if ( $_POST[ 'action' ] == 'update' ) {

				$ipok = true;
				$ipaddresses = explode( ',', $_POST[ 'ipaddress' ] );

				foreach ( $ipaddresses as $address ) {
					if ( ( $ip = trim( $address ) ) && ! preg_match( '|^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$|', $ip ) ) {
						$ipok = false;
						break;
					}
				}

				if ( $ipok ) {
					update_site_option( 'dm_ipaddress', $_POST[ 'ipaddress' ] );
				}
				if ( intval( $_POST[ 'dm_redirect_admin' ] ) == 0 ) {
					$_POST[ 'dm_remote_login' ] = 0; // disable remote login if redirecting to mapped domain
				}
				update_site_option( 'dm_remote_login', intval( $_POST[ 'dm_remote_login' ] ) );

				if ( ! preg_match( '/(--|\.\.)/', $_POST[ 'cname' ] ) && preg_match( '|^([a-zA-Z0-9-\.])+$|', $_POST[ 'cname' ] ) ) {
					update_site_option( 'dm_cname', stripslashes( $_POST[ 'cname' ] ) );
				} else {
					update_site_option( 'dm_cname', '' );
				}

				update_site_option( 'dm_301_redirect', isset( $_POST[ 'dm_301_redirect' ] ) ? intval( $_POST[ 'dm_301_redirect' ] ) : 0 );
				update_site_option( 'dm_redirect_admin', isset( $_POST[ 'dm_redirect_admin' ] ) ? intval( $_POST[ 'dm_redirect_admin' ] ) : 0 );
				update_site_option( 'dm_user_settings', isset( $_POST[ 'dm_user_settings' ] ) ? intval( $_POST[ 'dm_user_settings' ] ) : 0 );
				update_site_option( 'dm_no_primary_domain', isset( $_POST[ 'dm_no_primary_domain' ] ) ? intval( $_POST[ 'dm_no_primary_domain' ] ) : 0 );

			}

		} ?>
		<div class="wrap">
			<h2><?php _e( 'Domain Mapping Configuration', 'wordpress-mu-domain-mapping' ); ?></h2>
			<form action="" method="post">
				<input type="hidden" name="action" value="update" />
				<?php
				wp_nonce_field( 'domain_mapping' );
				do_settings_sections( 'dm-network-settings' );
				submit_button();
				?>
			</form>
		</div><!-- .wrap -->
		<?php

	} // END admin_page()

	public function domains_page() {
		global $wpdb, $current_site;

		if ( ! is_super_admin() )
			return false;

		WordPress_MU_Domain_Mapping::sunrise_warning();

		if ( $current_site->path != "/" )
			wp_die( sprintf( __( "<strong>Warning!</strong> This plugin will only work if WordPress is installed in the root directory of your webserver. It is currently installed in &#8217;%s&#8217;.", "wordpress-mu-domain-mapping" ), $current_site->path ) );

		echo '<h2>' . __( 'Domain Mapping: Domains', 'wordpress-mu-domain-mapping' ) . '</h2>';

		if ( ! empty( $_POST[ 'action' ] ) ) {
			check_admin_referer( 'domain_mapping' );
			$domain = strtolower( $_POST[ 'domain' ] );

			switch( $_POST[ 'action' ] ) {
				case "edit":
					$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->dmtable} WHERE domain = %s", $domain ) );

					if ( $row )
						$this->edit_domain( $row );
					else
						echo '<h3>' . __( 'Domain not found', 'wordpress-mu-domain-mapping' ) . '</h3>';
				break;
				case "save":
					if (
						isset( $_POST['blog_id'] ) AND
						$_POST[ 'blog_id' ] != 0 AND 
						$_POST[ 'blog_id' ] != 1 AND 
						null == $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id != %d AND domain = %s", $_POST[ 'blog_id' ], $domain ) ) 
					) {
						$active = 0;

						if( isset( $_POST[ 'active' ] ) ) {
							$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 0 WHERE blog_id = %d", $_POST[ 'blog_id' ] ) );
							$active = 1;
						}

						if ( $_POST[ 'orig_domain' ] == '' ) {
							$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->dmtable} ( `blog_id`, `domain`, `active` ) VALUES ( %d, %s, %d )", $_POST[ 'blog_id' ], $domain, $active ) );
							echo '<p><strong>' . __( 'Domain Add', 'wordpress-mu-domain-mapping' ) . '</strong></p>';
						}
						else {
							$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET blog_id = %d, domain = %s, active = %d WHERE domain = %s", $_POST[ 'blog_id' ], $domain, $active, $_POST[ 'orig_domain' ] ) );
							echo '<p><strong>' . __( 'Domain Updated', 'wordpress-mu-domain-mapping' ) . '</strong></p>';
						}
					}
				break;
				case "del":
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->dmtable} WHERE domain = %s", $domain ) );
					echo '<p><strong>' . __( 'Domain Deleted', 'wordpress-mu-domain-mapping' ) . '</strong></p>';
				break;
				case "search":
					$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->dmtable} WHERE domain LIKE %s", $domain ) );
					$this->domain_listing( $rows, sprintf( __( "Searching for %s", 'wordpress-mu-domain-mapping' ), esc_html( $domain ) ) );
				break;
			}

			if ( $_POST[ 'action' ] == 'update' ) {
				if ( preg_match( '|^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$|', $_POST[ 'ipaddress' ] ) )
					update_site_option( 'dm_ipaddress', $_POST[ 'ipaddress' ] );

				if ( ! preg_match( '/(--|\.\.)/', $_POST[ 'cname' ] ) && preg_match( '|^([a-zA-Z0-9-\.])+$|', $_POST[ 'cname' ] ) )
					update_site_option( 'dm_cname', stripslashes( $_POST[ 'cname' ] ) );
				else
					update_site_option( 'dm_cname', '' );

				update_site_option( 'dm_301_redirect', intval( $_POST[ 'dm_301_redirect' ] ) );
			}
		}

		echo "<h3>" . __( 'Search Domains', 'wordpress-mu-domain-mapping' ) . "</h3>";
		echo '<form method="POST">';
		wp_nonce_field( 'domain_mapping' );
		echo '<input type="hidden" name="action" value="search" />';
		echo '<p>';
		echo _e( "Domain:", 'wordpress-mu-domain-mapping' );
		echo " <input type='text' name='domain' value='' /></p>";
		echo "<p><input type='submit' class='button-secondary' value='" . __( 'Search', 'wordpress-mu-domain-mapping' ) . "' /></p>";
		echo "</form><br />";

		$this->edit_domain();

		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->dmtable} ORDER BY id DESC LIMIT 0,20" );
		$this->domain_listing( $rows );

		echo '<p>' . sprintf( __( '<strong>Note:</strong> %s', 'wordpress-mu-domain-mapping' ), WordPress_MU_Domain_Mapping::idn_warning() ) . "</p>";
	}


	public function edit_domain( $row = false ) {
		if ( is_object( $row ) ) {
			echo '<h3>' . __( 'Edit Domain', 'wordpress-mu-domain-mapping' ) . '</h3>';
		}
		else {
			echo '<h3>' . __( 'New Domain', 'wordpress-mu-domain-mapping' ) . '</h3>';

			$row               = new stdClass();
			$row->blog_id      = '';
			$row->domain       = '';
			$_POST[ 'domain' ] = '';

			$row->active = 1;
		}

		echo "<form method='POST'><input type='hidden' name='action' value='save' /><input type='hidden' name='orig_domain' value='" . esc_attr( $_POST[ 'domain' ] ) . "' />";

		wp_nonce_field( 'domain_mapping' );

		echo "<table class='form-table'>\n";
		echo "<tr><th>" . __( 'Site ID', 'wordpress-mu-domain-mapping' ) . "</th><td><input type='text' name='blog_id' value='{$row->blog_id}' /></td></tr>\n";
		echo "<tr><th>" . __( 'Domain', 'wordpress-mu-domain-mapping' ) . "</th><td><input type='text' name='domain' value='{$row->domain}' /></td></tr>\n";
		echo "<tr><th>" . __( 'Primary', 'wordpress-mu-domain-mapping' ) . "</th><td><input type='checkbox' name='active' value='1' ";
		echo $row->active == 1 ? 'checked=1 ' : ' ';
		echo "/></td></tr>\n";

		if ( get_site_option( 'dm_no_primary_domain' ) == 1 )
			echo "<tr><td colspan='2'>" . __( '<strong>Warning!</strong> Primary domains are currently disabled.', 'wordpress-mu-domain-mapping' ) . "</td></tr>";

		echo "</table>";
		echo "<p><input type='submit' class='button-primary' value='" .__( 'Save', 'wordpress-mu-domain-mapping' ). "' /></p></form><br /><br />";
	}

	public function domain_listing( $rows, $heading = '' ) {
		if ( $rows ) {
			if ( file_exists( ABSPATH . 'wp-admin/network/site-info.php' ) ) {
				$edit_url = network_admin_url( 'site-info.php' );
			}
			elseif ( file_exists( ABSPATH . 'wp-admin/ms-sites.php' ) ) {
				$edit_url = admin_url( 'ms-sites.php' );
			}
			else {
				$edit_url = admin_url( 'wpmu-blogs.php' );
			}

			if ( $heading != '' )
				echo "<h3>$heading</h3>";

			echo '<table class="widefat" cellspacing="0"><thead><tr><th>'.__( 'Site ID', 'wordpress-mu-domain-mapping' ).'</th><th>'.__( 'Domain', 'wordpress-mu-domain-mapping' ).'</th><th>'.__( 'Primary', 'wordpress-mu-domain-mapping' ).'</th><th>'.__( 'Edit', 'wordpress-mu-domain-mapping' ).'</th><th>'.__( 'Delete', 'wordpress-mu-domain-mapping' ).'</th></tr></thead><tbody>';

			foreach( $rows as $row ) {
				echo "<tr><td><a href='" . add_query_arg( array( 'action' => 'editblog', 'id' => $row->blog_id ), $edit_url ) . "'>{$row->blog_id}</a></td><td><a href='http://{$row->domain}/'>{$row->domain}</a></td><td>";
				echo $row->active == 1 ? __( 'Yes',  'wordpress-mu-domain-mapping' ) : __( 'No',  'wordpress-mu-domain-mapping' );
				echo "</td><td><form method='POST'><input type='hidden' name='action' value='edit' /><input type='hidden' name='domain' value='{$row->domain}' />";
				wp_nonce_field( 'domain_mapping' );
				echo "<input type='submit' class='button-secondary' value='" .__( 'Edit', 'wordpress-mu-domain-mapping' ). "' /></form></td><td><form method='POST'><input type='hidden' name='action' value='del' /><input type='hidden' name='domain' value='{$row->domain}' />";
				wp_nonce_field( 'domain_mapping' );
				echo "<input type='submit' class='button-secondary' value='" .__( 'Del', 'wordpress-mu-domain-mapping' ). "' /></form>";
				echo "</td></tr>";
			}

			echo '</table>';

			if ( get_site_option( 'dm_no_primary_domain' ) == 1 )
				echo "<p>" . __( '<strong>Warning!</strong> Primary domains are currently disabled.', 'wordpress-mu-domain-mapping' ) . "</p>";
		}
	}


	function maybe_create_db() {
		global $wpdb;

		WordPress_MU_Domain_Mapping::get_hash(); // initialise the remote login hash

		$wpdb->dmtable       = $wpdb->base_prefix . 'domain_mapping';
		$wpdb->dmtablelogins = $wpdb->base_prefix . 'domain_mapping_logins';

		if ( is_super_admin() ) {
			$created = 0;

			if ( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->dmtable}'") != $wpdb->dmtable ) {
				$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->dmtable}` (
					`id` bigint(20) NOT NULL auto_increment,
					`blog_id` bigint(20) NOT NULL,
					`domain` varchar(255) NOT NULL,
					`active` tinyint(4) default '1',
					PRIMARY KEY  (`id`),
					KEY `blog_id` (`blog_id`,`domain`,`active`)
				);" );

				$created = 1;
			}

			if ( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->dmtablelogins}'") != $wpdb->dmtablelogins ) {
				$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->dmtablelogins}` (
					`id` varchar(32) NOT NULL,
					`user_id` bigint(20) NOT NULL,
					`blog_id` bigint(20) NOT NULL,
					`t` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
					PRIMARY KEY  (`id`)
				);" );

				$created = 1;
			}

			if ( $created )
				echo '<div id="message" class="updated fade"><p><strong>' . __( 'Domain mapping database table created.', 'wordpress-mu-domain-mapping' ) . '</strong></p></div>';

		}
	}


	public function sites_columns( $columns ) {
		$columns[ 'map' ] = __( 'Mapping', 'wordpress-mu-domain-mapping' );

		return $columns;
	}

	public function sites_custom_column( $column, $blog_id ) {
		global $wpdb;
		static $maps = false;
		
		if ( $column == 'map' ) {
			if ( $maps === false ) {
				$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
				$work = $wpdb->get_results( "SELECT blog_id, domain FROM {$wpdb->dmtable} ORDER BY blog_id" );
				$maps = array();

				if( $work ) {
					foreach( $work as $blog ) {
						$maps[ $blog->blog_id ][] = $blog->domain;
					}
				}
			}

			if( ! empty( $maps[ $blog_id ] ) && is_array( $maps[ $blog_id ] ) ) {
				foreach( $maps[ $blog_id ] as $blog ) {
					echo $blog . '<br />';
				}
			}
		}
	}

}
