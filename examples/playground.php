<?php
	
//defined( 'ABSPATH' ) OR exit;
//define( 'MOBILEPRESS__MINIMUM_WP_VERSION', '4.7' );
//trailingslashit( ABSPATH );
//require_once( trailingslashit( ABSPATH ) . 'wp-load.php' );
require($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');	
wp_cookie_constants();
//echo get_current_user_id();
if( get_current_user_id()!=2 && get_current_user_id()!=1 ) {  
	header("HTTP/1.1 401 Unauthorized");
	exit;
}
require_once ('img_crop.php');

    // Read $url and $sel from request ($_POST | $_GET)
    $debug =@$_POST['debug'] ?: @$_GET['debug'];
    $url = @$_POST['url'] ?: @$_GET['url'];
   
    $go  = @$_POST['go']  ?: @$_GET['go'];
    
    $ct_status= @$_POST['ct_status'] ?: @$_GET['ct_status'];
    $state= @$_POST['state'] ?: @$_GET['state'];
    $property_type= @$_POST['property_type'] ?: @$_GET['property_type'];
    
    if($ct_status=='select' || $state=='select' || $property_type =='select'){
	    echo 'Hey yo!!! You MUST choose an option on each select.. take it easy!!!!!';
	    die();
    }
    
        
    $rm = strtoupper(getenv('REQUEST_METHOD') ?: $_SERVER['REQUEST_METHOD']);
// var_export(compact('url', 'sel', 'go')+[$rm]+$_SERVER);
    if ( $rm == 'POST' ) {
        require_once __DIR__ . '/../hquery.php';

        $config = [
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
            'accept_html' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ];

        // Enable cache
        hQuery::$cache_path = sys_get_temp_dir() . '/hQuery/';

        // Results acumulator
        $return = array();

        // If we have $url to parse and $sel (selector) to fetch, we a good to go
        if($url && $ct_status && $state && $property_type ) {
            try {
                $doc = hQuery::fromUrl(
                    $url
                  , [
                        'Accept'     => $config['accept_html'],
                        'User-Agent' => $config['user_agent'],
                    ]
                );
                if($doc) {
                    
                    programmatically_create_post($doc, $url,$ct_status,$state,$property_type); 
                }
                else {
                    $return['request'] = hQuery::$last_http_result;
                }
            }
            catch(Exception $ex) {
                $error = $ex;
            }
        }
    }
    
function encodeForWP($str){
	$str=strtolower(str_replace(","," ",$str));
	$str=str_replace("/","-",$str);
	$str=urlencode($str);
//	$str=str_replace("%2f","-",$str);
	$str=str_replace("/","-",$str);
	$str=str_replace("++","-",$str);	
	$str=str_replace("+","-",$str);	
	return $str;
}

function getElementsText($elements){
	$result="";
	if(!empty($elements)):    
		foreach($elements as $pos => $el):    
	    	$result .= $el;
	    	
	    endforeach;
	endif;    
	return $result;
}
 
            
function getElementsHtml($elements){
	$result="";
	if(!empty($elements)):    
		foreach($elements as $pos => $el):   
	    	$result .= $el->outerHtml();
	    endforeach;
	endif;    
	return $result;
}


function getImages($elements){
	$result="";
	if(!empty($elements)){
		$i=0;    
		foreach($elements as $pos => $el){
			
	    	$doc = new DOMDocument();
			$doc->loadHTML($el->outerHtml());
			$xpath = new DOMXPath($doc);
			$result[$i] = $xpath->evaluate("string(//img/@src)");
	    	
	    	
	    	$i++;
	    }
	}    
	return $result;
}






function uploadRoomImages($parent_post_id, $images){
	
	//$galery[0]=array();
	
	for($b=0;$b<count($images);$b++){
		$file=$images[$b];
			
		//$file = '/path/to/file.png';
		$filename = basename($file);
		$temp_path=$_SERVER['DOCUMENT_ROOT'] . "/wp-content/uploads/rooms/".$filename;
		copy($file, $temp_path);
		crop_images($temp_path);
		$file=$temp_path;
		
		//die();
		$upload_file = wp_upload_bits($filename, null, file_get_contents($file));
		if (!$upload_file['error']) {
			
			//print("<pre>".print_r($upload_file,true)."</pre>");
			$wp_filetype = wp_check_filetype($filename, null );
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_parent' => $parent_post_id,
				'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
				'post_content' => '',
				'post_status' => 'inherit'
			);
			$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $parent_post_id );
			if($b==0)$thumb=$attachment_id;
			$gallery[$attachment_id] = wp_get_attachment_url($attachment_id);
			if (!is_wp_error($attachment_id)) {
				require_once($_SERVER['DOCUMENT_ROOT'] . "/wp-admin" . '/includes/image.php');
				$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
				wp_update_attachment_metadata( $attachment_id,  $attachment_data );
			}
		}	
	
	}
	add_post_meta($parent_post_id, "_thumbnail_id", $thumb, true);
	add_post_meta($parent_post_id, "_ct_slider", $gallery, true);
	add_post_meta($parent_post_id, "slide_template", "default", true);
	//$prueba=get_post_meta("2760","_ct_slider",true);
	//print("<pre>".print_r($gallery,true)."</pre>");
	//print("<pre>".print_r($prueba,true)."</pre>");
	//die();
}
		

/**
 * A function used to programmatically create a post in WordPress. The slug, author ID, and title
 * are defined within the context of the function.
 *
 * @returns -1 if the post was never created, -2 if a post with the same title exists, or the ID
 *          of the post if successful.
 */
function programmatically_create_post($doc, $url,$ct_status,$state,$property_type) {
	
	
	$title2=getElementsText($doc->find("h1 > a:parent")->children());
	$content=getElementsHtml($doc->find("#detail-content")->children()); 
	$content=substr($content,0, strlen($content)-39);
	$title1=getElementsText(hQuery::fromHTML($content)->find("h2"));
	$slug=encodeForWP($title1);
	echo $slug;
	$price=getElementsText($doc->find(".priceask")->children());
	$frecuency=substr($price,10, 2);
	$price=substr($price,6, 3);//->parent());   
	
	
	
	$images=getElementsText($doc->find("#detail-images")->children()); 
	$images = hQuery::fromHTML($images);
	$images=getImages($images->find("a > img:parent"));
	



	// Initialize the page ID to -1. This indicates no action has been taken.
	$post_id = -1;

	// Setup the author, slug, and title for the post
	$author_id = 2;
	//$slug = $slug1;
	$title = $title1;
	
	// If the page doesn't already exist, then create it
	if( null == get_page_by_path( $slug , '','listings') ) {

		// Set the post ID so that we know the post was created successfully
		$post_id = wp_insert_post(
			array(
				
				'post_author'		=>	$author_id,
				
				'post_content'		=>	$content,
				'ping_status'		=>	'closed',

				'post_name'			=>	$slug,
				'post_title'		=>	$title,
				'post_status'		=>	'publish',
				'post_type'			=>	'listings'
			)
		);
		
		
			
		
		uploadRoomImages($post_id, $images);
		
		add_post_meta($post_id, "_ct_price", $price, true);
		
		//add_post_meta($post_id, "_ct_price_prefix", "PREFIJO DEL PRECIO", true);
		
		//SUFIJO DEL PRECIO
		add_post_meta($post_id, "_ct_price_postfix", $frecuency, true);


		//NOTAS QUE NO SE VEN DESDE LA WEB	
		add_post_meta($post_id, "_ct_ownernotes", $url, true);
		
		
		//TITULO ALTERNATIVO
		add_post_meta($post_id, "_ct_listing_alt_title", $title2, true);
		
		
		add_post_meta($post_id, "_ct_ownernotes", $url, true);
		
		
		
		//ADD CITY LONDON
		wp_set_object_terms( $post_id, 153, 'city', true );

		//ADD  STATUS
		wp_set_object_terms( $post_id, $ct_status, 'ct_status', true );
		
		//ADD AREA
		wp_set_object_terms( $post_id, $state, 'state', true );
		
		//ADD PROPERTY TYPE
		wp_set_object_terms( $post_id, $property_type, 'property_type', true );

		echo 'DONE';
	}else{
			
		$page = get_page_by_path( $slug ,'', 'listings');

		$post_id = $page->ID;

		// Set the post ID so that we know the post was created successfully
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'		=>	$title,
				'post_author'		=>	$author_id,
				'post_name'			=>	$slug,
				'post_content'		=>	$content,
				'post_type'			=>	'listings',
				'post_status'		=>	'publish',
				'ping_status'		=>	'closed',
			)
		);

		update_post_meta($post_id, "_ct_price", $price);
		
		//add_post_meta($post_id, "_ct_price_prefix", "PREFIJO DEL PRECIO", true);
		
		//SUFIJO DEL PRECIO
		update_post_meta($post_id, "_ct_price_postfix", $frecuency);


		//NOTAS QUE NO SE VEN DESDE LA WEB	
		update_post_meta($post_id, "_ct_ownernotes", $url);
		
		
		//TITULO ALTERNATIVO
		update_post_meta($post_id, "_ct_listing_alt_title", $title2);
		
		
		update_post_meta($post_id, "_ct_ownernotes", $url);
			
			
		
		//ADD CITY LONDON
		wp_set_object_terms( $post_id, 153, 'city', false );

		//ADD  STATUS
		wp_set_object_terms( $post_id, $ct_status, 'ct_status', false );
		
		//ADD AREA
		wp_set_object_terms( $post_id, $state, 'state', false );
		
		//ADD PROPERTY TYPE
		wp_set_object_terms( $post_id, $property_type, 'property_type', false );
		
		echo 'UPDATED';	
	}
		

} // end programmatically_create_post
//add_filter( 'after_setup_theme', 'programmatically_create_post' )


function wruk_get_object_terms_select($tax_term){
	//wp_get_object_terms( $object_ids, 'ct_status', $args );
	
	
	$terms = get_terms( array(
	    					'taxonomy' => $tax_term,
							'hide_empty' => false,
						));
						
	
	//print("<pre>".print_r($terms,true)."</pre>");
	$a="";
	$a.='<select
			id="'.$tax_term.'" 
			name="'.$tax_term.'">';
    $a.='<option value="select"'.selected('select','select',false).'>select</option>';
    	foreach($terms as $key => $term){
	    	//echo $term->name;
	    	//
			$a.='<option value="'.$term->name.'"'.selected('','select',false).'>'.$term->name.'</option>';
			//$a.='<option value="'.$select['value'].'"'.selected($options[$args['option']],$select['value'],false).'>'.$select['name'].'</option>';
    	}
	$a.= '</select>';
	
	return $a;
}



?>



<!DOCTYPE html>
<html>
<head>
    <meta charset="utf8" />
    <title>hQuery playground example</title>
    <style lang="css">
        * {
            box-sizing: border-box;
        }
        html, body {
            position: relative;
            min-height: 100%;
        }
        header, section {
            margin: 10px auto;
            padding: 10px;
            width: 90%;
            max-width: 1200px;
            border: 1px solid #eaeaea;
        }

        input {
            width: 100%;
        }
    </style>
</head>
<body>
    <header class="selector">
        <form name="hquery" action="" method="post">
            <p><label>URL: <input type="url" name="url" value="<?=htmlspecialchars(@$url, ENT_QUOTES);?>" placeholder="ex. https://iulianarocks.com" autofocus class="form-control" /></label></p>
        <p>Room Status: <?=wruk_get_object_terms_select('ct_status');?></p>
        <p>Property type: <?=wruk_get_object_terms_select('property_type');?></p>
        <p>Area: <?=wruk_get_object_terms_select('state');?></p>
                <button type="submit" name="go" value="elements" class="btn btn-success">Post room</button>
                            </p>

            <?php if( true ): //!empty($error) ?>
            <div class="error">
                <h3>Error:</h3>
                <p>
                    <?=$error->getMessage();?>
                </p>
            </div>
            <?php endif; ?>
        </form>
    </header>

    <section class="result">
<?php if( true ): //!empty($error) ?>
                <hr />
                <table style="width: 100%">
    <thead>
        <tr>
        	<th>Element</th>
			<th>value</th>
		</tr>
	</thead>
    <tbody>
        <tr>
            <td><i class="col-xs-1">Title 1</i></td>
            <td><?=$title1;?><br><?=encodeForWP($title1);?></td>
            
        </tr>
        <tr>
            <td><i class="col-xs-1">Title 2</i></td>
            <td><?=$title2;?></td>
        </tr>
        <tr>
            <td><i class="col-xs-1">URL</i></td>
            <td><?=$url;?></td>
        </tr>
        <tr>
            <td><i class="col-xs-1">Content</i></td>
            <td><?=$content;?></td>
        </tr>
        <tr>
            <td><i class="col-xs-1">Frecuency</i></td>
            <td><?=$frecuency;?></td>
        </tr>         
        <tr>
            <td><i class="col-xs-1">Price</i></td>
            <td><?=$price;?></td>
        </tr>         
        <tr>
            <td><i class="col-xs-1">Images</i></td>
            <td><?php print_r($images);?></td>
        </tr>         
        <tr>
            <td><i class="col-xs-1">Images</i></td>
            <td><?//=htmlspecialchars(@$images, ENT_QUOTES);?></td>
        </tr>        
    </tbody>
</table>
<?php endif;?>
    </section>
</body>
</html>
