<?php # -*- coding: utf-8 -*-
/*
 * Plugin Name: Cat Generator Avatars
 * Plugin URI:  https://wordpress.org/plugins/cat-generator-avatars/
 * Description: This plugin integrates the Cat Generator Avatars avatar placeholder service into WordPress.
 * Author:      Abner Chou
 * Author URI:  http://en.abnerchou.me
 * Artist:      David Revoy
 * Artist URI:	http://www.peppercarrot.com/
 * Version:     1.0.0
 * Text Domain: cat-generator-avatars
 * License:     MIT
 */

namespace abnerchou\CatGeneratorAvatars;

if ( ! function_exists( 'add_action' ) ) {
	return;
}

/**
 * Bootstraps the plugin.
 *
 * @since   1.0.0
 * @wp-hook plugins_loaded
 *
 * @return void
 */
function bootstrap() {

	/**
	 * Avatar model.
	 */
	require_once __DIR__ . '/src/Avatar.php';

	load_plugin_textdomain( 'cat-generator-avatars' );

	$avatar = new Avatar();
	add_filter( 'avatar_defaults', [ $avatar, 'add_to_defaults' ] );
	add_filter( 'get_avatar', [ $avatar, 'filter_avatar' ], 10, 6 );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
