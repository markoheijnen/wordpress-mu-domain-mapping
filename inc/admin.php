<?php

class WordPress_MU_Domain_Mapping_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_action( 'admin_init', array( $this, 'redirect_admin' ) );

		if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'domainmapping' )
			add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	public function add_pages() {
		global $current_site, $wpdb, $wp_db_version, $wp_version;

		if ( ! isset( $current_site ) ) {
			add_action( 'admin_notices', 'domain_mapping_warning' );
			return false;
		}

		if ( $current_site->path != "/" )
			wp_die( __( 'The domain mapping plugin only works if the site is installed in /. This is a limitation of how virtual servers work and is very difficult to work around.', 'wordpress-mu-domain-mapping' ) );

		if ( get_site_option( 'dm_user_settings' ) && $current_site->blog_id != $wpdb->blogid && ! WordPress_MU_Domain_Mapping::sunrise_warning( false ) )
			add_management_page(__( 'Domain Mapping', 'wordpress-mu-domain-mapping'), __( 'Domain Mapping', 'wordpress-mu-domain-mapping'), 'manage_options', 'domainmapping', array( $this, 'manage_page' ) );
	}


	public function domain_mapping_warning() {
		echo '<div id="domain-mapping-warning" class="updated fade"><p>';
		echo '<strong>' . __( 'Domain Mapping Disabled.', 'wordpress-mu-domain-mapping' ) . '</strong>';
		echo sprintf( __( 'You must <a href="%1$s">create a network</a> for it to work.', 'wordpress-mu-domain-mapping' ), 'http://codex.wordpress.org/Create_A_Network' );
		echo '</p></div>';
	}

	public function manage_page() {
		global $wpdb, $parent_file;

		if ( isset( $_GET[ 'updated' ] ) ) {
			switch( $_GET[ 'updated' ] ) {
				case "add":
					$msg = __( 'New domain added.', 'wordpress-mu-domain-mapping' );
					break;
				case "exists":
					$msg = __( 'New domain already exists.', 'wordpress-mu-domain-mapping' );
					break;
				case "primary":
					$msg = __( 'New primary domain.', 'wordpress-mu-domain-mapping' );
					break;
				case "del":
					$msg = __( 'Domain deleted.', 'wordpress-mu-domain-mapping' );
					break;
				default:
					$_GET[ 'updated' ] = '';
					break;
			}

			echo '<div class="updated fade"><p>' . apply_filters( 'dm_echo_updated_msg', $msg, $_GET[ 'updated' ] )  . '</p></div>';
		}

		WordPress_MU_Domain_Mapping::sunrise_warning();

		echo "<div class='wrap'><h2>" . __( 'Domain Mapping', 'wordpress-mu-domain-mapping' ) . "</h2>";

		if ( false == get_site_option( 'dm_ipaddress' ) && false == get_site_option( 'dm_cname' ) ) {
			if ( is_super_admin() )
				_e( "Please set the IP address or CNAME of your server in the <a href='wpmu-admin.php?page=dm_admin_page'>site admin page</a>.", 'wordpress-mu-domain-mapping' );
			else
				_e( "This plugin has not been configured correctly yet.", 'wordpress-mu-domain-mapping' );

			echo "</div>";
			return false;
		}

		$protocol = is_ssl() ? 'https://' : 'http://'; 

		$domains = $wpdb->get_results( "SELECT * FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}'", ARRAY_A );

		if ( is_array( $domains ) && ! empty( $domains ) ) {
			$orig_url  = parse_url( WordPress_MU_Domain_Mapping::get_original_url( 'siteurl' ) );
			$domains[] = array( 'domain' => $orig_url[ 'host' ], 'path' => $orig_url[ 'path' ], 'active' => 0 );

			echo "<h3>" . __( 'Active domains on this blog', 'wordpress-mu-domain-mapping' ) . "</h3>";
			echo '<form method="POST">';
			echo "<table><tr><th>" . __( 'Primary', 'wordpress-mu-domain-mapping' ) . "</th><th>" . __( 'Domain', 'wordpress-mu-domain-mapping' ) . "</th><th>" . __( 'Delete', 'wordpress-mu-domain-mapping' ) . "</th></tr>\n";

			$primary_found = 0;
			$del_url = add_query_arg( array( 'page' => 'domainmapping', 'action' => 'delete' ), admin_url( $parent_file ) );

			foreach( $domains as $details ) {
				if ( 0 == $primary_found && $details[ 'domain' ] == $orig_url[ 'host' ] )
					$details[ 'active' ] = 1;

				$url = $protocol . $details[ 'domain' ];

				echo "<tr><td>";
				echo "<input type='radio' name='domain' value='{$details[ 'domain' ]}' ";

				if ( $details[ 'active' ] == 1 )
					echo "checked='1' ";

				echo "/>";
				echo "</td><td><a href='$url'>$url</a></td><td style='text-align: center'>";

				if ( $details[ 'domain' ] != $orig_url[ 'host' ] && $details[ 'active' ] != 1 )
					echo '<a href="' . wp_nonce_url( add_query_arg( array( 'domain' => $details[ 'domain' ] ), $del_url ), "delete" . $details[ 'domain' ] ) . '">Del</a>';

				echo "</td></tr>";

				if ( 0 == $primary_found )
					$primary_found = $details[ 'active' ];
			}

			echo '</table>';

			echo '<input type="hidden" name="action" value="primary" />';
			echo "<p><input type='submit' class='button-primary' value='" . __( 'Set Primary Domain', 'wordpress-mu-domain-mapping' ) . "' /></p>";

			wp_nonce_field( 'domain_mapping' );

			echo "</form>";
			echo "<p>" . __( "* The primary domain cannot be deleted.", 'wordpress-mu-domain-mapping' ) . "</p>";

			if ( get_site_option( 'dm_no_primary_domain' ) == 1 )
				echo __( '<strong>Warning!</strong> Primary domains are currently disabled.', 'wordpress-mu-domain-mapping' );

		}

		echo "<h3>" . __( 'Add new domain', 'wordpress-mu-domain-mapping' ) . "</h3>";
		echo '<form method="POST">';
		echo '<input type="hidden" name="action" value="add" />';
		echo "<p>http://<input type='text' name='domain' value='' />/<br />";

		wp_nonce_field( 'domain_mapping' );

		echo "<input type='checkbox' name='primary' value='1' /> " . __( 'Primary domain for this blog', 'wordpress-mu-domain-mapping' ) . "</p>";
		echo "<p><input type='submit' class='button-secondary' value='" . __( "Add", 'wordpress-mu-domain-mapping' ) . "' /></p>";
		echo "</form><br />";
		
		if ( get_site_option( 'dm_cname' ) ) {
			$dm_cname = get_site_option( 'dm_cname');
			echo "<p>" . sprintf( __( 'If you want to redirect a domain you will need to add a DNS "CNAME" record pointing to the following domain name for this server: <strong>%s</strong>', 'wordpress-mu-domain-mapping' ), $dm_cname ) . "</p>";
			echo "<p>" . __( 'Google have published <a href="http://www.google.com/support/blogger/bin/answer.py?hl=en&answer=58317" target="_blank">instructions</a> for creating CNAME records on various hosting platforms such as GoDaddy and others.', 'wordpress-mu-domain-mapping' ) . "</p>";
		}
		else {
			echo "<p>" . __( 'If your domain name includes a hostname like "www", "blog" or some other prefix before the actual domain name you will need to add a CNAME for that hostname in your DNS pointing at this blog URL.', 'wordpress-mu-domain-mapping' ) . "</p>";
			$dm_ipaddress = get_site_option( 'dm_ipaddress', 'IP not set by admin yet.' );

			if ( strpos( $dm_ipaddress, ',' ) )
				echo "<p>" . sprintf( __( 'If you want to redirect a domain you will need to add DNS "A" records pointing at the IP addresses of this server: <strong>%s</strong>', 'wordpress-mu-domain-mapping' ), $dm_ipaddress ) . "</p>";
			else
				echo "<p>" . sprintf( __( 'If you want to redirect a domain you will need to add a DNS "A" record pointing at the IP address of this server: <strong>%s</strong>', 'wordpress-mu-domain-mapping' ), $dm_ipaddress ) . "</p>";
		}

		echo '<p>' . sprintf( __( '<strong>Note:</strong> %s', 'wordpress-mu-domain-mapping' ), WordPress_MU_Domain_Mapping::idn_warning() ) . "</p>";
		echo "</div>";
	}


	function handle_actions() {
		global $wpdb, $parent_file;

		$url = add_query_arg( array( 'page' => 'domainmapping' ), admin_url( $parent_file ) );

		if ( ! empty( $_POST[ 'action' ] ) ) {
			$domain = $wpdb->escape( $_POST[ 'domain' ] );

			if ( $domain == '' )
				wp_die( __( 'You must enter a domain', 'wordpress-mu-domain-mapping' ) );

			check_admin_referer( 'domain_mapping' );
			do_action( 'dm_handle_actions_init', $domain );

			switch( $_POST[ 'action' ] ) {
				case "add":
					do_action( 'dm_handle_actions_add', $domain );

					if( null == $wpdb->get_row( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain = '$domain'" ) && null == $wpdb->get_row( "SELECT blog_id FROM {$wpdb->dmtable} WHERE domain = '$domain'" ) ) {
						$primary = 0;

						if ( isset( $_POST[ 'primary' ] ) ) {
							$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 0 WHERE blog_id = %d", $wpdb->blogid ) );
							$primary = 1;
						}

						$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->dmtable} ( `id` , `blog_id` , `domain` , `active` ) VALUES ( NULL, %d, %s, %d )", $wpdb->blogid, $domain, $primary ) );

						wp_redirect( add_query_arg( array( 'updated' => 'add' ), $url ) );
						exit;
					}
					else {
						wp_redirect( add_query_arg( array( 'updated' => 'exists' ), $url ) );
						exit;
					}
				break;
				case "primary":
					do_action('dm_handle_actions_primary', $domain);

					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 0 WHERE blog_id = %d", $wpdb->blogid ) );
					$orig_url = parse_url( WordPress_MU_Domain_Mapping::get_original_url( 'siteurl' ) );

					if( $domain != $orig_url[ 'host' ] )
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->dmtable} SET active = 1 WHERE domain = %s", $domain ) );

					wp_redirect( add_query_arg( array( 'updated' => 'primary' ), $url ) );
					exit;
				break;
			}
		}
		elseif( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'delete' ) {
			$domain = $wpdb->escape( $_GET[ 'domain' ] );

			if ( $domain == '' )
				wp_die( __( 'You must enter a domain', 'wordpress-mu-domain-mapping' ) );

			check_admin_referer( "delete" . $_GET['domain'] );
			do_action( 'dm_handle_actions_del', $domain );
			$wpdb->query( "DELETE FROM {$wpdb->dmtable} WHERE domain = '$domain'" );

			wp_redirect( add_query_arg( array( 'updated' => 'del' ), $url ) );
			exit;
		}
	}


	public function redirect_admin() {
		// don't redirect admin ajax calls
		if ( strpos( $_SERVER['REQUEST_URI'], 'wp-admin/admin-ajax.php' ) !== false )
			return;

		if ( get_site_option( 'dm_redirect_admin' ) ) {
			// redirect mapped domain admin page to original url
			$url = WordPress_MU_Domain_Mapping::get_original_url( 'siteurl' );

			if ( false === strpos( $url, $_SERVER[ 'HTTP_HOST' ] ) ) {
				wp_redirect( untrailingslashit( $url ) . $_SERVER[ 'REQUEST_URI' ] );
				exit;
			}
		}
		else {
			global $current_blog;

			// redirect original url to primary domain wp-admin/ - remote login is disabled!
			$url = domain_mapping_siteurl( false );
			$request_uri = str_replace( $current_blog->path, '/', $_SERVER[ 'REQUEST_URI' ] );

			if ( false === strpos( $url, $_SERVER[ 'HTTP_HOST' ] ) ) {
				wp_redirect( str_replace( '//wp-admin', '/wp-admin', trailingslashit( $url ) . $request_uri ) );
				exit;
			}
		}
	}

}
