<?php # -*- coding: utf-8 -*-
/*
* Plugin Name: Cat Generator Avatars
* Plugin URI:  https://wordpress.org/plugins/cat-generator-avatars/
* Description: This plugin integrates the Cat Generator Avatars avatar placeholder service into WordPress (and now BuddyPress).
* Author:      Abner Chou
* Author URI:  http://en.abnerchou.me
* Artist:      David Revoy
* Artist URI:	http://www.peppercarrot.com/
* Version:     2.1.1
* Text Domain: cat-generator-avatars
* License:     BSD-3
*/

namespace abnerchou\CatGeneratorAvatars;

if ( ! function_exists( 'add_action' ) ) {
    return;
}

/**
* Bootstraps the plugin.
*
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
    add_filter( 'get_avatar', [ $avatar, 'filter_avatar' ], 199999, 6 );

    //stop BuddyPress searching Gravatar
    add_filter( 'bp_core_fetch_avatar_no_grav', '__return_true' );

    // add filter to bp_core_fetch_avatar:
    add_filter('bp_core_fetch_avatar', array($avatar, 'set_buddypress_avatar'), 10, 2); // this is used for every avatar call except the anonymous comment posters

    // filter just the avatar URL:
    add_filter('bp_core_fetch_avatar_url', array($avatar, 'set_buddypress_avatar_url'), 10, 2);


}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
