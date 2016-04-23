<?php

/*
Plugin Name: News in Stack Widget

Description: Adds a widget that can display recent posts from multiple categories or from custom post types.
Version: 1.3.1
Author: Eugen Bobrowski
Author URI: http://bobrowski.ru
*/


class News_In_Stack_Widget extends WP_Widget
{
    public $instance;

    /** constructor */
    function __construct()
    {
        $widget_ops = array(
            'classname' => 'news-in-stack-widget',
            'description' => __('Shows recent posts / custom post types. Includes advanced options.')
        );
        $control_ops = array('width' => 400, 'height' => 350);
        parent::__construct('news-in-stack-widget', __('News in Stack Widget'), $widget_ops, $control_ops);


        $this->widget_shortcodes = apply_filters('nisw_shortcodes', array(
            'link' => array($this, 'shortcode_link'),
            'title' => array($this, 'shortcode_title'),
            'thumb' => array($this, 'shortcode_thumb'),
            'excerpt' => array($this, 'shortcode_excerpt'),
            'commentnum' => array($this, 'shortcode_commentsnum'),
        ));

    }

    function widget($args, $instance)
    {
        global $post;

        extract($args);

        $defaults = $this->return_defaults();

        $instance = wp_parse_args($instance, $defaults);

        $this->instance = $instance;

        $title = apply_filters('widget_title', empty($instance['title']) ? 'Recent Posts' : $instance['title'], $instance, $this->id_base);

        $template = $instance["template"];
        $styles = $instance['styles'];
        $script = $instance['script'];
        $cssclass = ($instance['cssclass'] === null) ? $defaults['cssclass'] : $instance['cssclass'];

        if (!$number = absint($instance['number'])) $number = 5;

        if (!$excerpt_length = absint($instance['excerpt_length'])) $excerpt_length = 5;

        if (!$cats = $instance["cats"]) $cats = '';

        if (!$show_type = $instance["show_type"]) $show_type = 'post';

        if (!($this->instance['thumb_h'] = absint($this->instance['thumb_h']))) $this->instance['thumb_h'] = 50;

        if (!$thumb_w = absint($instance["thumb_w"])) $thumb_w = 50;

        if (!$excerpt_readmore = $instance["excerpt_readmore"]) $excerpt_readmore = 'Read more &rarr;';

        $default_sort_orders = array('date', 'title', 'comment_count', 'rand');

        if (in_array($instance['sort_by'], $default_sort_orders)) {

            $sort_by = $instance['sort_by'];

            $sort_order = (bool)$instance['asc_sort_order'] ? 'ASC' : 'DESC';

        } else {

            // by default, display latest first

            $sort_by = 'date';

            $sort_order = 'DESC';

        }


        //Excerpt more filter
        $new_excerpt_more = create_function('$more', 'return " ";');
        add_filter('excerpt_more', $new_excerpt_more);


        // Excerpt length filter
        $new_excerpt_length = create_function('$length', "return " . $excerpt_length . ";");

        if ($instance["excerpt_length"] > 0) add_filter('excerpt_length', $new_excerpt_length);


        // post info array.

        $my_args = array(

            'showposts' => $number,

            'category__in' => $cats,

            'orderby' => $sort_by,

            'order' => $sort_order,

            'post_type' => $show_type

        );


        $recent_posts = null;

        $recent_posts = new WP_Query($my_args);


        echo $before_widget;


        // Widget title

        echo $before_title;

        echo $instance["title"];

        echo $after_title;


        // Post list

        echo '<ul>';

        if (strpos($template, '{title}') !== false) {
            $tags['title'] = '{title}';
        }
        if (strpos($template, '{thumb}') !== false) {
            $tags['thumb'] = '{thumb}';
        }
        if (strpos($template, '{thumburl}') !== false) {
            $tags['thumburl'] = '{thumburl}';
        }
        if (strpos($template, '{postlink}') !== false) {
            $tags['postlink'] = '{postlink}';
        }
        if (strpos($template, '{date}') !== false) {
            $tags['date'] = '{date}';
        }
        if (strpos($template, '{excerpt}') !== false) {
            $tags['excerpt'] = '{excerpt}';
        }
        if (strpos($template, '{commentnum}') !== false) {
            $tags['commentnum'] = '{commentnum}';
        }


        while ($recent_posts->have_posts()) {

            $recent_posts->the_post();

            $item = $template;

            if (isset($tags['title'])):
                $item = str_replace('{title}', the_title('', '', false), $item);
            endif;

            if (isset($tags['thumb']) or isset($tags['thumburl'])):

                if (
                    current_theme_supports("post-thumbnails") &&
                    has_post_thumbnail()
                ) {
                    $thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
                    require_once('aq_resize.php');
                    $thumbUrl = aq_resize($thumbnail[0], $thumb_w, $thumb_h, true, true, true);

                    if (isset($tags['thumburl'])) {
                        $item = str_replace('{thumburl}', $thumbUrl, $item);
                    }
                    if (isset($tags['thumb'])) {
                        $item = str_replace('{thumb}', '<img src="' . $thumbUrl . '" alt="" width="' . $thumb_w . '" height="' . $thumb_h . '"/>', $item);
                    }
                } else {
                    $item = str_replace('{thumb}', '', $item);
                }
            endif;

            if (isset($tags['postlink'])) {
                $item = str_replace('{postlink}', get_permalink(), $item);
            }
            if (isset($tags['date'])) {
                $item = str_replace('{date}', get_the_time("j M Y"), $item);
            }

            if (isset($tags['excerpt'])) {
                $item = str_replace('{excerpt}', get_the_excerpt(), $item);
            }
            if (isset($tags['commentnum'])) {
                $item = str_replace('{commentnum}', get_comments_number(), $item);
            }

            echo '<li class="' . $cssclass . '">' . $this->stack_shortcodes($item) . '</li>';

        }

        wp_reset_query();

        echo "</ul>\n";
        echo "<script>(function ( $ ) { $(function () {
            $(document).ready(function(){ " . $script . " })})}(jQuery));</script>";
        echo "<style> " . $styles . "</style>";


        echo $after_widget;


        remove_filter('excerpt_length', $new_excerpt_length);
        remove_filter('excerpt_more', $new_excerpt_more);


    }

    function return_defaults()
    {
        return array(
            'title' => 'Recent Posts',
            'number' => 5,
            'thumb_h' => 50,
            'thumb_w' => 50,
            'show_type' => 'post',
            'excerpt_length' => 5,
            'cats' => NULL,
            'sort_by' => 'date',
            'asc_sort_order' => false,
            'cssclass' => 'recent-post-item',
            'template' => '<a href="{postlink}" rel="bookmark" title="Permanent link to {title}" class="full-item-link">
{thumb}
<span class="post-title">{title}</span>
<span class="post-entry">{excerpt} <span class="comment-num"><span class="glyphicon glyphicon-comment"></span> {commentnum}</span></span>
</a>',
            'styles' => '.news-in-stack-widget {}
.news-in-stack-widget ul {
	list-style: none;
	list-style-type: none;
	padding: 0;
	font-size: 0.9em;
}
.news-in-stack-widget img {
	margin: 3px 5px 0 0;
	border: 2px solid #ffffff;
	border-radius: 50%;
	float:left;
}
.news-in-stack-widget .recent-post-item {
	background-color: rgba(255,255,255,0.8);
	margin-bottom: 6px;
	padding: 5px;
}
.news-in-stack-widget .post-title {
	color: #526170;
	display: block;
}
.news-in-stack-widget .full-item-link, .news-in-stack-widget .full-item-link:hover {
	width: 100%;
	display: block;
	text-decoration: none;
	color: #83888c;
}
.news-in-stack-widget .comment-num {
	display: block;
	text-align: right;
}',
            'script' => "$('.news-in-stack-widget ul li a').tooltip();",
        );
    }

    function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['cats'] = $new_instance['cats'];
        $instance['sort_by'] = esc_attr($new_instance['sort_by']);
        $instance['show_type'] = esc_attr($new_instance['show_type']);
        $instance['asc_sort_order'] = esc_attr($new_instance['asc_sort_order']);
        $instance['number'] = absint($new_instance['number']);
        $instance["thumb"] = esc_attr($new_instance['thumb']);
        $instance['date'] = esc_attr($new_instance['date']);
        $instance['comment_num'] = esc_attr($new_instance['comment_num']);
        $instance["excerpt_length"] = absint($new_instance["excerpt_length"]);
        $instance["excerpt_readmore"] = esc_attr($new_instance["excerpt_readmore"]);
        $instance["thumb_w"] = absint($new_instance["thumb_w"]);
        $instance["thumb_h"] = absint($new_instance["thumb_h"]);
        $instance["excerpt"] = esc_attr($new_instance["excerpt"]);
        $instance["readmore"] = esc_attr($new_instance["readmore"]);
        $instance["template"] = $new_instance["template"];
        $instance["styles"] = $new_instance["styles"];
        $instance["script"] = $new_instance["script"];
        $instance["cssclass"] = $new_instance["cssclass"];
        return $instance;
    }

    function form($instance)
    {
        $instance = wp_parse_args($instance, $this->return_defaults());

        ?>
        <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                   name="<?php echo $this->get_field_name('title'); ?>" type="text"
                   value="<?php echo $instance['title']; ?>"/></p>
        <p>
            <label for="<?php echo $this->get_field_id('cssclass'); ?>"><?php _e('Item CSS Class: '); ?>
                <input type="text" class="widefat"
                       id="<?php echo $this->get_field_id("cssclass"); ?>"
                       name="<?php echo $this->get_field_name("cssclass"); ?>"
                       value="<?php echo $instance['cssclass']; ?>">
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('template'); ?>"><?php _e('Templates:'); ?>
                <textarea class="widefat"
                          id="<?php echo $this->get_field_id("template"); ?>"
                          name="<?php echo $this->get_field_name("template"); ?>"
                          rows="7"><?php echo $instance['template']; ?></textarea>
            </label>
            <strong>Avaliable variables:</strong><br/>
            {title}
            {thumb}
            {thumburl}
            {postlink}
            {excerpt}
            {commentnum}
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('styles'); ?>"><?php _e('Additional styles:'); ?>
                <textarea class="widefat"
                          id="<?php echo $this->get_field_id("styles"); ?>"
                          name="<?php echo $this->get_field_name("styles"); ?>"
                          rows="7"><?php echo $instance['styles']; ?></textarea>
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('script'); ?>"><?php _e('Script:'); ?>
                <input class="widefat" type="text"
                       id="<?php echo $this->get_field_id("scrips"); ?>"
                       name="<?php echo $this->get_field_name("script"); ?>"
                       value="<?php echo $instance['script']; ?>">
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id("sort_by"); ?>">
                <?php _e('Sort by'); ?>:
                <select id="<?php echo $this->get_field_id("sort_by"); ?>"
                        name="<?php echo $this->get_field_name("sort_by"); ?>">
                    <option value="date"<?php selected($instance['sort_by'], "date"); ?>>Date</option>
                    <option value="title"<?php selected($instance['sort_by'], "title"); ?>>Title</option>
                    <option value="comment_count"<?php selected($instance['sort_by'], "comment_count"); ?>>Number of
                        comments
                    </option>
                    <option value="rand"<?php selected($instance['sort_by'], "rand"); ?>>Random</option>
                </select>
            </label>
        </p>


        <p>
            <label for="<?php echo $this->get_field_id("asc_sort_order"); ?>">

                <input type="checkbox" class="checkbox"

                       id="<?php echo $this->get_field_id("asc_sort_order"); ?>"

                       name="<?php echo $this->get_field_name("asc_sort_order"); ?>"

                    <?php checked((bool)$instance["asc_sort_order"], true); ?> />

                <?php _e('Reverse sort order (ascending)'); ?>

            </label>

        </p>

        <p>
            <label for="<?php echo $this->get_field_id("excerpt_length"); ?>">
                <?php _e('Excerpt length (in words):'); ?>
            </label>
            <input style="text-align: center;" type="text" id="<?php echo $this->get_field_id("excerpt_length"); ?>"
                   name="<?php echo $this->get_field_name("excerpt_length"); ?>"
                   value="<?php echo $instance['excerpt_length']; ?>"
                   size="3"/>
        </p>


        <p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of posts to show:'); ?></label>
            <input id="<?php echo $this->get_field_id('number'); ?>"
                   name="<?php echo $this->get_field_name('number'); ?>" type="text"
                   value="<?php echo $instance['number']; ?>"
                   size="3"/></p>

        <?php if (function_exists('the_post_thumbnail') && current_theme_supports("post-thumbnails")) : ?>


        <p>

            <label>

                <?php _e('Thumbnail dimensions'); ?>:<br/>

                <label for="<?php echo $this->get_field_id("thumb_w"); ?>">

                    W: <input class="widefat" style="width:40%;" type="text"
                              id="<?php echo $this->get_field_id("thumb_w"); ?>"
                              name="<?php echo $this->get_field_name("thumb_w"); ?>"
                              value="<?php echo $instance['thumb_w']; ?>"/>

                </label>


                <label for="<?php echo $this->get_field_id("thumb_h"); ?>">

                    H: <input class="widefat" style="width:40%;" type="text"
                              id="<?php echo $this->get_field_id("thumb_h"); ?>"
                              name="<?php echo $this->get_field_name("thumb_h"); ?>"
                              value="<?php echo $instance['thumb_h']; ?>"/>

                </label>

            </label>

        </p>

    <?php endif; ?>

        <p>
            <label for="<?php echo $this->get_field_id('cats'); ?>"><?php _e('Categories:'); ?>

                <?php
                $categories = get_categories('hide_empty=0');
                echo "<br/>";
                foreach ($categories as $cat) {
                    $option = '<input type="checkbox" id="' . $this->get_field_id('cats') . '[]" name="' . $this->get_field_name('cats') . '[]"';
                    if (is_array($instance['cats'])) {
                        foreach ($instance['cats'] as $cats) {
                            if ($cats == $cat->term_id) {
                                $option = $option . ' checked="checked"';
                            }
                        }
                    }
                    $option .= ' value="' . $cat->term_id . '" />';

                    $option .= $cat->cat_name;

                    $option .= '<br />';
                    echo $option;
                }

                ?>
            </label>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('show_type'); ?>"><?php _e('Show Post Type:'); ?>
                <select class="widefat" id="<?php echo $this->get_field_id('show_type'); ?>"
                        name="<?php echo $this->get_field_name('show_type'); ?>">
                    <?php
                    global $wp_post_types;
                    foreach ($wp_post_types as $k => $sa) {
                        if ($sa->exclude_from_search) continue;
                        echo '<option value="' . $k . '"' . selected($k, $show_type, true) . '>' . $sa->labels->name . '</option>';
                    }
                    ?>
                </select>
            </label>
        </p>

        <?php
    }

    public function stack_shortcodes($code)
    {

        $pattern = get_shortcode_regex(array_keys($this->widget_shortcodes));

        do {
            $old_code=$code;
            $code = preg_replace_callback("/$pattern/", array($this, 'do_shortcode_tag'), $code);
        } while ($old_code != $code);



        return $code;
    }
    public function do_shortcode_tag($m)
    {
        $shortcode_tags = $this->widget_shortcodes;
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
            'width' => $this->instance['thumb_w'],
            'height' => $this->instance['thumb_h'],
        ));

        if (
            current_theme_supports("post-thumbnails") &&
            has_post_thumbnail()
        ) {
            $thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'full');
            require_once('aq_resize.php');
            $thumbUrl = aq_resize($thumbnail[0], absint($attr['width']), $attr['height'], true, true, true);

            if (in_array('url', $attr)) return $thumbUrl;
            else return '<img src="'. $thumbUrl .'" alt="' . get_the_title() . '"/>';

        } else {
            return '<img src="' . apply_filters('stack_blank_img', plugin_dir_url(__FILE__) . 'assets/blank.png') . '" alt="' . get_the_title() . '"/>';
        }
    }
    public function shortcode_excerpt($attr)
    {
        return get_the_excerpt();
    }

}

// register RecentPostsPlus widget
add_action('widgets_init', create_function('', 'return register_widget("News_In_Stack_Widget");'));
?>
