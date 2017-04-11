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

				//JM: check $avatar is not a custom uploaded image, previously custom images were ignored in user listings
				//and code went on to overwrite with cat-generator image
				if (stripos($avatar, 'wp-content/uploads/') !== false) {
            return $avatar;
        }
				//JM: gravatar may be (should be) disabled so this check should be removed?
				//in particular this check slows down the user listing...
        //if ( $this->validate_gravatar($id_or_email) ) {
        //    return $avatar;
        //}

        $id = $this->get_identifier($id_or_email);
        $cachepath = $this->pluginfolder.''.$this->cachefolder;
        $cachefile = ''.$cachepath.''.$id.'.png';

        if (! file_exists($cachefile) ) {
            $this->build_monster($id);
        }

        $url = plugins_url().'/cat-generator-avatars'.$this->cachefolder.''.$id.'.png';

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
	* This method is used to filter just the avatar URL. Basically the same as set_buddypress_avatar(),
    * but it does not return the full <img /> tag, it just returns the image URL
	*
	* @param string $image_url
	* @param array $params
	*
	* @return string
	*/
    public function get_avatar_url($id_or_email, $size){

        $id = $this->get_identifier($id_or_email);
        $cachepath = $this->pluginfolder.''.$this->cachefolder;
        $cachefile = ''.$cachepath.''.$id.'.png';

        if (! file_exists($cachefile) ) {
            $this->build_monster($id);
        }

        $url = plugins_url().'/cat-generator-avatars'.$this->cachefolder.''.$id.'.png';
        return $url;
    }
    /**
    * This method is used to filter every avatar, except for anonymous comments.
    * It returns full <img /> HTML tag
    *
    * @param string $html_data
    * @param array $params
    *
    * @return string
    */
    public function set_buddypress_avatar($html_data = '', $params = array()){

        if (empty($params)){ // data not supplied
            return $html_data; // return original image
        }

			//if we got here because user is submitting a new image,
			if ( isset( $_POST['avatar-crop-submit'] ) ) {
				return $html_data; // return original image
			}

        // these params are very well documented in BuddyPress' bp-core-avatar.php file:
        $id = $params['item_id'];
        $object = $params['object'];
        $size = $params['width'];
        $alt = $params['alt'];
        $email = $params['email'];

        if ($object == 'user'){ // if we are filtering user's avatar

            if (empty($id) && $id !== 0){ // if id not specified (and id not equal 0)
                if (is_user_logged_in()){ // if user logged in
                    $user = get_user_by('id', get_current_user_id());
                    $id = get_current_user_id(); // get current user's id
                } else {
                    return $html_data; // no id specified and user not logged in - return the original image
                }
            }

        } else if ($object == 'group'){ // we're filtering group
            return $html_data;
        } else if ($object == 'blog'){ // we're filtering blog
            return $html_data;	// this feature is not used at all, so just return the input parameter
        } else { // not user, not group and not blog - just return the input html image
            return $html_data;
        }

			if (stripos($html_data, 'wp-content/uploads/avatar') !== false){
				return $html_data;
			}else{
				$cat_uri = $this->get_avatar_url($id, $size); // get URL

        $avatar_img_output = $this->generate_avatar_img_tag($cat_uri, $size, $alt); // get final <img /> tag for the avatar/gravatar

        return $avatar_img_output;
			}
		}

		/**
		* This method is used to filter just the avatar URL. Basically the same as set_buddypress_avatar(),
		* but it does not return the full <img /> tag, it just returns the image URL
		*
		* @param string $image_url
		* @param array $params
		*
		* @return string
		*/
		public function set_buddypress_avatar_url($image_url = '', $params = array()) {

			//if we got here because user is submitting a new image,
			if ( isset( $_POST['avatar-crop-submit'] ) ) {
				return $image_url; // return original image
			}

			$user_id = $params['item_id'];
			$size = $params['width'];
			$email = $params['email'];

			if (!is_numeric($user_id)){ // user_id was not passed, so we cannot do anything about this avatar
				return $image_url;
			}



			// if there is already a gravatar image or local upload, user has set his own profile avatar,
			// in which case, just return the input data and leave the avatar as it was:
			if ((stripos($image_url, 'gravatar.com/avatar') !== false) || (stripos($image_url, 'wp-content/uploads/') !== false)) {
				return $image_url;
			}
			if (empty($size)){ // if for some reason size was not specified...
				$size = 48; // just set it to 48
			}
			if ( ($image_url==='' ) || (stripos($image_url, 'cat-generator') !== false ) ||  (stripos($image_url, 'mystery-man') !== false ) ) {
				return get_avatar_url($user_id, $size);
			}else{
//				return get_avatar_url($user_id, $size);
				return $image_url;
			}
		}

    /**
    * Generate full HTML <img /> tag with avatar URL, size, CSS classes etc.
    *
    * @param string $avatar_uri
    * @param string $size
    * @param string $alt
    * @param array $args
    *
    * @return string
    */
    private function generate_avatar_img_tag($avatar_uri, $size, $alt = '', $args = array()){

     $avatar = sprintf(
     '<img src="%1$s" srcset="%2$s 2x" width="%3$d" height="%3$d" class="%4$s" alt="%5$s" %6$s>',
     esc_url( $avatar_uri ),
     esc_url( $avatar_uri ),
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
        //imagefill($monster,0,0,$white);
				imagefilledrectangle($monster, 0, 0, 256, 256, $white);

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
        $cachefile = ''.$cachepath.''.$seed.'.png';

        // Save/cache the output to a file
        $savedfile = fopen($cachefile, 'w+'); # w+ to be at start of the file, write mode, and attempt to create if not existing.

        //imagejpeg($monster, $savedfile);
				imagepng($monster, $cachefile, 1, PNG_NO_FILTER);

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
//    private function validate_gravatar($id_or_email) {
//        //id or email code borrowed from wp-includes/pluggable.php
//        $email = '';
//        if ( is_numeric($id_or_email) ) {
//            $id = (int) $id_or_email;
//            $user = get_userdata($id);
//            if ( $user )
//                $email = $user->user_email;
//        } elseif ( is_object($id_or_email) ) {
//            // No avatar for pingbacks or trackbacks
//            $allowed_comment_types = apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
//            if ( ! empty( $id_or_email->comment_type ) && ! in_array( $id_or_email->comment_type, (array) $allowed_comment_types ) )
//                return false;
//
//            if ( !empty($id_or_email->user_id) ) {
//                $id = (int) $id_or_email->user_id;
//                $user = get_userdata($id);
//                if ( $user)
//                    $email = $user->user_email;
//            } elseif ( !empty($id_or_email->comment_author_email) ) {
//                $email = $id_or_email->comment_author_email;
//            }
//        } else {
//            $email = $id_or_email;
//        }
//
//        $hashkey = md5(strtolower(trim($email)));
//        $uri = 'http://www.gravatar.com/avatar/' . $hashkey . '?d=404';
//
//        $data = wp_cache_get($hashkey);
//        if (false === $data) {
//            $response = wp_remote_head($uri);
//            if( is_wp_error($response) ) {
//                $data = 'not200';
//            } else {
//                $data = $response['response']['code'];
//            }
//            wp_cache_set($hashkey, $data, $group = '', $expire = 60*5);
//
//        }
//        if ($data == '200'){
//            return true;
//        } else {
//            return false;
//        }
//    }
}
