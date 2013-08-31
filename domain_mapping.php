<?php
/*
Plugin Name: WordPress MU Domain Mapping
Plugin URI: http://ocaoimh.ie/wordpress-mu-domain-mapping/
Description: Map any blog on a WordPress website to another domain.
Version: 0.5.4.3
Author: Donncha O Caoimh
Author URI: http://ocaoimh.ie/

text-domain: wordpress-mu-domain-mapping
*/

/*  Copyright Donncha O Caoimh (http://ocaoimh.ie/)
    With contributions by Ron Rennick(http://wpmututorials.com/), Greg Sidberry and others.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class WordPress_MU_Domain_Mapping {

	public function __construct() {
		if( is_admin() ) {
			include 'inc/admin.php';
			new WordPress_MU_Domain_Mapping_Admin();
		}

		if( is_network_admin() ) {
			include 'inc/admin-network.php';
			new WordPress_MU_Domain_Mapping_Admin_Network();
		}


		add_action( 'init', array( $this, 'load_text_domain' ) );
		add_action( 'template_redirect', array( $this, 'redirect_to_mapped_domain' ) );
		add_action( 'delete_blog', array( $this, 'delete_blog_domain_mapping' ), 1, 2 );


		if ( defined( 'DOMAIN_MAPPING' ) ) {
			add_filter( 'pre_option_siteurl', array( $this, 'domain_mapping_siteurl' ) );
			add_filter( 'pre_option_home', array( $this, 'domain_mapping_siteurl' ) );

			add_filter( 'the_content', array( $this, 'domain_mapping_post_content' ) );
			add_action( 'wp_head', array( $this, 'remote_login_js_loader' ) );
			add_action( 'login_head', array( $this, 'redirect_login_to_orig' ) );
			add_action( 'wp_logout', array( $this, 'remote_logout_loader' ), 9999 );

			add_filter( 'stylesheet_uri', array( $this, 'domain_mapping_post_content' ) );
			add_filter( 'stylesheet_directory', array( $this, 'domain_mapping_post_content' ) );
			add_filter( 'stylesheet_directory_uri', array( $this, 'domain_mapping_post_content' ) );
			add_filter( 'template_directory', array( $this, 'domain_mapping_post_content' ) );
			add_filter( 'template_directory_uri', array( $this, 'domain_mapping_post_content' ) );
			add_filter( 'plugins_url', array( $this, 'domain_mapping_post_content' ) );
		}
		else {
			add_filter( 'admin_url', array( $this, 'domain_mapping_adminurl' ), 10, 3 );
		}

		if ( isset( $_GET[ 'dm' ] ) )
			add_action( 'template_redirect', array( $this, 'remote_login_js' ) );
	}



	public function load_text_domain() {
		load_plugin_textdomain( 'wordpress-mu-domain-mapping', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}



	public static function get_hash() {
		$remote_login_hash = get_site_option( 'dm_hash' );

		if ( null == $remote_login_hash ) {
			$remote_login_hash = md5( time() );
			update_site_option( 'dm_hash', $remote_login_hash );
		}

		return $remote_login_hash;
	}

	public static function sunrise_warning( $die = true ) {
		if ( ! file_exists( WP_CONTENT_DIR . '/sunrise.php' ) ) {
			if ( ! $die )
				return true;

			if ( is_super_admin() )
				wp_die( sprintf( __( "Please copy sunrise.php to %s/sunrise.php and ensure the SUNRISE definition is in %swp-config.php", 'wordpress-mu-domain-mapping' ), WP_CONTENT_DIR, ABSPATH ) );
			else
				wp_die( __( "This plugin has not been configured correctly yet.", 'wordpress-mu-domain-mapping' ) );
		}
		elseif ( ! defined( 'SUNRISE' ) ) {
			if ( ! $die )
				return true;

			if ( is_super_admin() )
				wp_die( sprintf( __( "Please uncomment the line <em>define( 'SUNRISE', 'on' );</em> or add it to your %swp-config.php", 'wordpress-mu-domain-mapping' ), ABSPATH ) );
			else
				wp_die( __( "This plugin has not been configured correctly yet.", 'wordpress-mu-domain-mapping' ) );
		}
		elseif ( ! defined( 'SUNRISE_LOADED' ) ) {
			if ( ! $die )
				return true;

			if ( is_super_admin() )
				wp_die( sprintf( __( "Please edit your %swp-config.php and move the line <em>define( 'SUNRISE', 'on' );</em> above the last require_once() in that file or make sure you updated sunrise.php.", 'wordpress-mu-domain-mapping' ), ABSPATH ) );
			else
				wp_die( __( "This plugin has not been configured correctly yet.", 'wordpress-mu-domain-mapping' ) );
		}

		return false;
	}

	public static function idn_warning() {
		return sprintf( __( 'International Domain Names should be in <a href="%s">punycode</a> format.', 'wordpress-mu-domain-mapping' ), 'http://api.webnic.cc/idnconversion.html' );
	}



	public function redirect_to_mapped_domain() {
		global $current_blog, $wpdb, $wp_customize;

		// don't redirect the main site
		if ( is_main_site() )
			return;

		// don't redirect post previews
		if ( isset( $_GET['preview'] ) && $_GET['preview'] == 'true' )
			return;

		// don't redirect theme customizer (WP 3.4)
		if ( is_a( $wp_customize, 'WP_Customize_Manager' ) )
			return;

		$protocol = is_ssl() ? 'https://' : 'http://';
		$url      = $this->domain_mapping_siteurl( false );

		if ( $url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_blog->path ) ) {
			$redirect = get_site_option( 'dm_301_redirect' ) ? '301' : '302';

			if ( ( defined( 'VHOST' ) && constant( "VHOST" ) != 'yes' ) || ( defined( 'SUBDOMAIN_INSTALL' ) && constant( 'SUBDOMAIN_INSTALL' ) == false ) )
				$_SERVER[ 'REQUEST_URI' ] = str_replace( $current_blog->path, '/', $_SERVER[ 'REQUEST_URI' ] );

			header( "Location: {$url}{$_SERVER[ 'REQUEST_URI' ]}", true, $redirect );
			exit;
		}
	}

	function delete_blog_domain_mapping( $blog_id, $drop ) {
		global $wpdb;

		$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';

		if ( $blog_id && $drop ) {
			// Get an array of domain names to pass onto any delete_blog_domain_mapping actions
			$domains = $wpdb->get_col( $wpdb->prepare( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id  = %d", $blog_id ) );

			do_action('dm_delete_blog_domain_mappings', $domains);
			
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->dmtable} WHERE blog_id  = %d", $blog_id ) );
		}
	}


	public function domain_mapping_siteurl( $setting ) {
		global $wpdb, $current_site;

		// To reduce the number of database queries, save the results the first time we encounter each blog ID.
		static $return_url = array();

		$option_key = 'home';

		if( 'pre_option_siteurl' == current_filter() )
			$option_key = 'siteurl';

		$wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';

		if ( ! isset( $return_url[ $wpdb->blogid ] ) || ! isset( $return_url[ $wpdb->blogid ][ $option_key ] ) ) {
			if( ! isset( $return_url[ $wpdb->blogid ] ) )
				$return_url[ $wpdb->blogid ] = array();

			$s = $wpdb->suppress_errors();

			if ( get_site_option( 'dm_no_primary_domain' ) == 1 ) {
				$domain = $wpdb->get_var( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}' AND domain = '" . $wpdb->escape( $_SERVER[ 'HTTP_HOST' ] ) . "' LIMIT 1" );

				if ( null == $domain ) {
					$return_url[ $wpdb->blogid ][ $option_key ] = untrailingslashit( $this->get_original_url( $option_key ) );
					return $return_url[ $wpdb->blogid ][ $option_key ];
				}
			}
			else {
				// get primary domain, if we don't have one then return original url.
				$domain = $wpdb->get_var( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = '{$wpdb->blogid}' AND active = 1 LIMIT 1" );

				if ( null == $domain ) {
					$return_url[ $wpdb->blogid ][ $option_key ] = untrailingslashit( $this->get_original_url( $option_key ) );
					return $return_url[ $wpdb->blogid ][ $option_key ];
				}
			}

			$wpdb->suppress_errors( $s );

			$protocol = is_ssl() ? 'https://' : 'http://'; 

			if ( $domain ) {
				if( 'siteurl' == $option_key )
					$return_url[ $wpdb->blogid ][ $option_key ] = untrailingslashit( $protocol . $domain . $current_site->path );
				else 
					$return_url[ $wpdb->blogid ][ $option_key ] = untrailingslashit( $protocol . $domain );

				$setting = $return_url[ $wpdb->blogid ][ $option_key ];
			}
			else {
				$return_url[ $wpdb->blogid ][ $option_key ] = false;
			}
		}
		elseif ( $return_url[ $wpdb->blogid ][ $option_key ] !== FALSE ) {
			$setting = $return_url[ $wpdb->blogid ][ $option_key ];
		}

		return $setting;
	}

	// url is siteurl or home
	public static function get_original_url( $url, $blog_id = 0 ) {
		global $wpdb, $wordPress_mu_domain_mapping;

		if ( $blog_id != 0 )
			$id = $blog_id;
		else
			$id = $wpdb->blogid;

		if( 'home' != $url && 'siteurl' != $url )
			$url = 'siteurl';

		static $orig_urls = array();

		if ( ! isset( $orig_urls[ $id ] ) || ! isset( $orig_urls[ $id ][ $url ] ) ) {
			if ( defined( 'DOMAIN_MAPPING' ) ) 
				remove_filter( 'pre_option_' . $url, array( $wordPress_mu_domain_mapping, 'domain_mapping_siteurl' ) );

			if ( $blog_id == 0 )
				$orig_url = get_option( $url );
			else
				$orig_url = get_blog_option( $blog_id, $url );

			if ( is_ssl() )
				$orig_url = str_replace( "http://", "https://", $orig_url );
			else
				$orig_url = str_replace( "https://", "http://", $orig_url );

			if( ! isset( $orig_urls[ $id ] ) )
				$orig_urls[ $id ] = array();

			$orig_urls[ $id ][ $url ] = $orig_url;

			if ( defined( 'DOMAIN_MAPPING' ) ) 
				add_filter( 'pre_option_' . $url, array( $wordPress_mu_domain_mapping, 'domain_mapping_siteurl' ) );
		}

		return $orig_urls[ $id ][ $url ];
	}

	public function domain_mapping_post_content( $post_content ) {
		global $wpdb;

		$orig_url = $this->get_original_url( 'siteurl' );
		$url      = $this->domain_mapping_siteurl( 'NA' );

		if ( $url == 'NA' )
			return $post_content;

		return str_replace( $orig_url, $url, $post_content );
	}

	public function remote_login_js_loader() {
		global $current_site, $current_blog;

		if ( 0 == get_site_option( 'dm_remote_login' ) || is_user_logged_in() )
			return false;

		$protocol = is_ssl() ? 'https://' : 'http://';
		$hash     = $this->get_hash();

		echo "<script src='{$protocol}{$current_site->domain}{$current_site->path}?dm={$hash}&amp;action=load&amp;blogid={$current_blog->blog_id}&amp;siteid={$current_blog->site_id}&amp;t=" . mt_rand() . "&amp;back=" . urlencode( $protocol . $current_blog->domain . $_SERVER[ 'REQUEST_URI' ] ) . "' type='text/javascript'></script>";
	}

	public function redirect_login_to_orig() {
		if ( ! get_site_option( 'dm_remote_login' ) || ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'logout' ) || isset( $_GET[ 'loggedout' ] ) )
			return false;

		$url = $this->get_original_url( 'siteurl' );

		if ( $url != site_url() ) {
			$url .= "/wp-login.php";
			echo "<script type='text/javascript'>\nwindow.location = '$url'</script>";
		}
	}

	public function remote_logout_loader() {
		global $current_site, $current_blog, $wpdb;

		$wpdb->dmtablelogins = $wpdb->base_prefix . 'domain_mapping_logins';

		$protocol = is_ssl() ? 'https://' : 'http://';
		$hash     = $this->get_hash();
		$key      = md5( time() );

		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->dmtablelogins} ( `id`, `user_id`, `blog_id`, `t` ) VALUES( %s, 0, %d, NOW() )", $key, $current_blog->blog_id ) );

		if ( get_site_option( 'dm_redirect_admin' ) ) {
			wp_redirect( $protocol . $current_site->domain . $current_site->path . "?dm={$hash}&action=logout&blogid={$current_blog->blog_id}&k={$key}&t=" . mt_rand() );
			exit;
		} 
	}



	public function domain_mapping_adminurl( $url, $path, $blog_id = 0 ) {
		$index = strpos( $url, '/wp-admin' );

		if( $index !== false ) {
			$url = $this->get_original_url( 'siteurl', $blog_id ) . substr( $url, $index );

			// make sure admin_url is ssl if current page is ssl, or admin ssl is forced
			if( ( is_ssl() || force_ssl_admin() ) && 0 === strpos( $url, 'http://' ) )
				$url = 'https://' . substr( $url, 7 );
		}

		return $url;
	}



	public function remote_login_js() {
		global $current_blog, $current_user, $wpdb;

		if ( 0 == get_site_option( 'dm_remote_login' ) )
			return false;

		$wpdb->dmtablelogins = $wpdb->base_prefix . 'domain_mapping_logins';
		$hash     = $this->get_hash();
		$protocol = is_ssl() ? 'https://' : 'http://';

		if ( $_GET[ 'dm' ] == $hash ) {
			if ( $_GET[ 'action' ] == 'load' ) {
				if ( !is_user_logged_in() )
					exit;

				$key = md5( time() . mt_rand() );
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->dmtablelogins} ( `id`, `user_id`, `blog_id`, `t` ) VALUES( %s, %d, %d, NOW() )", $key, $current_user->ID, $_GET[ 'blogid' ] ) );
				$url = add_query_arg( array( 'action' => 'login', 'dm' => $hash, 'k' => $key, 't' => mt_rand() ), $_GET[ 'back' ] );

				echo "window.location = '$url'";
				exit;
			}
			elseif ( $_GET[ 'action' ] == 'login' ) {
				if ( $details = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->dmtablelogins} WHERE id = %s AND blog_id = %d", $_GET[ 'k' ], $wpdb->blogid ) ) ) {
					if ( $details->blog_id == $wpdb->blogid ) {
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->dmtablelogins} WHERE id = %s", $_GET[ 'k' ] ) );
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->dmtablelogins} WHERE t < %d", ( time() - 120 ) ) ); // remote logins survive for only 2 minutes if not used.

						wp_set_auth_cookie( $details->user_id );

						wp_redirect( remove_query_arg( array( 'dm', 'action', 'k', 't', $protocol . $current_blog->domain . $_SERVER[ 'REQUEST_URI' ] ) ) );
						exit;
					}
					else {
						wp_die( __( "Incorrect or out of date login key", 'wordpress-mu-domain-mapping' ) );
					}
				}
				else {
					wp_die( __( "Unknown login key", 'wordpress-mu-domain-mapping' ) );
				}
			}
			elseif ( $_GET[ 'action' ] == 'logout' ) {
				if ( $details = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->dmtablelogins} WHERE id = %d AND blog_id = %d", $_GET[ 'k' ], $_GET[ 'blogid' ] ) ) ) {
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->dmtablelogins} WHERE id = %s", $_GET[ 'k' ] ) );
					$blog = get_blog_details( $_GET[ 'blogid' ] );
					wp_clear_auth_cookie();

					wp_redirect( trailingslashit( $blog->siteurl ) . "wp-login.php?loggedout=true" );
					exit;
				}
				else {
					wp_die( __( "Unknown logout key", 'wordpress-mu-domain-mapping' ) );
				}
			}
		}
	}

}

$wordPress_mu_domain_mapping = new WordPress_MU_Domain_Mapping;