<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
  'pi_name' => 'EE Hive Hacksaw',
  'pi_version' => '1.02',
  'pi_author' => 'EE Hive - Brett DeWoody',
  'pi_author_url' => 'http://www.ee-hive.com/hacksaw',
  'pi_description' => 'Allows you to create excerpts of your entries by removing HTML tags and limited the excerpt by character count, word count or a specific marker you insert into your content.',
  'pi_usage' => Eehive_hacksaw::usage()
  );

/**
 * Buzzsaw Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			Brett DeWoody - ee hive
 * @copyright		Copyright (c) 2010, Brett DeWoody
 * @link			http://www.ee-hive.com/hacksaw
 */

class Eehive_hacksaw
{

var $return_data = "";

	// --------------------------------------------------------------------

	/**
	 * Hacksaw
	 *
	 * This function strips HTML and cuts the content off at a specific character count, word or cutoff marker
	 *
	 * @access	public
	 * @return	string
	 */

  function Eehive_hacksaw() {
    $this->EE =& get_instance();
    
	$tag_content = $this->EE->TMPL->tagdata;
	
	$chars = $this->EE->TMPL->fetch_param('chars');
	$words = $this->EE->TMPL->fetch_param('words');
	$cutoff = $this->EE->TMPL->fetch_param('cutoff');
	$append = $this->EE->TMPL->fetch_param('append', '');
	$allow = $this->EE->TMPL->fetch_param('allow');
	
	if(isset($cutoff) && $cutoff != "") {
		$cutoff_content = $this->_truncate_cutoff($tag_content, $cutoff, $words, $allow, $append);
		// Strip the HTML
		$new_content = strip_tags($cutoff_content, $allow) . $append;
	} elseif (isset($chars) && $chars != "") {
		// Strip the HTML
		$stripped_content = strip_tags($tag_content, $allow);
		$new_content = $this->_truncate_chars($stripped_content, $chars, $append);
	} elseif (isset($words) && $words != "") {
		// Strip the HTML
		$stripped_content = strip_tags($tag_content, $allow);
		$new_content = $this->_truncate_words($stripped_content, $words, $append);
	} 
	
	// Return the new content
    $this->return_data = $new_content;
    
  }
  
  // Helper Function - Truncate by Word Limit
  function _truncate_words($content, $limit, $append) {
    $num_words = str_word_count($content, 0);
	if ($num_words > $limit) {
	  $words = str_word_count($content, 2);
      $pos = array_keys($words);
      $content = substr($content, 0, ($pos[$limit]-1)) . $append;
    }
    return $content;
    }
	
  // Helper Function - Truncate by Character Limit
  function _truncate_chars($content, $limit, $append) {
    $content = substr($content,0,$limit) . $append;  
    return $content;
  }
  
  // Helper Function - Truncate by Cutoff Marker
  function _truncate_cutoff($content, $cutoff, $words, $allow, $append) {
    $pos = strpos($content, $cutoff);
	if ($pos != FALSE) {
		$content = substr($content, 0, $pos);
	} elseif ($words != "") {
		$content = $this->_truncate_words(strip_tags($content, $allow), $words, '');
	}
    return $content;
  }
  

	// --------------------------------------------------------------------

	/**
	 * Usage
	 *
	 * This function describes how the plugin is used.
	 *
	 * @access	public
	 * @return	string
	 */
	
  //  Make sure and use output buffering

  function usage()
  {
  ob_start(); 
  ?>
Hacksaw allows you to create excerpts of your content
like no other. It strips the HTML from your content and
limits the excerpts by character count, word count or
cutoff marker.

{exp:eehive_hacksaw
	chars = "" // Limit by number of characters
    words = "" // Limit by number of words
    cutoff = "" // Limit by a specific cutoff string
    append = "" // String to append to the end of the excerpt
    allow = "" // HTML tags you want to allow. Ex allow="<b><a>"
}{your_content}{/exp:eehive_hacksaw}



  <?php
  $buffer = ob_get_contents();
	
  ob_end_clean(); 

  return $buffer;
  }
  // END

}
/* End of file pi.memberlist.php */ 
/* Location: ./system/expressionengine/third_party/eehive_buzzsaw/pi.eehive_buzzsaw.php */