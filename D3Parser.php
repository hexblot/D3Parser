<?php
/**
 * Copyright 2012 Andriopoulos Nikolaos.
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */
define('D3P_DEBUG', true);      // Prints a few more messages
define('D3P_PATH_TO_LIB', '.'); // Path to Simple HTML DOM library
define('D3P_STORAGE', 'file');  // Method to use to store data. Currently only file is supported.

// Use the following for file storage (SQL to come soon)
define('D3P_DATAFILE', 'profiles.json');
define('D3P_DATA_TTL', 3600);

include(D3P_PATH_TO_LIB.'/simple_html_dom.php'); // Easy jQuery like DOM traversal

/**
 * Use to get profile data for Diablo 3 heroes and profiles from the HTML armory.
 *
 * D3Parser is a library that fetches complete profile data and hero data
 * and provides for caching. It is compatible with the Battle.net HTML armory, and
 * tries to avoid querying when possible.
 */
class D3Parser {
	private $config = array(
		/* Connection URL parameters. Override when instantiating. */
		'realm'     => 'eu',
		'lang'      => 'en',
		'game'      => 'd3',
		'base'      => 'battle.net',
		'scheme'    => 'http',
	);
	
	private static $profiles; /* Static cache of all queries */
	private static $instance; /* Singleton instance id */
	
	/**
	 * Use to instantiate class
	 * 
	 * Using the singleton pattern multiple connections can reuse the
	 * same resources, and keep statically cached data in one object.
	 * If passed with a config array, keys are overriden from defaults.
	 */
	public static function getInstance($config=array()) { 
	    if (!self::$instance) { 
	        self::$instance = new D3Parser($config); 
	    } 

	    return self::$instance; 
	}
	
	/**
	 * Use to fetch complete data for a profile (not heroes). 
	 *
	 * This is the actual function to call to get profile data for a player.
	 * It is also the first required step for fetching hero data.
	 */
	public function getTag($tag) {
		if(count($this->profiles)==0) {
			$this->loadProfiles();
			print_r($this->profiles);
		}
	  if(!isset($this->profiles->{$tag})) {
		  $dom = $this->_load_page($this->_make_url($tag));
		  $this->profiles->{$tag}->{'career'} = $this->parseTagPage($dom);
		  $dom->clean;
		  unset($dom);
		  $this->saveProfiles();
	  }
	  return $this->profiles->{$tag};
	}
	
	/**
	 * Use to fetch complete data for a hero. Will also fetch profile data.
	 * 
	 * This is the actual function to call to get hero data for a player.
	 */
	public function getHero($tag, $hero) {
		if ( !isset($this->profiles->{$tag}) ) {
			$this->getTag($tag);
		}
		
	  $dom = $this->_load_pag($this->make_url($tag, $hero));
	  $this->profiles->{$tag}->{'heroes'}->{$hero} = $this->parseHeroPage($dom);
	  $dom->clean;
	  unset($dom);
		
		$this->saveProfiles();
		
	}
	
	/**
	 * Class constructor. Private due to singleton pattern.
	 */
	private function __construct($config) {
		// Allow any option to be overriden in constructor
		$this->config = array_merge($this->config, $config);
	}
	
	/**
	 * Get a web file (HTML, XHTML, XML, image, etc.) from a URL.  Return an
	 * array containing the HTTP server content.
	 */
	private function _load_page($url) {
    $html = new simple_html_dom();
    if ($html->load($this->get_web_page($url))===false) {
	    // Notify caller that load did not work. Usually PHP configuration issue.
				return array('error'=>'Could not load file');
	  }
    return $html;
	}
	
	/**
	 * Formulate a battle.net URL from params. Internal function.
	 */
	private function _make_url( $tag, $hero='') {
	  // Sample URL: http://eu.battle.net/d3/en/profile/Mytag-1234/hero/1234567
	  return 
	    $this->config['scheme'].'://'.
	    $this->config['realm'] .'.'.
	    $this->config['base']  .'/'.
	    $this->config['game']  .'/'.
	    $this->config['lang']  .'/profile/'.
	    $tag  .'/'. // Must include trailing slash after tag, even if hero is empty, else you get 404
	    $hero;
	}
	
	/**
	 * This method does the actual parsing of the profile page.
	 */
	private function parseTagPage($html) {
		// If the page was not loaded, return the error
		if ( is_array($html) && isset($html['error'])) return $html;
		$profile = $html->find('.profile-body'); // Limit scope to profile
		$career = array(
			'kills' => array(
			  'lifetime' => $profile[0]->find('.lifetime .num-kills',0)->innertext,
			  'elites'   => $profile[0]->find('.elite .num-kills'   ,0)->innertext,
		  ),
		  'played' => array(
		  	'barbarian'    => $this->untooltip($profile[0]->find('#tooltip-bar-barbarian'   ,0)->innertext),
		    'demon-hunter' => $this->untooltip($profile[0]->find('#tooltip-bar-demon-hunter',0)->innertext),
		    'monk'         => $this->untooltip($profile[0]->find('#tooltip-bar-monk'        ,0)->innertext),
		    'witch-doctor' => $this->untooltip($profile[0]->find('#tooltip-bar-witch-doctor',0)->innertext),
		    'wizard'       => $this->untooltip($profile[0]->find('#tooltip-bar-wizard'      ,0)->innertext),
		  ),
		);
		
		// Parse top heroes
		foreach($profile[0]->find('a.hero-portrait-wrapper') as $heroes) {
			$hero = array();
			$hero['id'] = substr($heroes->href, strrpos($heroes->href, '/')+1);
			foreach($heroes->find('span') as $herodata) {
				switch($herodata->class) {
					case 'name':
					  $hero['name'] = $herodata->innertext;
					  break;
					case 'skill-measure':
					  $hero['skill'] = substr($herodata->innertext,0,-12);
					  break; 
					case 'level':
					  $hero['level'] = $herodata->innertext;
					  break;
					default: // This will only leave the profile picture
					  list($hero['class'], $hero['gender']) = explode('-',substr($herodata->class,14));
				}
			}
			$career['heroes'][$hero['id']] = $hero;
		}
		
		// Parse other heroes
		foreach($profile[0]->find('.other-heroes li') as $herow) {
			$hero = "";
			$hero['id'] = $herow->find(a,0)->href;
			$hero['id'] = substr($hero['id'], strrpos($hero['id'], '/')+1);
			foreach($herow->find('span') as $herodata) {
				switch(substr($herodata->class, 5)) {
					case 'col-measure':
					  $hero['skill'] = substr($herodata->innertext, 0,strpos($herodata->innertext, ' '));
					  break;
					case 'col-hero':
					  $hero['name'] = trim(strip_tags($herodata->innertext, ''));
					  break;
					case 'col-class':
					  list($hero['level'], $hero['class1'], $hero['class2']) = explode(' ', strip_tags($herodata->innertext, ''));
					  $hero['class'] = $hero['class1'].' '.$hero['class2'];
					  unset($hero['class1'], $hero['class2']);
					case 'icon-frame':
					  if(strpos($herodata->innertext, 'female.png')) {
						  $hero['gender'] = 'female';
					  } else{
						  $hero['gender'] = 'male';
					  }
				}
			}
			$career['heroes'][$hero['id']] = $hero;
    }

    // Parse progression, Normal
    list($career['progress']['normal']['difficulty'], , $career['progress']['normal']['act']) = 
      explode(' ',$profile[0]->find('#progression-tooltip-1 h3',0)->innertext);
    $career['progress']['normal']['difficulty'] = trim($career['progress']['normal']['difficulty']);
    $career['progress']['normal']['hero'] = $profile[0]->find('#progression-tooltip-1 p.hero-name',0)->innertext;

		// Parse progression, Hardcore
    list($career['progress']['hardcore']['difficulty'], , $career['progress']['hardcore']['act']) = 
      explode(' ',$profile[0]->find('#progression-tooltip-2 h3',0)->innertext);
    $career['progress']['hardcore']['difficulty'] = trim($career['progress']['hardcore']['difficulty']);
    $career['progress']['hardcore']['hero'] = $profile[0]->find('#progression-tooltip-2 p.hero-name',0)->innertext;

    // Parse artisans, Blacksmith
    $career['artisans']['blacksmith']['normal']   = $profile[0]->find('.blacksmith .normal   .value',0)->innertext;
    if ($career['artisans']['blacksmith']['normal'] == '&#8211;' ) $career['artisans']['blacksmith']['normal']=0;

    $career['artisans']['blacksmith']['hardcore'] = $profile[0]->find('.blacksmith .hardcore .value',0)->innertext;
    if ($career['artisans']['blacksmith']['hardcore'] == '&#8211;' ) $career['artisans']['blacksmith']['hardcore']=0;

    // Parse artisans, Blacksmith
    $career['artisans']['jeweler']['normal']   = $profile[0]->find('.jeweler .normal   .value',0)->innertext;
    if ($career['artisans']['jeweler']['normal'] == '&#8211;' ) $career['artisans']['jeweler']['normal']=0;

    $career['artisans']['jeweler']['hardcore'] = $profile[0]->find('.jeweler .hardcore .value',0)->innertext;
    if ($career['artisans']['jeweler']['hardcore'] == '&#8211;' ) $career['artisans']['jeweler']['hardcore']=0;

		return $career;
	}
	
	/**
	 * Extracts data from battle.net tooltips (time distribution)
	 */
	private function untooltip($str) {
		$parts = array('time_percent'=>0, 'max_level'=>0, 'max_difficulty'=>0);
		// Need to remove leading <h3> tag and split info
		$str = substr($str, strpos($str, '</h3>')+5);
		list( $parts['time_percent'], $parts['max_level'], $parts['max_difficulty']) = explode('<br />', $str);
		$parts['time_percent'] = trim(str_replace('%','',$parts['time_percent']));
		$parts['max_level']    = substr(trim($parts['max_level']), 15);
		$parts['max_difficulty'] = substr(trim($parts['max_difficulty']),12);

    return $parts;
	}
	
 /**
	 * Get a web file (HTML, XHTML, XML, image, etc.) from a URL.  Return an
	 * array containing the HTTP server response header fields and content.
	 */
	private function get_web_page( $url ){
	    $options = array(
	        CURLOPT_RETURNTRANSFER => true,     // return web page
	        CURLOPT_HEADER         => false,    // don't return headers
	        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
	        CURLOPT_ENCODING       => "",       // handle all encodings
	        CURLOPT_USERAGENT      => "spider", // who am i
	        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
	        CURLOPT_CONNECTTIMEOUT => 20,      // timeout on connect
	        CURLOPT_TIMEOUT        => 20,      // timeout on response
	        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
	    );

	    $ch      = curl_init( $url );
	    curl_setopt_array( $ch, $options );
	    $content = curl_exec( $ch );
	    $err     = curl_errno( $ch );
	    $errmsg  = curl_error( $ch );
	    $header  = curl_getinfo( $ch );
	    curl_close( $ch );

	    /*
	    	$header['errno']   = $err;
		    $header['errmsg']  = $errmsg;
		    $header['content'] = $content;
		  */
	    return $content;
	}
	
	/**
	 * File based persistent storage setter. Could be replaced with a db call.
	 */
	private function saveProfiles() {
    switch(D3P_STORAGE) {
	    case 'file':
	      $this->saveProfilesToFile();
	      break;
    }
	}
	
	/**
	 * Saves profiles to a flat file
	 */
	private function saveProfilesToFile() {
		$fp = fopen(D3P_DATAFILE, 'w');
		fwrite($fp, json_encode($this->profiles));
		fclose($fp);		
	}
	
	/**
	 * Load profile data. More options to appear soon.
	 */
	private function loadProfiles() {
    switch(D3P_STORAGE) {
	    case 'file':
	      $this->loadProfilesFromFile();
	      break;
    }
	}
	
	/**
	 * Loads profiles from flat file.
	 */
	private function loadProfilesFromFile() {
    $this->profiles = json_decode(file_get_contents(D3P_DATAFILE));
	}
}