<?php

/*
Plugin Name: WPThumb Srcset
Description: Automatic high resolution retina images using srcset.
Version: 1.0
Author: Human Made Limited
Author URI: http://hmn.md/
*/

/*  Copyright 2011 Human Made Limited  (email : hello@humanmade.co.uk)

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

class HM_WordPress_Srcset {

	private $plugin_url;
	private $multipliers;

	function __construct() {

		$this->plugin_url  = plugin_dir_url( __FILE__ );
		$this->multipliers = apply_filters( 'hm_wp_srcset', array( 2, 3 ) );

		register_activation_hook( __FILE__ , array( $this, 'plugin_activation_check' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_filter( 'image_downsize',  array( $this, 'image_downsize' ), 100, 3 );

		add_filter( 'image_send_to_editor', array( $this, 'image_send_to_editor' ), 100, 8 );
		add_filter( 'tiny_mce_before_init', array( $this, 'modify_mce_options' ), 100 );

	}

	/**
	 * plugin_activation_check()
	 *
	 * Replace "plugin" with the name of your plugin
	 */
	function plugin_activation_check() {

		if ( version_compare( PHP_VERSION, '5.4', '<' ) ) {
			deactivate_plugins( basename( __FILE__ ) );
			wp_die( "PHP 5.3 or higher is required to use this plugin." );
		}

		if ( ! class_exists( 'WP_Thumb' ) ) {
			deactivate_plugins( basename( __FILE__ ) );
			wp_die( "WP Thumb is required to use this plugin." );
		}

	}

	/**
	 * Enqueue Retina-fy scripts.
	 */
	function enqueue_scripts() {

		wp_enqueue_script( 'wpthumb_srcset', $this->plugin_url . '/srcset-polyfill/build/srcset.min.js', false, false, true );

	}


	function image_downsize( $null, $attachment_id, $size ) {

		add_filter( 'wp_get_attachment_image_attributes', $closure = function( $attr, $attachment ) use ( $attachment_id, $size, &$closure ) {

			remove_filter( 'wp_get_attachment_image_attributes', $closure );

			// Prevent firing this filter for all images on the page.
			if ( $attachment_id != $attachment->ID )
				return $attr;

			$requested_image = wp_get_attachment_image_src( $attachment_id, $size );
			$size_args = array( 'width' => $requested_image[1], 'height' => $requested_image[2] );

			$attr['src'] = $requested_image[0];

			$srcset = array();

			if ( $src = $this->get_alt_img_src( $attachment_id,  $size_args, 2 ) ) {
				array_push( $srcset, sprintf( '%s 2x', $src ) );
			}

			$attr['srcset'] = implode( ', ', $srcset );

			return $attr;

		}, 10, 2 );

	}

	/**
	 *	Add retina image attr to content images on insert
	 */
	function image_send_to_editor( $html, $attachment_id, $caption, $title, $align, $url, $size, $alt = '' ) {

		$requested_image = wp_get_attachment_image_src( $attachment_id, $size );
		$size_args       = array( 'width' => $requested_image[1], 'height' => $requested_image[2] );
		$srcset          = array();

		$attr['src'] = $requested_image[0];

		if ( $src = $this->get_alt_img_src( $attachment_id,  $size_args, 2 ) ) {
			array_push( $srcset, sprintf( '%s 2x', $src ) );
		}

		$html = preg_replace( '/src="\w*"/', 'src="' . $src . '"', $html );
		$html = str_replace( '/>', 'srcset="' . implode( ', ', $srcset ) . '" />', $html );

		return $html;

	}

	/**
	 * Get the src for an alternate sized version of an attachment.
	 *
	 * @param  string/int $attachment_id
	 * @param  Array  $size array of width, height args.
	 * @param  int $multiplier return src for image at x times the size.
	 * @return string src.
	 */
	function get_alt_img_src( $attachment_id, Array $size, $multiplier ) {

		$alt_size = array(
			$size['width']  * $multiplier,
			$size['height'] * $multiplier,
		);

		$alt_img = wp_get_attachment_image_src( $attachment_id, $alt_size );

		if ( $alt_img[1] != $alt_size[0] && $alt_img[2] != $alt_size[1] ) {
			return;
		}

		return $alt_img[0];

	}

	/**
	 * Add to extended_valid_elements for TinyMCE
	 *
	 * @param $init assoc. array of TinyMCE options
	 * @return $init the changed assoc. array
	 */
	function modify_mce_options( $init ) {

		// Command separated string of extended elements
		// I've set it to all - but maybe can modify defaults? If I only set the one I want, doesn't allow any others.
		$ext = 'img[*]';

		// Add to extended_valid_elements if it alreay exists
		if ( isset( $init['extended_valid_elements'] ) ) {
			$init['extended_valid_elements'] .= ',' . $ext;
		} else {
			$init['extended_valid_elements'] = $ext;
		}

		return $init;

	}

}

$retina = new HM_WordPress_Srcset();