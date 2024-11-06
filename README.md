# Create Image from Post Featured Images

Creates an image of rows and columns of all of the featured images of posts within a certain set of parameters in WordPress.

## Options

There are the following options available at the top of the file to set some defaults. Always make sure to set the default post type that should be queried.

```php
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
```

## Usage

Pass the file to an img src and attach taxonomy name, term name, and/or trim to the query string if you need to pass those options.

```html
<img
    src="https://url/to/file/generate.php?tax=TAXONOMY&term=TERM&trim=true"
    alt="Composite of Featured Images" />
```
