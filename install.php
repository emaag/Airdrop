<?php
/*
*	Airdrop for WordPress 
*	By Nicolas Bouliane
*/

if ( isset($_GET['install']) && $_GET['install']=='1' ){
	//Make sure all options are set
	$setup_options = array(
		'blog_title'=> isset($_GET['blog_title'])?$_GET['blog_title']:'My blog',
		'blog_user'=> isset($_GET['blog_user'])?$_GET['blog_user']:'admin',
		'blog_email'=> isset($_GET['blog_email'])?$_GET['blog_email']:'',
		'blog_pass'=> isset($_GET['blog_pass'])?$_GET['blog_pass']:'123456',
		
		'db_name'=> isset($_GET['db_name'])?$_GET['db_name']:'',
		'db_user'=> isset($_GET['db_user'])?$_GET['db_user']:'',
		'db_pass'=> isset($_GET['db_pass'])?$_GET['db_pass']:'',
		
		'lang'=> isset($_GET['lang'])?$_GET['lang']:'en_US',
		'debug'=> (isset($_GET['debug']))?'true':'false',
		'is_public'=> (isset($_GET['is_public']))?true:false,
		'plugins'=> (isset($_GET['plugins']))?urldecode($_GET['plugins']):''
	);
	
	//Download and extract WordPress
		downloadFile('http://wordpress.org/latest.zip','wordpress.zip');
		extractZip('wordpress.zip');
		
	//Configure WordPress' wp-config.php
		configureWordPress($setup_options);
		
	//Download selected plugins
		$slugs = explode(',',$setup_options['plugins']);
		foreach($slugs as $slug)
			configurePlugin(trim($slug),dirname(__FILE__).'/wordpress');
	
	/* WordPress.zip has all its contents in a subfolder (/wordpress), so we need
	*  to take everything out, move it to a temp directory, then move it back in
	*  the parent directory, since moving it directly to a non-empty folder would
	*  throw errors. */
			
	//Clean the directory (both necessary and safer)
		unlink('wordpress.zip'); //Remove the .zip
		unlink(__FILE__); //Remove this script
	
	//Move everything to a temp folder, then back in the directory.
		rename(dirname(__FILE__).'/wordpress',dirname(__FILE__).'/../tmp');
		rename(dirname(__FILE__).'/../tmp',dirname(__FILE__).'/');
	
	//Let WordPress create the tables and users
		chdir(dirname(__FILE__));
		define('WP_INSTALLING', true);
		require_once 'wp-load.php';
		require_once 'wp-admin/includes/upgrade.php';
		require_once 'wp-includes/wp-db.php';
		
		//Set the site URL. Otherwise, WordPress will think it's this file's URL
		define('WP_SITEURL', siteUrl());

		//Install WordPress
		wp_install($setup_options['blog_title'], $setup_options['blog_user'], $setup_options['blog_email'], true, null, $setup_options['blog_pass']);
}

//Fills wp-config.php
function configureWordPress($options){
	//Make sure all options are correctly defined
		$options = array(
			'db_name'=> isset($options['db_name'])?$options['db_name']:'',
			'db_user'=> isset($options['db_user'])?$options['db_user']:'',
			'db_pass'=> isset($options['db_pass'])?$options['db_pass']:'',
			'lang'=> isset($options['lang'])?$options['lang']:'en_US',
			'debug'=> (isset($options['debug']) && ($options['debug']===true || $options['debug']=='true'))?'true':'false'
		);
	
	//Start configuring options
	
		//Load wp-config-sample.php's contents
			$wpconfig = implode("\n",file('wordpress/wp-config-sample.php'));
			
		//Replace the file's contents
			$wpconfig = str_replace('database_name_here', $options['db_name'], $wpconfig);
			$wpconfig = str_replace('username_here', $options['db_user'], $wpconfig);
			$wpconfig = str_replace('password_here', $options['db_pass'], $wpconfig);
			$wpconfig = str_replace("define('WP_DEBUG', false);", "define('WP_DEBUG', " . $options['debug'] . ');', $wpconfig);
			$wpconfig = str_replace("define('WPLANG', '');", "define('WPLANG', '" . $options['lang'] . "');", $wpconfig);
			
			//The random strings for auth keys
				$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-=.+<>()*_:[]$%?';
				$phrase = '';
				for ($i=0;$i<64;$i++)
					$phrase .= substr($chars,rand(0,77),1);
					
				$wpconfig = str_replace("put your unique phrase here", $phrase, $wpconfig);
			
	//Save to wp-config.php
		$file_pointer_new = fopen('wordpress/wp-config.php', 'w');
		fwrite($file_pointer_new, $wpconfig, strlen($wpconfig));
}

//Extracts a zip file
function extractZip($file,$destination=''){
	$zip = new ZipArchive();
	
	if($destination == '')
		$destination = dirname(__FILE__);
	
	//Open and extract the archive
	if ($zip->open($file) === TRUE) {
		$zip->extractTo($destination);
		$zip->close();
	}
}

//Downloads a file
function downloadFile($url,$localfile){
	//Initialize curl
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		
	//Create the local file
		$file_pointer = fopen($localfile, 'w');
		
	//Set the file download path
		curl_setopt($curl, CURLOPT_FILE, $file_pointer);
	
	//Download the file
		curl_exec ($curl);
	
	//Close the download
		curl_close ($curl);
		fclose($file_pointer);
}

//Downloads, extracts and configures plugins
function configurePlugin($slug,$plugins_path){
	//Download the plugin
		downloadFile('http://downloads.wordpress.org/plugin/'.$slug.'.zip', $slug.'.zip');
	
	//Extract the plugin
		extractZip($slug.'.zip', $plugins_path.'/wp-content/plugins');
		
	//Delete the .zip
		unlink($slug.'.zip');
}

//Guesses the root URL
function siteUrl(){
	$page_url = wp_guess_url();//WordPress uses this function to find the site's URL
	$site_url = substr($page_url, 0, strpos($page_url,'/install.php'));//Only keep the part before the page URL
	return $site_url;
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Airdrop for WordPress</title>
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.0/jquery.min.js"></script>
	<script type="text/javascript">
		$(document).ready(function(){
			$('#loading').hide();
			$('#step2').hide();
			$('#go').click(function(){
				$('#go').hide();
				$('#loading').show();
				$.ajax({
						type: "GET",
						url: "install.php",
						data: $('form').serialize()
					}).done(function(msg) {
						$('#loading').hide();
						$('#step2').fadeIn();
					});
			});
			
			$('.helpbutton').click(function(){
				var helpid = $(this).attr('id').slice(0,-7);
				$('#'+helpid).slideToggle();
			});
			
		});
	</script>
	<style type="text/css">
		body{background:#eee;margin:0}
		body,input,textarea{font:16px/26px sans-serif}
		#wrapper{width:600px;padding:25px 50px 50px;margin:0 auto;background:#fff}
		h1{line-height:76px!important;margin:0;padding:0;font-size:76px;letter-spacing:-2px}
		p.subtitle{margin-top:-17px;padding-top:0}
		p.info,p.help{background:#eef;padding:15px;color:#334}
		p.help{display:none}
			span.helpbutton{background:#eef;padding:2px 5px;border-radius:20px;cursor:pointer}
		input,textarea{width:594px;display:block;padding:3px}
		input#debug{width:auto;display:inline}
		textarea{height:200px}
		#go{display:block;color:#fff;background:#77f;margin:20px auto 0;padding:10px 0;text-align:center;width:200px;cursor:pointer;border:1px solid #66e;text-shadow:0 -1px 0 #33d;border-radius:30px}
		#go:active{background:#33a;border-color:#33a}
		#footer{text-align:center}
		#footer a{color:#33d;text-decoration:none}
	</style>
</head>
<body>
	<form id="wrapper">
		<div id="step1">
			<h1>Airdrop</h1>
			<p class="subtitle">Simple WordPress deployment</p>
			<p class="info">Airdrop automates WordPress installation from the download to the database setup. Fill this form, sit back and enjoy the show!</p>
			<p><label for="db_name">Database name: </label><input type="text" name="db_name" id="db_name"/></p>
			<p><label for="db_user">Database user: </label><input type="text" name="db_user" id="db_user"/></p>
			<p><label for="db_pass">Database password: </label><input type="password" name="db_pass" id="db_pass"/></p>
			<p><label for="blog_user">Wordpress admin username: </label><input type="text" name="blog_user" id="blog_user" value="admin"/></p>
			<p><label for="blog_email">Wordpress admin email: </label><input type="text" name="blog_email" id="blog_email"/></p>
			<p><label for="blog_pass">Wordpress admin password: </label><input type="password" name="blog_pass" id="blog_pass"/></p>
			<p><label for="blog_title">Site title: </label><input type="text" name="blog_title" id="blog_title"/></p>
			<p><label for="lang">Language: </label><input type="text" name="lang" id="lang" value="en_US"/></p>
			<p><label for="debug">Enable debugging: </label><input type="checkbox" name="debug" id="debug"/><span id='debughelp-button' class='helpbutton'>?</span></p>
				<p id='debughelp' class='help'>Enabling debugging will allow WordPress to show errors on the site. Although useful when developing a site, this should be disabled on the production server.</p>
			<label for="plugins">Plugins to install (slugs separated by a comma): </label><span id='pluginhelp-button' class='helpbutton'>?</span>
				<p id='pluginhelp' class='help'>Slugs are used to identify themes. You can find them in the plugin page's URL. For example: http://wordpress.org/extend/plugins/<b>tumblr-importer</b>/</p>
			<textarea name="plugins" id="plugins">wordpress-importer, w3-total-cache, qtranslate</textarea></p>
			<a id="go">Install WordPress!</a>
			<div id="loading">
				<img src="data:image/jpg;base64,R0lGODlhEAAQAPYAAP///wAAAPr6+pKSkoiIiO7u7sjIyNjY2J6engAAAI6OjsbGxjIyMlJSUuzs7KamppSUlPLy8oKCghwcHLKysqSkpJqamvT09Pj4+KioqM7OzkRERAwMDGBgYN7e3ujo6Ly8vCoqKjY2NkZGRtTU1MTExDw8PE5OTj4+PkhISNDQ0MrKylpaWrS0tOrq6nBwcKysrLi4uLq6ul5eXlxcXGJiYoaGhuDg4H5+fvz8/KKiohgYGCwsLFZWVgQEBFBQUMzMzDg4OFhYWBoaGvDw8NbW1pycnOLi4ubm5kBAQKqqqiQkJCAgIK6urnJyckpKSjQ0NGpqatLS0sDAwCYmJnx8fEJCQlRUVAoKCggICLCwsOTk5ExMTPb29ra2tmZmZmhoaNzc3KCgoBISEiIiIgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH+GkNyZWF0ZWQgd2l0aCBhamF4bG9hZC5pbmZvACH5BAAIAAAAIf8LTkVUU0NBUEUyLjADAQAAACwAAAAAEAAQAAAHaIAAgoMgIiYlg4kACxIaACEJCSiKggYMCRselwkpghGJBJEcFgsjJyoAGBmfggcNEx0flBiKDhQFlIoCCA+5lAORFb4AJIihCRbDxQAFChAXw9HSqb60iREZ1omqrIPdJCTe0SWI09GBACH5BAAIAAEALAAAAAAQABAAAAdrgACCgwc0NTeDiYozCQkvOTo9GTmDKy8aFy+NOBA7CTswgywJDTIuEjYFIY0JNYMtKTEFiRU8Pjwygy4ws4owPyCKwsMAJSTEgiQlgsbIAMrO0dKDGMTViREZ14kYGRGK38nHguHEJcvTyIEAIfkEAAgAAgAsAAAAABAAEAAAB2iAAIKDAggPg4iJAAMJCRUAJRIqiRGCBI0WQEEJJkWDERkYAAUKEBc4Po1GiKKJHkJDNEeKig4URLS0ICImJZAkuQAhjSi/wQyNKcGDCyMnk8u5rYrTgqDVghgZlYjcACTA1sslvtHRgQAh+QQACAADACwAAAAAEAAQAAAHZ4AAgoOEhYaCJSWHgxGDJCQARAtOUoQRGRiFD0kJUYWZhUhKT1OLhR8wBaaFBzQ1NwAlkIszCQkvsbOHL7Y4q4IuEjaqq0ZQD5+GEEsJTDCMmIUhtgk1lo6QFUwJVDKLiYJNUd6/hoEAIfkEAAgABAAsAAAAABAAEAAAB2iAAIKDhIWGgiUlh4MRgyQkjIURGRiGGBmNhJWHm4uen4ICCA+IkIsDCQkVACWmhwSpFqAABQoQF6ALTkWFnYMrVlhWvIKTlSAiJiVVPqlGhJkhqShHV1lCW4cMqSkAR1ofiwsjJyqGgQAh+QQACAAFACwAAAAAEAAQAAAHZ4AAgoOEhYaCJSWHgxGDJCSMhREZGIYYGY2ElYebi56fhyWQniSKAKKfpaCLFlAPhl0gXYNGEwkhGYREUywag1wJwSkHNDU3D0kJYIMZQwk8MjPBLx9eXwuETVEyAC/BOKsuEjYFhoEAIfkEAAgABgAsAAAAABAAEAAAB2eAAIKDhIWGgiUlh4MRgyQkjIURGRiGGBmNhJWHm4ueICImip6CIQkJKJ4kigynKaqKCyMnKqSEK05StgAGQRxPYZaENqccFgIID4KXmQBhXFkzDgOnFYLNgltaSAAEpxa7BQoQF4aBACH5BAAIAAcALAAAAAAQABAAAAdogACCg4SFggJiPUqCJSWGgkZjCUwZACQkgxGEXAmdT4UYGZqCGWQ+IjKGGIUwPzGPhAc0NTewhDOdL7Ykji+dOLuOLhI2BbaFETICx4MlQitdqoUsCQ2vhKGjglNfU0SWmILaj43M5oEAOwAAAAAAAAAAAA==" />
				Setting up WordPress (this may take a while)
			</div>
		</div>
		<div id="step2">
			Done! <a href="wp-admin">Click here to go to your site's dashboard</a>.
		</div>
		<input type="hidden" name="install" value="1"/>
	</form>
	<p id="footer"><a href="http://inoui.ca">Made by Inoui</a></p>
</body>
</html>