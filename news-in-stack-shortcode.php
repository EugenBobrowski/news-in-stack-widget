<?php
/**
 * Created by PhpStorm.
 * User: eugen
 * Date: 4/25/16
 * Time: 12:47 PM
 */

class News_In_Stack_Shortcode {

    protected static $instance;
    public $stack_shortcodes;
    public $attr;

    private function __construct()
    {
        $this->stack_shortcodes = apply_filters('nisw_shortcodes', array(
            'link' => array($this, 'shortcode_link'),
            'title' => array($this, 'shortcode_title'),
            'thumb' => array($this, 'shortcode_thumb'),
            'excerpt' => array($this, 'shortcode_excerpt'),
            'comments' => array($this, 'shortcode_commentsnum'),
            'commentnum' => array($this, 'shortcode_commentsnum'),
            'date' => array($this, 'shortcode_date'),
        ));
        add_shortcode('stack', array($this, 'shortcode_stack'));
        add_filter('widget_text', 'do_shortcode');
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function stack_shortcodes($code)
    {
        $pattern = get_shortcode_regex(array_keys($this->stack_shortcodes));
        do {
            $old_code=$code;
            $code = preg_replace_callback("/$pattern/", array($this, 'do_inner_shortcode_tag'), $code);
        } while ($old_code != $code);

        return $code;
    }
    public function do_inner_shortcode_tag($m)
    {
        $shortcode_tags = $this->stack_shortcodes;
        // allow [[foo]] syntax for escaping a tag
        if ($m[1] == '[' && $m[6] == ']') {
            return substr($m[0], 1, -1);
        }

        $tag = $m[2];
        $attr = shortcode_parse_atts($m[3]);

        if (!is_callable($shortcode_tags[$tag])) {
            /* translators: %s: shortcode tag */
            $message = sprintf(__('Attempting to parse a shortcode without a valid callback: %s'), $tag);
            _doing_it_wrong(__FUNCTION__, $message, '4.3.0');
            return $m[0];
        }

        if (isset($m[5])) {
            // enclosing tag - extra parameter
            return $m[1] . call_user_func($shortcode_tags[$tag], $attr, $m[5], $tag) . $m[6];
        } else {
            // self-closing tag
            return $m[1] . call_user_func($shortcode_tags[$tag], $attr, null, $tag) . $m[6];
        }

    }
    public function shortcode_stack($attr, $content = null) {
        $attr = wp_parse_args($attr, array(
            //Style
            'class' => 'stack-posts',
            'item_class' => 'item',
            //Image
            'thumb_w' => 50,
            'thumb_h' => 50,
            //Excerpt

            //WP_Query
            'showposts' => 10,
            'orderby' => 'date',
            'order' => 'desc',
            'post_type' => 'post',

        ));
        $this->attr = $attr;
        if (empty($content)) $content = '[link][thumb][title] [excerpt][comments][/link]';

        $stack_posts = new WP_Query(array(
            'showposts' => $attr['showposts'],
            'orderby' => $attr['orderby'],
            'order' => $attr['order'],
            'post_type' => $attr['post_type'],
        ));

        $result = '<ul class="'.$attr['class'].'">';

        while ($stack_posts->have_posts()) {

            $stack_posts->the_post();

            $result .= '<li class="' . $attr['item_class'] . '">' . $this->stack_shortcodes($content) . '</li>';

        }

        $result .= '</ul>';

        wp_reset_query();


        return $result;
    }
    public function shortcode_link($attr, $content)
    {
        if (empty($content)) return get_permalink();
        $attributes = '';
        if (is_array($attr)) {
            foreach ($attr as $attribute => $value) {
                $attributes .= $attribute . '="' . $value . '" ';
            }
        }

        return '<a href="'.get_permalink().'" ' . $attributes . ' title=' . get_the_title() . '>' . $content . '</a>';
    }
    public function shortcode_title($attr)
    {
        return get_the_title();
    }
    public function shortcode_thumb($attr)
    {
        $attr = wp_parse_args($attr, array(
            'width' => $this->attr['thumb_w'],
            'height' => $this->attr['thumb_h'],
            'alt' => get_the_title(),
        ));
        if (
            has_post_thumbnail()
        ) {
            $thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'full');
            require_once('aq_resize.php');
            $attr['src'] = aq_resize($thumbnail[0], absint($attr['width']), $attr['height'], true, true, true);

            if (in_array('url', $attr)) return $attr['src'];
            else {
                $attributes = '';
                if (is_array($attr)) {
                    foreach ($attr as $attribute => $value) {
                        $attributes .= $attribute . '="' . $value . '" ';
                    }
                }
                return '<img ' . $attributes . '/>';
            }

        } else {
            return '<img src="' . apply_filters('stack_blank_img', plugin_dir_url(__FILE__) . 'assets/blank.png') . '" alt="' . get_the_title() . '"/>';
        }
    }
    public function shortcode_excerpt($attr)
    {
        return get_the_excerpt();
    }
    public function shortcode_commentsnum($attr)
    {
        return get_comments_number();
    }
    public function shortcode_date()
    {
        return get_the_time("j M Y");
    }

}

News_In_Stack_Shortcode::get_instance();