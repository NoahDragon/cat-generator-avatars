<?php # -*- coding: utf-8 -*-

namespace abnerchou\CatGeneratorAvatars;

use WP_Comment;
use WP_Post;
use WP_User;

/**
* The avatar model.
*
* @package abnerchou\CatGeneratorAvatars
*/
class Avatar {

    /**
    * Avatar name.
    * @var string
    */
    const NAME = 'cat-generator-avatars';

    /**
    * @var string
    */
    private $cachefolder;

    /**
    * @var string
    */
    private $pluginfolder;
    
    /**
    * Constructor
    */
    public function __construct() {
        $this->pluginfolder = WP_PLUGIN_DIR . '/cat-generator-avatars/';
        $this->cachefolder = '/cache/';
    }
    
    /**
    * Adds "cat-generator-avatar" to the default avatars.
    *
    * @wp-hook avatar_defaults
    *
    * @param string[] $defaults Array of default avatars.
    *
    * @return string[] Array of default avatars, includeing "cat-generator Avatar".
    */
    public function add_to_defaults( array $defaults ) {

        $defaults = [ self::NAME => __( 'Cat Avatar (Generated)', 'cat-generator-avatars' ) ] + $defaults;

        return $defaults;
    }

    /**
    * Filters the avatar image tag.
    *
    * @wp-hook get_avatar
    *
    * @param string $avatar      Avatar image tag.
    * @param mixed  $id_or_email User identifier.
    * @param int    $size        Avatar size.
    * @param string $default     Avatar key.
    * @param string $alt         Alternative text to use in the avatar image tag.
    * @param array  $args        Avatar args.
    *
    * @return string Filtered avatar image tag.
    */
    public function filter_avatar( $avatar, $id_or_email, $size, $default, $alt, array $args ) {

        if ( $args['default'] && self::NAME !== $args['default'] ) {
            return $avatar;
        }

        if ( $this->validate_gravatar($id_or_email) ) {
            return $avatar;
        }

        $id = $this->get_identifier($id_or_email);
        $cachepath = $this->pluginfolder.''.$this->cachefolder;
        $cachefile = ''.$cachepath.''.$id.'.jpg';

        if (! file_exists($cachefile) ) {
            $this->build_monster($id);
        }

        $url = plugins_url().'/cat-generator-avatars'.$this->cachefolder.''.$id.'.jpg';

        $avatar = sprintf(
        '<img src="%1$s" srcset="%2$s 2x" width="%3$d" height="%3$d" class="%4$s" alt="%5$s" %6$s>',
        esc_url( $url ),
        esc_url( $url ),
        esc_attr( $size ),
        esc_attr( $this->get_class_value( $size, $args ) ),
        esc_attr( $alt ),
        isset( $args['extra_attr'] ) ? $args['extra_attr'] : ''
        );

        return $avatar;
    }

    /**
    * Returns the identifier string for the given user identifier.
    *
    * @param mixed $identifier User identifier.
    *
    * @return string The identifier string for the given user identifier.
    */
    private function get_identifier( $identifier ) {

        if ( is_numeric( $identifier ) ) {
            $identifier = get_user_by( 'id', $identifier );
        } elseif ( $identifier instanceof WP_Post ) {
            $identifier = get_user_by( 'id', $identifier->post_author );
        } elseif ( $identifier instanceof WP_Comment ) {
            $identifier = 0 < $identifier->user_id
            ? get_user_by( 'id', $identifier->user_id )
            : $identifier->comment_author_email;
        }

        if ( $identifier instanceof WP_User ) {
            $identifier = $identifier->user_email;
        } elseif ( ! is_string( $identifier ) ) {
            return '';
        }

        $identifier = substr(md5( strtolower( trim( $identifier ) ) ),0,6);

        return $identifier;
    }

    /**
    * Returns the avatar HTML class attribute value for the given avatar size and args.
    *
    * @param int   $size Avatar size.
    * @param array $args Avatar args.
    *
    * @return string The avatar HTML class attribute value
    */
    private function get_class_value( $size, array $args ) {

        $class = [
        'avatar',
        "avatar-$size",
        'cat-generator-avatar',
        'photo',
        ];

        if ( empty( $args['found_avatar'] ) || $args['force_default'] ) {
            $class[] = 'avatar-default';
        }

        if ( ! empty( $args['class'] ) ) {
            $class = array_unique( array_merge( $class, (array) $args['class'] ) );
        }

        return join( ' ', $class );
    }

    /**
    * Build the avatar image if not exists.
    *
    * @param string    $seed Input md5 string to use as seed.
    * @param int       $size Avatar size.
    *
    */
    private function build_monster($seed=''){
        // init random seed
        if($seed) srand( hexdec($seed) );

        // throw the dice for body parts
        $parts = array(
        'body' => rand(1,15),
        'fur' => rand(1,10),
        'eyes' => rand(1,15),
        'mouth' => rand(1,10),
        'accessorie' => rand(1,20)
        );

        // create backgound
        $monster = @imagecreatetruecolor(256, 256)
        or die("GD image create failed");
        $white   = imagecolorallocate($monster, 255, 255, 255);
        imagefill($monster,0,0,$white);

        // add parts
        foreach($parts as $part => $num){
            $file = $this->pluginfolder.'img/'.$part.'_'.$num.'.png';

            $im = @imagecreatefrompng($file);
            if(!$im) die('Failed to load '.$file);
            imageSaveAlpha($im, true);
            imagecopy($monster,$im,0,0,0,0,256,256);
            imagedestroy($im);
        }

        // restore random seed
        if($seed) srand();


        $cachepath = $this->pluginfolder.''.$this->cachefolder;
        $cachefile = ''.$cachepath.''.$seed.'.jpg';

        // Save/cache the output to a file
        $savedfile = fopen($cachefile, 'w+'); # w+ to be at start of the file, write mode, and attempt to create if not existing.

        imagejpeg($monster, $savedfile);
        imagedestroy($monster);
    }

    /**
    * Check if Gravatar exists.
    * From: https://gist.github.com/justinph/5197810
    *
    * @param string    $email User email address.
    *
    * @return boolean  true: Gravatar exits; false: Gravatar not exits.
    */
    private function validate_gravatar($id_or_email) {
        //id or email code borrowed from wp-includes/pluggable.php
        $email = '';
        if ( is_numeric($id_or_email) ) {
            $id = (int) $id_or_email;
            $user = get_userdata($id);
            if ( $user )
                $email = $user->user_email;
        } elseif ( is_object($id_or_email) ) {
            // No avatar for pingbacks or trackbacks
            $allowed_comment_types = apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
            if ( ! empty( $id_or_email->comment_type ) && ! in_array( $id_or_email->comment_type, (array) $allowed_comment_types ) )
                return false;

            if ( !empty($id_or_email->user_id) ) {
                $id = (int) $id_or_email->user_id;
                $user = get_userdata($id);
                if ( $user)
                    $email = $user->user_email;
            } elseif ( !empty($id_or_email->comment_author_email) ) {
                $email = $id_or_email->comment_author_email;
            }
        } else {
            $email = $id_or_email;
        }

        $hashkey = md5(strtolower(trim($email)));
        $uri = 'http://www.gravatar.com/avatar/' . $hashkey . '?d=404';

        $data = wp_cache_get($hashkey);
        if (false === $data) {
            $response = wp_remote_head($uri);
            if( is_wp_error($response) ) {
                $data = 'not200';
            } else {
                $data = $response['response']['code'];
            }
            wp_cache_set($hashkey, $data, $group = '', $expire = 60*5);

        }
        if ($data == '200'){
            return true;
        } else {
            return false;
        }
    }
}
