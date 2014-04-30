<?php
require  'facebook-php-sdk/src/facebook.php';
$facebook = new Facebook(array('appId' => 'YOUR_APP_ID', 'secret' => 'YOUR_SECRET'));	     
//https://graph.facebook.com/oauth/access_token?client_id=YOUR_APP_ID&client_secret=YOUR_APP_SECRET&grant_type=client_credentials

//test for curl
if (extension_loaded("curl")){
	echo  "cURL extension is loaded" . PHP_EOL;
}
else{
	echo  "cURL extension is not available" . PHP_EOL;
}

try{
	//get the web app database
	$fb_db = new PDO('mysql:host=localhost;dbname=dbname', 'usrname', 'pw');
	$fb_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$urls = $fb_db->query('SELECT * FROM facebook_urls');
	$searches = $fb_db->query('SELECT date_lost FROM searches');
	$search_ids = $fb_db->query('SELECT id FROM searches');
	
	foreach($searches  as  $search){
		//echo "<pre>";
		//var_dump($search);
		//echo "</pre>";
		$date_lost = $search['date_lost'];
		//next 2 lines remove timestamp from date string:
		$date_lost = explode(" ", $date_lost);
		$date_lost = $date_lost[0];
		
	}
	
	//this finds the most recent search ID and saves it as a variable used later inside the images folder
	foreach($search_ids as $s_id){
		$search_id = $s_id['id'];
		//echo $search_id . PHP_EOL;
	}
	
	$dog_array = array();
	
	$path =  "images/".$search_id."/";
	//create a folder for the search id
	if (!is_dir($path)){
    		mkdir($path, 0775, true);
	}
	
	foreach($urls  as  $row) {
		//echo "<pre>";
		//var_dump($row);
		//echo "</pre>";
		//get facebook url from database and remove "http://www.facebook.com/" from it
		$fb_url = $row['url'];
		$fb_url = str_replace('https://www.facebook.com/', '', $fb_url);
		//create an array for this url
		$temp_array = array();
		
		//$request = $fb_url.'/photos/uploaded?fields=created_time,link,images&limit=1';
		$request = $fb_url.'/photos/uploaded?fields=source,created_time,link&since='.$date_lost;
		//echo  $request;
		$response = $facebook->api($request, 'GET');
		//echo "<pre>";
		//var_dump($response);
		//echo "</pre>";
		$counter = 0;
		foreach($response['data']  as  $post){
			//temp array for each dog
			$temp_temp_array = array();
			foreach($post  as  $key => $value){
				//echo  "<ul>";
				if ($key == "source"){
					//add the image to the dog array
					//echo  "<li><img src=".$value."></img></li>";
					$path =  "images/".$search_id."/".$fb_url."/".$counter.".jpg";
					$dirname = dirname($path);
					//create a folder for the facebook page
					if (!is_dir($dirname)){
    						mkdir($dirname, 0775, true);
					}
					//download the image
					grab_image($value, $path);
					//add image info to array
					$temp_temp_array['path'] = $dirname."/";
					$temp_temp_array['filename'] = $counter.'.jpg';
					$counter++;
				}
				if($key == 'created_time'){
					//add the created_time (day posted) to the array
					$temp_temp_array['dateAdded'] = $value;
				}
				if($key == 'link'){
					//add the link to the dog array
					$temp_temp_array['listingURL'] = $value;
				}
				//echo  "</ul>";
					
			}
			$temp_array[] = $temp_temp_array;
		}
		//add temp array to the dog array
		$dog_array[$fb_url] = $temp_array;
	}
	
	// For putting photos in cache db
	//Create cache db
	$cache_db = new PDO('sqlite:cache.sqlite3');
	
	//set errormode
	$cache_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	//create tables
	$cache_db->exec("CREATE TABLE IF NOT EXISTS dogs(
		dogID		int primary key not null,
		listingURL	varchar(255) not null,
		dateAdded	date not null
		)");
		
	$cache_db->exec("CREATE TABLE IF NOT EXISTS photos(
		photoID		int primary key not null,
		filename	varchar(255) not null,
		path		varchar(255) not null,
		dogID		int not null,
		foreign key (dogID) references dogs(dogID)
		)");
		
	//insert data into cache db
	$sql_dogs = "INSERT INTO dogs (listingURL, dateAdded) values (:listingURL, :dateAdded)";
	$sql_photos = "INSERT INTO photos (filename, path, dogID) values (:filename, :path, :dogID)";
	
	foreach($dog_array as $fb_site){
		foreach($fb_site as $dog){
			//prepare sql insert statements
			$query = $cache_db->prepare($sql_dogs);
			$query->bindParam(':listingURL', $dog['listingURL']);
			$query->bindParam(':dateAdded', $dog['dateAdded']);
			$query->execute();
			
			$dog_id = $cache_db->lastInsertId();
			
			$query = $cache_db->prepare($sql_photos);
			$query->bindParam(':filename', $dog['filename']);
			$query->bindParam(':path', $dog['path']);
			$query->bindParam(':dogID', $dog_id);
			$query->execute();
		}
	}
	
	// close database connections
	$fb_db = null;
	//$dog_db = null;
	$cache_db = null;
	
	
	//echo  "<h1>Dog array:</h1><pre>";
	//var_dump($dog_array);
	//echo  "</pre>";
	
}
catch(PDOException $e){
	echo  $e->getMessage() . PHP_EOL; 
}

function  grab_image($url,$saveto){
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
	curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
	$raw = curl_exec($ch);
	curl_close($ch);
	if(file_exists($saveto)){
		unlink($saveto);	
	}
	$fp = fopen($saveto,'x');
	fwrite($fp, $raw);
	fclose($fp);
}
?>
