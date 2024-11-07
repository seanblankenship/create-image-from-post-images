<?php

/**
 * Description:     Create a single image, displayed in rows and colums,
 *                  from the featured images of a defined post type.
 * Version:         1.0
 * Implementation:  <img src="http://example.com/generate.php?tax=TAXONOMY&term=TERM&trim=true" alt="Composite of Featured Images" />
 * Parameters:      type - (required) post type slug to be set in the configuration (not passed in the URL)
 *                  tax - (optional) taxonomy slug
 *                  term - (optional) term slug
 *                  trim - (optional) boolean to trim transparent space
 **/

header( 'Content-Type: image/webp' );

$path = preg_replace( '/wp-content(?!.*wp-content).*/', '', __DIR__ );
require_once $path . '/wp-load.php';

// configure your settings here
$config = array(
	'canvas_width'  => 1600, // final image width if not trimmed
	'canvas_height' => 1200, // final image height if not trimmed
	'columns'       => 7, // number of images per row
	'padding'       => 10, // space between images
	'max_images'    => 50, // keep this set to a reasonable number to prevent memory issues
	'default_type'  => 'POST_TYPE', // type must always bet set
	'default_tax'   => '', // optional
	'default_term'  => '', // optional
	'aspect_ratio'  => array( 3, 4 ), // width, height
);

// error handling
function output_error_image( $message, $width = 400, $height = 100 ) {
	$img = imagecreatetruecolor( $width, $height );
	imagesavealpha( $img, true );
	$transparent = imagecolorallocatealpha( $img, 0, 0, 0, 127 );
	imagefill( $img, 0, 0, $transparent );
	$text_color = imagecolorallocate( $img, 255, 0, 0 );
	imagestring( $img, 5, 10, 40, $message, $text_color );
	imagepng( $img );
	imagedestroy( $img );
	exit;
}

// helper function for fetching images via cURL
function fetch_image_data( $url ) {
	$ch = curl_init();
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_CONNECTTIMEOUT => 5,
		)
	);

	$data = curl_exec( $ch );

	if ( curl_errno( $ch ) ) {
		error_log( 'cURL Error: ' . curl_error( $ch ) );
		curl_close( $ch );
		return false;
	}

	curl_close( $ch );
	return $data;
}

// crop and resize image while maintaining aspect ratio
function crop_and_resize( $source_image, $target_width, $target_height, $aspect_ratio ) {
	$source_width  = imagesx( $source_image );
	$source_height = imagesy( $source_image );
	$source_ratio  = $source_width / $source_height;
	$target_ratio  = $aspect_ratio[0] / $aspect_ratio[1];

	if ( $source_ratio > $target_ratio ) {
		$crop_width  = round( $source_height * $target_ratio );
		$crop_height = $source_height;
		$crop_x      = round( ( $source_width - $crop_width ) / 2 );
		$crop_y      = 0;
	} else {
		$crop_width  = $source_width;
		$crop_height = round( $source_width / $target_ratio );
		$crop_x      = 0;
		$crop_y      = round( ( $source_height - $crop_height ) / 2 );
	}

	$target_image = imagecreatetruecolor( $target_width, $target_height );
	imagesavealpha( $target_image, true );
	$transparent = imagecolorallocatealpha( $target_image, 0, 0, 0, 127 );
	imagefill( $target_image, 0, 0, $transparent );

	imagecopyresampled(
		$target_image,
		$source_image,
		0,
		0,
		$crop_x,
		$crop_y,
		$target_width,
		$target_height,
		$crop_width,
		$crop_height
	);

	return $target_image;
}

// get and validate query parameters
$params = array(
	'tax'  => isset( $_GET['tax'] ) ? preg_replace( '/[^a-z0-9\-_]/', '', strtolower( $_GET['tax'] ) ) : '',
	'term' => isset( $_GET['term'] ) ? preg_replace( '/[^a-z0-9\-_]/', '', strtolower( $_GET['term'] ) ) : '',
	'trim' => isset( $_GET['trim'] ) ? filter_var( $_GET['trim'], FILTER_VALIDATE_BOOLEAN ) : false,
);

// build query arguments
$args = array(
	'post_type'      => $config['default_type'],
	'posts_per_page' => $config['max_images'],
);

// add taxonomy query if parameters are set
if ( ! empty( $params['tax'] ) && ! empty( $params['term'] ) ) {
	if ( ! taxonomy_exists( $params['tax'] ) ) {
		output_error_image( 'Invalid taxonomy' );
	}
	$args['tax_query'] = array(
		array(
			'taxonomy' => $params['tax'],
			'field'    => 'slug',
			'terms'    => $params['term'],
		),
	);
}

$query        = new WP_Query( $args );
$total_images = $query->post_count;

// calculate image dimensions based on number of columns set
$image_width  = ( $config['canvas_width'] - ( ( $config['columns'] - 1 ) * $config['padding'] ) ) / $config['columns'];
$image_height = ( $image_width * $config['aspect_ratio'][1] ) / $config['aspect_ratio'][0];

// calculate rows needed
$rows = ceil( $total_images / $config['columns'] );

// create canvas
$canvas = imagecreatetruecolor( $config['canvas_width'], $config['canvas_height'] );
imagesavealpha( $canvas, true );
$transparent = imagecolorallocatealpha( $canvas, 0, 0, 0, 127 );
imagefill( $canvas, 0, 0, $transparent );

// process images
if ( $query->have_posts() ) {
	$row = 0;
	$col = 0;

	// calculate vertical offset for centering
	$total_height = ( $image_height * $rows ) + ( $config['padding'] * ( $rows - 1 ) );
	$start_y      = ( $config['canvas_height'] - $total_height ) / 2;

	while ( $query->have_posts() ) {
		$query->the_post();
		$image_url = get_the_post_thumbnail_url( get_the_ID(), 'full' );

		if ( $image_url ) {
			$image_data = fetch_image_data( $image_url );

			if ( $image_data ) {
				$image = imagecreatefromstring( $image_data );

				if ( $image ) {
					$x = ( $config['padding'] * $col ) + ( $image_width * $col );
					$y = $start_y + ( $image_height + $config['padding'] ) * $row;

					$processed_image = crop_and_resize( $image, $image_width, $image_height, $config['aspect_ratio'] );
					imagecopy( $canvas, $processed_image, $x, $y, 0, 0, $image_width, $image_height );

					imagedestroy( $processed_image );
					imagedestroy( $image );

					++$col;
					if ( $col >= $config['columns'] ) {
						$col = 0;
						++$row;
					}
				}
			}
		}
	}
	wp_reset_postdata();
} else {
	output_error_image( 'No posts found' );
}

// trim to the edges of the rows / columns if requested
if ( $params['trim'] ) {
	$bounds = array(
		'top'    => 0,
		'left'   => 0,
		'right'  => $config['canvas_width'],
		'bottom' => $config['canvas_height'],
	);

	// find bounds
	for ( $y = 0; $y < $config['canvas_height']; $y++ ) {
		for ( $x = 0; $x < $config['canvas_width']; $x++ ) {
			$alpha = ( imagecolorat( $canvas, $x, $y ) >> 24 ) & 0x7F;
			if ( $alpha != 127 ) {
				$bounds['top'] = $y;
				break 2;
			}
		}
	}

	for ( $y = $config['canvas_height'] - 1; $y >= 0; $y-- ) {
		for ( $x = 0; $x < $config['canvas_width']; $x++ ) {
			$alpha = ( imagecolorat( $canvas, $x, $y ) >> 24 ) & 0x7F;
			if ( $alpha != 127 ) {
				$bounds['bottom'] = $y + 1;
				break 2;
			}
		}
	}

	for ( $x = 0; $x < $config['canvas_width']; $x++ ) {
		for ( $y = 0; $y < $config['canvas_height']; $y++ ) {
			$alpha = ( imagecolorat( $canvas, $x, $y ) >> 24 ) & 0x7F;
			if ( $alpha != 127 ) {
				$bounds['left'] = $x;
				break 2;
			}
		}
	}

	for ( $x = $config['canvas_width'] - 1; $x >= 0; $x-- ) {
		for ( $y = 0; $y < $config['canvas_height']; $y++ ) {
			$alpha = ( imagecolorat( $canvas, $x, $y ) >> 24 ) & 0x7F;
			if ( $alpha != 127 ) {
				$bounds['right'] = $x + 1;
				break 2;
			}
		}
	}

	// create trimmed image
	$new_width  = $bounds['right'] - $bounds['left'];
	$new_height = $bounds['bottom'] - $bounds['top'];
	$new_canvas = imagecreatetruecolor( $new_width, $new_height );
	imagesavealpha( $new_canvas, true );
	$transparent = imagecolorallocatealpha( $new_canvas, 0, 0, 0, 127 );
	imagefill( $new_canvas, 0, 0, $transparent );
	imagecopy( $new_canvas, $canvas, 0, 0, $bounds['left'], $bounds['top'], $new_width, $new_height );
	imagedestroy( $canvas );
	$canvas = $new_canvas;
}

// output final image and destroy to free memory
imagepng( $canvas, null, 9 );
imagedestroy( $canvas );
