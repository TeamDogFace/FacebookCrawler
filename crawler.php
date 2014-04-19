<?php
require  'facebook-php-sdk/src/facebook.php';
$facebook = new Facebook(array('appId' => 'YOUR_APP_ID', 'secret' => 'YOUR_SECRET'	     
//https://graph.facebook.com/oauth/access_token?client_id=YOUR_APP_ID&client_secret=YOUR_APP_SECRET&grant_type=client_credentials
	
));
?>
<!DOCTYPE  html>
<html>
	<head>
		<title>
			Dog Crawler
		</title>
		
	</head>
	<body>
		<?php
		//test for curl
		if (extension_loaded("curl")){
			echo  "cURL extension is loaded<br>";
		}
		else{
			echo  "cURL extension is not available<br>";
		}
		
		try{
			$fb_db = new PDO('sqlite:development.sqlite3');
			$fb_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$urls = $fb_db->query('SELECT * FROM facebook_urls');
			$searches = $fb_db->query('SELECT date_lost FROM searches');
			
			foreach($searches  as  $search){
				echo "<pre>";
				var_dump($search);
				echo "</pre>";
				$date_lost = $search['date_lost'];
				//next 2 lines remove timestamp from date string:
				$date_lost = explode(" ", $date_lost);
				$date_lost = $date_lost[0];
				
			}
			
			$dog_array = array();
			
			foreach($urls  as  $row) {
				echo "<pre>";
				var_dump($row);
				echo "</pre>";
				//get facebook url from database and remove "http://www.facebook.com/" from it
				$fb_url = $row['url'];
				$fb_url = str_replace('https://www.facebook.com/', '', $fb_url);
				//create an array for this url
				$temp_array = array();
				
				//$request = $fb_url.'/photos/uploaded?fields=created_time,link,images&limit=1';
				$request = $fb_url.'/photos/uploaded?fields=source,created_time,link&since='.$date_lost;
				echo  $request;
				$response = $facebook->api($request, 'GET');
				echo "<pre>";
				var_dump($response);
				echo "</pre>";
				$counter = 0;
				foreach($response['data']  as  $post){
					//temp array for each dog
					$temp_temp_array = array();
					foreach($post  as  $key => $value){
						echo  "<ul>";
						if ($key == "source"){
							//add the image to the dog array
							echo  "<li><img src=".$value."></img></li>";
							$path =  "images/".$fb_url."/".$counter.".jpg";
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
						echo  "</ul>";
							
					}
					$temp_array[] = $temp_temp_array;
				}
				//add temp array to the dog array
				$dog_array[$fb_url] = $temp_array;
			}
			/*
				   		foreach ($response['data'] as $data) {
					      foreach ($data as $blank_array){
					      	foreach ($blank_array as $key => $inner_value) {
					      		if ($key == "images"){
					            	foreach ($inner_value as $image => $img_url){
					            		if($image == "source"){
					            			echo "<ul>";
								            echo "<li><img src=".$img_url."></img></li>";
								            echo "</ul>";	
					            		}	
					            	}
					      	}
					      }
					      
					      
					         //if ($key == "source"){
					            //echo "<ul>";
					            //echo "<li><img src=".$inner_value."></img></li>";
					            //echo "</ul>";
					      }
				   		}
				    }
				   		*/
			/*
				   		$decoded_response = json_decode($response, true);
				   		echo "<ul>";
				   		foreach($decoded_response['id'] as $id){
				   			echo "<li>".$id."</li>";	
				   		}	   		
				   		echo "</ul>";
				   		*/
			//$fb_id = $row['url'];
			//echo $fb_id;
			//echo $facebook->api($fb_url, 'GET');
			//$url = "https://graph.facebook.com/?ids={$fb_url}&fields=id";
			//$encoded = urlencode($url);
			//$response = file_get_contents($url);
			//$decoded_response = json_decode($response, true);
			//print_r('<h1>from: '.$url.'</h1>');
			   				   		//$fb_id = "272632972777405";
					   //echo '<pre>';
			//print_r($decoded_response['data']);
			//echo '</pre>';
					   //foreach ($decoded_response['data'] as $value) {
			//foreach ($value as $key => $inner_value) {
			//if ($key == "source"){
			//echo "<ul>";
			//echo "<li><img src=".$inner_value."></img></li>";
			//echo "</ul>";
			//}
			//}    
			//}
			$user = 'test_db';
			$pass = 'password';
			$dog_db = new PDO('mysql:host=localhost;dbname=test_db', $user, $pass);
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
			
			// close database connections
			$fb_db = null;
			$dog_db = null;
			
			
			echo  "<h1>Dog array:</h1><pre>";
			var_dump($dog_array);
			echo  "</pre>";
			
		}
		catch(PDOException $e){
			echo  $e->getMessage(); 
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
	</body>
</html>
