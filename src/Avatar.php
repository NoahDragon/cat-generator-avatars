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
     * Plugin name.
     * @var string
     */
    const NAME = 'cat-generator-avatars';
    /**
    * Avatar name.
    * @var string
    */
    const CATNAME = 'cat-generator';
    /**
     * Avatar name.
     * @var string
     */
    const BIRDNAME = 'bird-generator';
    /**
     * Avatar name.
     * @var string
     */
    const MIXNAME = 'mix-generator';
    /**
    * @var string
    */
    private $cachefolder;
    /**
    * @var string
    */
    private $pluginfolder;
    /**
     * @var bool
     */
    private $isCat = true;

    /**
    * Constructor
    */
    public function __construct() {
        $this->pluginfolder = WP_PLUGIN_DIR . '/' . self::NAME . '/';
        $this->cachefolder = 'cache/';
    }

    /**
    * Adds "cat-generator-avatar" to the default avatars.
    * @wp-hook avatar_defaults
    *
    * @param string[] $defaults Array of default avatars.
    *
    * @return string[] Array of default avatars, includeing "cat-generator Avatar".
    */
    public function add_to_defaults( array $defaults ) {
        $defaults = [ self::CATNAME => __( 'Cat Avatar (Generated)', 'cat-generator' ),
                      self::BIRDNAME => __( 'Bird Avatar (Generated)', 'bird-generator' ),
                      self::MIXNAME => __('Cat/Bird Avatar (Generated)', 'mix-generator')
                    ]
                    + $defaults;
        return $defaults;
    }

    /**
    * Filters the avatar image tag.
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
        if ( $args['default'] && self::CATNAME !== $args['default'] && self::BIRDNAME !== $args['default'] && self::MIXNAME !== $args['default']) return $avatar;

        //JM: check $avatar is not a custom uploaded image, previously custom images were ignored in user listings
        //and code went on to overwrite with cat-generator image
        if (stripos($avatar, 'wp-content/uploads/') !== false) return $avatar;

        if (self::MIXNAME == $args['default']) $this->isCat = NULL;
        if (self::BIRDNAME == $args['default']) $this->isCat = false;

        $url = $this->get_avatar_url($id_or_email, $size);
        return $this->generate_avatar_img_tag($url, $size, $args);
    }

    /**
    * This method is used to filter just the avatar URL. Basically the same as set_buddypress_avatar(),
    * but it does not return the full <img /> tag, it just returns the image URL
    *
    * @param string $id_or_email user ID
    * @param int $size width/height for square
    *
    * @return string
    */
    public function get_avatar_url($id_or_email, $size){
        $id = $this->get_identifier($id_or_email);
        $cachepath = $this->pluginfolder.$this->cachefolder;
        $cachefile = $cachepath.$id.'.png';

        if (! file_exists($cachefile) ) $this->build_avatar($id);

        $url = $this->vt_resize($cachefile, $size, $size);

        if (is_null($url)) return '';

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

        //if user is submitting a new image,
        if ( isset( $_POST['avatar-crop-submit'] ) ) {
            return $html_data; // return original image
        }

        // these params are very well documented in BuddyPress' bp-core-avatar.php file:
        $id = $params['item_id'];
        $object = $params['object'];
        $size = $params['width'];
        $alt = $params['alt'];
        $email = $params['email'];

        if ($object == 'user'){ // filtering user's avatar
            if (empty($id) && $id !== 0){ // if id not specified (and id not equal 0)
                if (is_user_logged_in()){ // if user logged in
                    $user = get_user_by('id', get_current_user_id());
                    $id = get_current_user_id(); // get current user's id
                } else {
                    return $html_data; // no id specified and user not logged in - return the original image
                }
            }
        } else if ($object == 'group'){ // filtering group
            return $html_data;
        } else if ($object == 'blog'){ // filtering blog
            return $html_data;	// this feature is not used at all, so just return the input parameter
        } else { // not user, not group and not blog - just return the input html image
            return $html_data;
        }

        if (stripos($html_data, 'wp-content/uploads/avatar') !== false){
            return $html_data;
        }else{
            $cat_uri = $this->get_avatar_url($id, $size); // get URL
            return $this->generate_avatar_img_tag($cat_uri, $size, $alt); // get final <img /> tag for the avatar/gravatar
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
        if (empty($size)){ // size was not specified.
            $size = 48; // just set it to 48
        }
        if ( ($image_url==='' ) || (stripos($image_url, 'cat-generator') !== false ) ||  (stripos($image_url, 'mystery-man') !== false ) ) {
            return $this->get_avatar_url($user_id, $size);
        }else{
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
    private function generate_avatar_img_tag($avatar_uri, $size, $args = array(), $alt = '') {
        return sprintf(
            '<img src="%1$s" srcset="%2$s 2x" width="%3$d" height="%3$d" class="%4$s" alt="%5$s" %6$s>',
            esc_url( $avatar_uri ),
            esc_url( $avatar_uri ),
            esc_attr( $size ),
            esc_attr( $this->get_class_value( $size, $args ) ),
            esc_attr( $alt ),
            isset( $args['extra_attr'] ) ? $args['extra_attr'] : ''
        );
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

        $identifier = substr(md5( strtolower( trim( $identifier ).(is_null($this->isCat)?self::MIXNAME:($this->isCat?self::CATNAME:self::BIRDNAME)) ) ),0,6);
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
    *
    */
    private function build_avatar($seed=''){
        // init random seed
        if($seed) srand( hexdec($seed) );

        if (is_null($this->isCat)) $this->isCat = rand(0, 1) == 1; // Mix mode

        // throw the dice for body parts
        if (!$this->isCat){ //bird
            $parts = array(
                'body' => rand(1, 9),
                'eyes' => rand(1, 9),
                'hoop' => rand(1, 10),
                'tail' => rand(1, 9),
                'wing' => rand(1, 9),
                'bec' => rand(1, 9),
                'accessorie' => rand(1, 20)
            );
        } else { //cat
            $parts = array(
                'body' => rand(1,15),
                'fur' => rand(1,10),
                'eyes' => rand(1,15),
                'mouth' => rand(1,10),
                'accessorie' => rand(1,20)
            );
        }

        $partsdir = $this->isCat?'cat/':'bird/';
        // create background
        $avatar = @imagecreatetruecolor(256, 256) or die("GD image create failed");
        $color = imagecolorallocatealpha($avatar, 0, 0, 0, 127);
        imagesavealpha($avatar, true);
        imagefill($avatar,0,0,$color);
        imagefilledrectangle($avatar, 0, 0, 256, 256, $color);

        foreach($parts as $part => $num){ // add parts
            $file = $this->pluginfolder.'img/'.$partsdir.$part.'_'.$num.'.png';
            $im = @imagecreatefrompng($file);

            if(!$im) die('Failed to load '.$file);

            imagesavealpha($im, true);
            imagecopy($avatar,$im,0,0,0,0,256,256);
            imagedestroy($im);
        }

        // restore random seed
        if($seed) srand();

        $cachepath = $this->pluginfolder.$this->cachefolder;
        $cachefile = $cachepath.$seed.'.png';

        // Save/cache the output to a file
        imagepng($avatar, $cachefile, 1, PNG_NO_FILTER);

        imagedestroy($avatar);
    }

    /**
    * Resize to fit responsive pageBuild the avatar image if not exists.
    *
    * @param string    $img_uri image url.
    * @param int       $width target width.
    * @param int       $height target height.
    * @param bool      $crop crop the image, default false.
    *
    * @return string
    */
    public function vt_resize( $img_uri, $width, $height, $crop = false ) {
        $old_img_info = pathinfo( $img_uri );
        $old_img_ext = '.'. $old_img_info['extension'];
        $old_img_path = $old_img_info['dirname'] .'/'. $old_img_info['filename'];

        $new_img_path = $old_img_path.'-'. $width .'x'. $height . $old_img_ext;
        $new_img_url = str_replace(ABSPATH, '/', $new_img_path); // relative path to the wordpress root.

        if (! file_exists($new_img_path) ) {
            $new_img = wp_get_image_editor( $img_uri );
            if (!is_wp_error($new_img)) {
                $new_img->set_quality(100);
                $new_img->resize( $width, $height, $crop );
                $new_img = $new_img->save( $new_img_path );
                error_log(print_r($new_img, true));
                $vt_image = array (
                    'url' => $new_img_url,
                    'width' => $new_img['width'],
                    'height' => $new_img['height']
                );
                return $vt_image['url'];
            } else
                return NULL;
        } else {
           return $new_img_url;
        }
    }
}
