<?php
/*
Plugin Name: Random Tweet Widget
Plugin URI: http://www.whiletrue.it/
Description: This plugin displays a random post from a Twitter account in a sidebar widget.
Author: WhileTrue
Version: 1.1.1
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

/**
 * RandomTweetWidget Class
 */
class RandomTweetWidget extends WP_Widget {
    /** constructor */
    function RandomTweetWidget() {
		$this->options = array(
			array(
				'label' => __( 'Twitter Authentication options', 'rstw' ),
				'type'	=> 'separator', 	'notes' => __('Get them creating your Twitter Application', 'rstw' ).' <a href="https://dev.twitter.com/apps" target="_blank">'.__('here', 'rstw' ).'</a><br /><br />'	),
			array(
				'name'	=> 'consumer_key',	'label'	=> 'Consumer Key',
				'type'	=> 'text',	'default' => ''			),
			array(
				'name'	=> 'consumer_secret',	'label'	=> 'Consumer Secret',
				'type'	=> 'text',	'default' => ''			),
			array(
				'name'	=> 'access_token',	'label'	=> 'Access Token',
				'type'	=> 'text',	'default' => ''			),
			array(
				'name'	=> 'access_token_secret',	'label'	=> 'Access Token Secret',
				'type'	=> 'text',	'default' => ''			),
			array(
				'label' => __( 'Other options', 'rstw' ),
				'type'	=> 'separator'			),
			array('name'=>'title', 'label'=>'Title', 'type'=>'text'),
			array('name'=>'username', 'label'=>'Twitter Username', 'type'=>'text'),
			array('name'=>'num', 'label'=>'Choose among how many tweets', 'type'=>'text'),
			array('name'=>'linked', 'label'=>'Link text', 'type'=>'text'),
			array('name'=>'update', 'label'=>'Show timestamps', 'type'=>'checkbox'),
			array('name'=>'hyperlinks', 'label'=>'Show Hyperlinks', 'type'=>'checkbox'),
			array('name'=>'twitter_users', 'label'=>'Find @replies', 'type'=>'checkbox'),
			array('name'=>'encode_utf8', 'label'=>'UTF8 Encode', 'type'=>'checkbox'),
		);

        $control_ops = array('width' => 400);
        parent::WP_Widget(false, 'Random Tweet', array(), $control_ops);	
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {		
		extract( $args );
		$title = apply_filters('widget_title', $instance['title']);
		echo $before_widget;  
		if ( $title != '' ) {
			echo $before_title . '<a href="http://twitter.com/' . $instance['username'] . '" class="twitter_title_link">'. $instance['title'] . '</a>' . $after_title; 
		}
		echo $this->random_tweet_messages($instance);
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
			$instance['linked'] = '';
			$instance['hyperlinks'] = true;
			$instance['twitter_users'] = true;
			$instance['encode_utf8'] = false;
		}
		
		foreach ($this->options as $val) {
			if ($val['type']=='separator') {
				echo '<hr />';
				if ($val['label']!='') {
					echo '<h3>'.$val['label'].'</h3>';
				}
				if ($val['notes']!='') {
					echo '<span class="description">'.$val['notes'].'</span>';
				}
			} else if ($val['type']=='text') {
				echo '<p>
				      <label for="'.$this->get_field_id($val['name']).'">'.__($val['label']).'</label><br />
					  <input class="widefat" id="'.$this->get_field_id($val['name']).'" name="'.$this->get_field_name($val['name']).'" type="text" value="'.esc_attr($instance[$val['name']]).'" />
					  </p>';
			} else if ($val['type']=='checkbox') {
				$checked = ($instance[$val['name']]) ? 'checked="checked"' : '';
				echo '<input id="'.$this->get_field_id($val['name']).'" name="'.$this->get_field_name($val['name']).'" type="checkbox" '.$checked.' />
						<label for="'.$this->get_field_id($val['name']).'">'.__($val['label']).'</label><br />';
			}
		}
	}


	protected function debug ($options, $text) {
		if ($options['debug']) {
			echo $text."\n";
		}
	}
	

	// Display Twitter messages
	protected function random_tweet_messages($options) {
	
		// CHECK OPTIONS

		if ($options['username'] == '') {
			return __('Twitter username is not configured','random_tweet_widget');
		} 
		if (!is_numeric($options['num']) or $options['num']<=0) {
			return __('Number of tweets is not valid','random_tweet_widget');
		}
		if ($options['consumer_key'] == '' or $options['consumer_secret'] == '' or $options['access_token'] == '' or $options['access_token_secret'] == '') {
			return __('Twitter Authentication data is incomplete','random_tweet_widget');
		} 

		if (!class_exists('Codebird')) {
			require ('lib/codebird.php');
		}
		Codebird::setConsumerKey($options['consumer_key'], $options['consumer_secret']); 
		$this->cb = Codebird::getInstance();	
		$this->cb->setToken($options['access_token'], $options['access_token_secret']);

		$transient_name = 'twitter_data_'.$options['username'].'_'.$options['num'];

		// USE TRANSIENT DATA, TO MINIMIZE REQUESTS TO THE TWITTER FEED
	
		$timeout = 120 * 60; //120m
		$error_timeout = 5 * 60; //5m
    
		$twitter_data = get_transient($transient_name);
    
		if (empty($twitter_data) or count($twitter_data)<1 or isset($twitter_data->errors)) {
			$this->debug($options, '<!-- '.__('Fetching data from Twitter').'... -->');
			$this->debug($options, '<!-- '.__('Requested items').' : '.$max_items_to_retrieve.' -->');

			try {
				$twitter_data =  $this->cb->statuses_userTimeline(array('screen_name'=>$options['username'], 'count'=>$max_items_to_retrieve));
			} catch (Exception $e) { return __('Error retrieving tweets','random_tweet_widget'); }

			if(!isset($twitter_data->errors) and (count($twitter_data) >= 1) ) {
			    set_transient($transient_name, $twitter_data, $timeout);
			} else {
			    set_transient($transient_name, $twitter_data, $error_timeout);	// Wait 5 minutes before retry
				if (isset($twitter_data->errors)) {
					$this->debug($options, __('Twitter data error:','random_tweet_widget').' '.$twitter_data->errors[0]->message.'<br />');
				}
			}
		} else {
			$this->debug($options, '<!-- '.__('Using cached Twitter data').'... -->');

			if(isset($twitter_data->errors)) {
				$this->debug($options, __('Twitter cache error:','random_tweet_widget').' '.$twitter_data->errors[0]->message.'<br />');
			}
		}
    
		if (empty($twitter_data) or count($twitter_data)<1) {
		    return __('No public tweets','random_tweet_widget');
		}
		
		// TODO LINK TARGET
		//$link_target = ($options['link_target_blank']) ? ' target="_blank" ' : '';

		$out = '<div class="random_tweet_widget">';

		$twitter_data = (array) $twitter_data;
	
		// BUILD AN ARRAY WITH A RANDOM ITEM
		$random_index = rand(0,(count($twitter_data)-1));
		$message = $twitter_data[$random_index]; 

		//$msg = " ".substr(strstr($message->get_description(),': '), 2, strlen($message->get_description()))." ";
		$msg = $message->text;
		if($options['encode_utf8']) $msg = utf8_encode($msg);
				
		if ($options['hyperlinks']) {
			// match protocol://address/path/file.extension?some=variable&another=asf%
			$msg = preg_replace('/\b([a-zA-Z]+:\/\/[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i',"<a href=\"$1\" class=\"twitter-link\">$1</a>", $msg);
			// match www.something.domain/path/file.extension?some=variable&another=asf%
			$msg = preg_replace('/\b(?<!:\/\/)(www\.[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i',"<a href=\"http://$1\" class=\"twitter-link\">$1</a>", $msg);    
			// match name@address
			$msg = preg_replace('/\b([a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]*\@[a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]{2,6})\b/i',"<a href=\"mailto://$1\" class=\"twitter-link\">$1</a>", $msg);
			//NEW mach #trendingtopics
			//$msg = preg_replace('/#([\w\pL-.,:>]+)/iu', '<a href="http://twitter.com/#!/search/\1" class="twitter-link">#\1</a>', $msg);
			//NEWER mach #trendingtopics
			$msg = preg_replace('/(^|\s)#(\w*[a-zA-Z_]+\w*)/', '\1<a href="http://twitter.com/#!/search/%23\2" class="twitter-link" '.$link_target.'>#\2</a>', $msg);
		}
		if ($options['twitter_users'])  { 
			$msg = preg_replace('/([\.|\,|\:|\¡|\¿|\>|\{|\(]?)@{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://twitter.com/$2\" class=\"twitter-user\">@$2</a>$3 ", $msg);
		}
          					
		$link = 'http://twitter.com/#!/'.$options['username'].'/status/'.$message->id_str;
		if($options['linked'] == 'all')  { 
			$msg = '<a href="'.$link.'" class="twitter-link">'.$msg.'</a>';  // Puts a link to the status of each tweet 
		} else if ($options['linked'] != '') {
			$msg = $msg . '<a href="'.$link.'" class="twitter-link">'.$options['linked'].'</a>'; // Puts a link to the status of each tweet
		} 
		$out .= $msg;
		
		if($options['update']) {				
			$time = strtotime($message->created_at);
			$h_time = ( ( abs( time() - $time) ) < 86400 ) ? sprintf( __('%s ago'), human_time_diff( $time )) : date(__('Y/m/d'), $time);
			$out = rtrim($out);
			$out .= ', '.sprintf( __('%s'),' <span class="twitter-timestamp"><abbr title="' . date(__('Y/m/d H:i:s'), $time) . '">' . $h_time . '</abbr></span>' );
		}
		$out .= '</div>';
		return $out;
	}

} // class RandomTweetWidget

// register RandomTweetWidget widget
add_action('widgets_init', create_function('', 'return register_widget("RandomTweetWidget");'));
