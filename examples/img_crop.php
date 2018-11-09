<?php
	
require_once (getenv("HOME").'/libs/vendor/autoload.php');
use Intervention\Image\ImageManagerStatic as Image;

function crop_images($filename){

	$img = Image::make($filename);
	$width = $img->width();
	$height = $img->height();
	$img->crop($width-130, $height, 130, 0)->save($filename);	

}

?>