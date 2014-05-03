<?php
require  'facebook-php-sdk/src/facebook.php';
$facebook = new Facebook(array('appId' => '521884561263250', 'secret' => 'cc95d1118d819cc351c4d8319efe301b'));
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
	$fb_db = new PDO('mysql:host=localhost;dbname=here_doggie', 'root', 'SeniorDesign14');
	$fb_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	//$urls = $fb_db->query('SELECT * FROM facebook_urls WHERE ');
	$search_ids = $fb_db->query('SELECT id FROM searches');
	/*
	foreach($searches  as  $search){
		//echo "<pre>";
		//var_dump($search);
		//echo "</pre>";
		$date_lost = $search['date_lost'];
		//next 2 lines remove timestamp from date string:
		$date_lost = explode(" ", $date_lost);
		$date_lost = $date_lost[0];
	}
	*/
	
	//not sure if this is needed,
	//gets last search id and saves it in a variable, bad way of doing this
	foreach($search_ids as $s_id){
		$search_id = $s_id['id'];
	}

	//remove me!!
	$search_id = 22;
	//get date lost
	$searches = $fb_db->query('SELECT date_lost FROM searches WHERE id='.$search_id);
	foreach($searches as $search){
		$date_lost = $search['date_lost'];
		//remove timestamp
		$date_lost = explode(" ", $date_lost);
		$date_lost = $date_lost[0];
	}

	$dog_array = array();
	
	$path =  "images/".$search_id."/";
	//create a folder for the search id
	if (!is_dir($path)){
    		mkdir($path, 0775, true);
	}
	
	//get the urls
	$urls = $fb_db->query('SELECT * FROM facebook_urls WHERE search_id='.$search_id);

	foreach($urls  as  $row) {
		//echo "<pre>";
		//var_dump($row);
		//echo "</pre>";
		//get facebook url from database and remove "http://www.facebook.com/" from it
		$fb_url = $row['url'];
		$fb_url = strtolower($fb_url);
		if (strpos($fb_url, 'https://') === false){
			$fb_url = "https://".$fb_url;
		}
		echo "Getting ready to crawl sanitized URL: ".$fb_url.PHP_EOL;
		$fb_url = str_replace('https://www.facebook.com/', '', $fb_url);
		echo"Trimmed url: ".$fb_url.PHP_EOL;
		//create an array for this url
		$temp_array = array();
		
		//$request = $fb_url.'/photos/uploaded?fields=created_time,link,images,name&limit=1';
		var_dump($date_lost);
		$request = $fb_url.'/photos/uploaded?fields=source,created_time,link,name&since='.$date_lost;
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
					echo "Image downloaded and added to array: ".$counter.PHP_EOL;
					$counter++;
				}
				if($key == "created_time"){
					//add the created_time (day posted) to the array
					$temp_temp_array['dateAdded'] = $value;
					echo "Date_Added added to array.".PHP_EOL;
				}
				if($key == "link"){
					//add the link to the dog array
					$temp_temp_array['listingURL'] = $value;
					echo "Listing URL added to array.".PHP_EOL;
				}
				if($key == "name"){
					$temp_temp_array['description'] = $value;
					echo "Description added to array.".PHP_EOL;
				}
				//echo  "</ul>";
					
			}
			$temp_array[] = $temp_temp_array;
		}
		//add temp array to the dog array
		$dog_array[$fb_url] = $temp_array;
	}
	
	/* For putting photos in dog db
	$dbname = 'dbname';
	$user = 'test_db';
	$pass = 'password';
	$dog_db = new PDO('mysql:host=localhost;dbname='.$dbname , $user, $pass);
	$dog_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$sql_dogs = "INSERT INTO dogs (listingURL, dateAdded) values (:listingURL, :dateAdded)";
	$sql_photos = "INSERT INTO photos (filename, path, dogID) values (:filename, :path, :dogID)";
	
	foreach($dog_array as $fb_site){
		foreach($fb_site as $dog){
			//prepare sql insert statements
			$query = $dog_db->prepare($sql_dogs);
			$query->bindParam(':listingURL', $dog['listingURL']);
			$query->bindParam(':dateAdded', $dog['dateAdded']);
			$query->execute();
			
			$dog_id = $dog_db->lastInsertId();
			
			$query = $dog_db->prepare($sql_photos);
			$query->bindParam(':filename', $dog['filename']);
			$query->bindParam(':path', $dog['path']);
			$query->bindParam(':dogID', $dog_id);
			$query->execute();
		}
	}
	*/
	
	// For putting photos in cache db
	//Create cache db
	$cache_db = new PDO('sqlite:cache'.$search_id.'.sqlite3');
	
	//set errormode
	$cache_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	//create tables
	$cache_db->exec("CREATE TABLE IF NOT EXISTS dogs(
		id		integer primary key autoincrement,
		listingURL	varchar(255) not null,
		dateAdded	date not null,
		description	text
		)");
		
	$cache_db->exec("CREATE TABLE IF NOT EXISTS photos(
		photoID		integer primary key autoincrement,
		filename	varchar(255) not null,
		path		varchar(255) not null,
		dogID		integer unsigned not null,
		foreign key (dogID) references dogs(id)
		)");
		
	//insert data into cache db
	$sql_dogs = "INSERT INTO dogs (listingURL, dateAdded, description) values (:listingURL, :dateAdded, :description)";
	$sql_photos = "INSERT INTO photos (filename, path, dogID) values (:filename, :path, :dogID)";
	
	foreach($dog_array as $fb_site){
		foreach($fb_site as $dog){
			//prepare sql insert statements
			$query = $cache_db->prepare($sql_dogs);
			$query->bindParam(':listingURL', $dog['listingURL']);
			$query->bindParam(':dateAdded', $dog['dateAdded']);
			$query->bindParam(':description', $dog['description']);
			$query->execute();
			
			$dog_id = $cache_db->lastInsertId();
			echo('DOG ID: '.$dog_id.PHP_EOL);
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
	echo 'On line: ' . $e->getLine() . PHP_EOL;
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
