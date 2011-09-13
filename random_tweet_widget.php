<?php
/*
Plugin Name: Random Tweet Widget
Plugin URI: http://www.whiletrue.it/
Description: This plugin displays a random post from a Twitter account in a sidebar widget.
Author: WhileTrue
Version: 1.0.0
Author URI: http://www.whiletrue.it/
*/

/*
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License version 2, 
    as published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/


// Display Twitter messages
function random_tweet_messages($options) {
	include_once(ABSPATH . WPINC . '/rss.php');
	
	// CHECK OPTIONS
	
	if ($options['username'] == '') {
		return __('RSS not configured','random_tweet_widget');
	} 
	
	if (!is_numeric($options['num']) or $options['num']<=0) {
		return __('Number of tweets not valid','random_tweet_widget');
	}

	$rss = fetch_feed('http://api.twitter.com/1/statuses/user_timeline.rss?screen_name='.$options['username'].'&count='.$options['num']);

	if (is_wp_error($rss)) {
		return __('WP Error: Feed not created correctly','random_tweet_widget');
	}

	$max_items_retrieved = $rss->get_item_quantity(); 

	if ($max_items_retrieved==0) {
		return __('No public Twitter messages','random_tweet_widget');
	}

	$out = '<div class="random_tweet_widget">';

	// BUILD AN ARRAY WITH A RANDOM ITEM
	$random_index = rand(0,($max_items_retrieved-1));
	$message = $rss->get_item($random_index); 

	$msg = " ".substr(strstr($message->get_description(),': '), 2, strlen($message->get_description()))." ";
		
	if($options['encode_utf8']) $msg = utf8_encode($msg);
				
	if ($options['hyperlinks']) {
		// match protocol://address/path/file.extension?some=variable&another=asf%
		$msg = preg_replace('/\b([a-zA-Z]+:\/\/[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i',"<a href=\"$1\" class=\"twitter-link\">$1</a>", $msg);
		// match www.something.domain/path/file.extension?some=variable&another=asf%
		$msg = preg_replace('/\b(?<!:\/\/)(www\.[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i',"<a href=\"http://$1\" class=\"twitter-link\">$1</a>", $msg);    
		// match name@address
		$msg = preg_replace('/\b([a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]*\@[a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]{2,6})\b/i',"<a href=\"mailto://$1\" class=\"twitter-link\">$1</a>", $msg);
		// mach #trendingtopics
		$msg = preg_replace('/#([\w\pL-.,:>]+)/iu', '<a href="http://twitter.com/#!/search/\1" class="twitter-link">#\1</a>', $msg);
	}
	if ($options['twitter_users'])  { 
		$msg = preg_replace('/([\.|\,|\:|\¡|\¿|\>|\{|\(]?)@{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://twitter.com/$2\" class=\"twitter-user\">@$2</a>$3 ", $msg);
	}
          					
	$link = $message->get_permalink();
	if($options['linked'] == 'all')  { 
		$msg = '<a href="'.$link.'" class="twitter-link">'.$msg.'</a>';  // Puts a link to the status of each tweet 
	} else if ($options['linked'] != '') {
		$msg = $msg . '<a href="'.$link.'" class="twitter-link">'.$options['linked'].'</a>'; // Puts a link to the status of each tweet
	} 
	$out .= $msg;
		
	if($options['update']) {				
		$time = strtotime($message->get_date());
		$h_time = ( ( abs( time() - $time) ) < 86400 ) ? sprintf( __('%s ago'), human_time_diff( $time )) : date(__('Y/m/d'), $time);
		$out = rtrim($out);
		$out .= ', '.sprintf( __('%s', 'twitter-for-wordpress'),' <span class="twitter-timestamp"><abbr title="' . date(__('Y/m/d H:i:s'), $time) . '">' . $h_time . '</abbr></span>' );
	}
	$out .= '</div>';
	return $out;
}



/**
 * RandomTweetWidget Class
 */
class RandomTweetWidget extends WP_Widget {
    /** constructor */
    function RandomTweetWidget() {
				$this->options = array(
					array('name'=>'title', 'label'=>'Title:', 'type'=>'text'),
					array('name'=>'username', 'label'=>'Twitter Username:', 'type'=>'text'),
					array('name'=>'num', 'label'=>'Choose among how many tweets:', 'type'=>'text'),
					array('name'=>'update', 'label'=>'Show timestamps:', 'type'=>'checkbox'),
					array('name'=>'linked', 'label'=>'Link text:', 'type'=>'text'),
					array('name'=>'hyperlinks', 'label'=>'Show Hyperlinks:', 'type'=>'checkbox'),
					array('name'=>'twitter_users', 'label'=>'Find @replies:', 'type'=>'checkbox'),
					array('name'=>'encode_utf8', 'label'=>'UTF8 Encode:', 'type'=>'checkbox'),
				);

        parent::WP_Widget(false, $name = 'Random Tweet');	
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {		
				extract( $args );
				$title = apply_filters('widget_title', $instance['title']);
				echo $before_widget;  
				if ( $title ) {
					echo $before_title . '<a href="http://twitter.com/' . $instance['username'] . '" class="twitter_title_link">'. $instance['title'] . '</a>' . $after_title; 
				}
				echo random_tweet_messages($instance);
				echo $after_widget;
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {				
				$instance = $old_instance;
				
				foreach ($this->options as $val) {
					if ($val['type']=='text') {
						$instance[$val['name']] = strip_tags($new_instance[$val['name']]);
					} else if ($val['type']=='checkbox') {
						$instance[$val['name']] = ($new_instance[$val['name']]=='on') ? true : false;
					}
				}
        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {
				if (empty($instance)) {
					$instance['title'] = 'Random Tweet';
					$instance['username'] = '';
					$instance['num'] = '100';
					$instance['update'] = true;
					$instance['linked'] = '#';
					$instance['hyperlinks'] = true;
					$instance['twitter_users'] = true;
					$instance['encode_utf8'] = false;
				}					

				foreach ($this->options as $val) {
					echo '<p>
						      <label for="'.$this->get_field_id($val['name']).'">'.__($val['label']).'</label> 
						   ';
					if ($val['type']=='text') {
						echo '<input class="widefat" id="'.$this->get_field_id($val['name']).'" name="'.$this->get_field_name($val['name']).'" type="text" value="'.esc_attr($instance[$val['name']]).'" />';
					} else if ($val['type']=='checkbox') {
						$checked = ($instance[$val['name']]) ? 'checked="checked"' : '';
						echo '<input id="'.$this->get_field_id($val['name']).'" name="'.$this->get_field_name($val['name']).'" type="checkbox" '.$checked.' />';
					}
					echo '</p>';
				}
    }

} // class RandomTweetWidget

// register RandomTweetWidget widget
add_action('widgets_init', create_function('', 'return register_widget("RandomTweetWidget");'));
