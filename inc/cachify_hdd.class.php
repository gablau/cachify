<?php


/* Quit */
defined('ABSPATH') OR exit;


/**
* Cachify_HDD
*/

final class Cachify_HDD {


	/**
	* Availability check
	*
	* @since   2.0.7
	* @change  2.0.7
	*
	* @return  boolean  true/false  TRUE when installed
	*/

	public static function is_available()
	{
		return get_option('permalink_structure');
	}


	/**
	* Caching method as string
	*
	* @since   2.1.2
	* @change  2.1.2
	*
	* @return  string  Caching method
	*/

	public static function stringify_method() {
		return 'HDD';
	}


	/**
	* Store item in cache
	*
	* @since   2.0
	* @change  2.0
	*
	* @param   string   $hash      Hash  of the entry [optional]
	* @param   string   $data      Content of the entry
	* @param   integer  $lifetime  Lifetime of the entry [optional]
	*/

	public static function store_item($hash, $data, $lifetime)
	{
		/* Leer? */
		if ( empty($data) ) {
			wp_die('HDD add item: Empty input.');
		}

		/* Speichern */
		self::_create_files(
			$data . self::_cache_signatur()
		);
	}


	/**
	* Read item from cache
	*
	* @since   2.0
	* @change  2.0
	*
	* @return  boolean  $diff  TRUE if cache is present
	*/

	public static function get_item()
	{
		return is_readable(
			self::_file_html()
		);
	}


	/**
	* Delete item from cache
	*
	* @since   2.0
	* @change  2.0
	*
	* @param   string   $hash  Hash of the entry [optional]
	* @param   string   $url   URL of the entry
	*/

	public static function delete_item($hash = '', $url)
	{
		/* Empty? */
		if ( empty($url) ) {
			wp_die('HDD delete item: Empty input.');
		}

		/* Delete */
		self::_clear_dir(
			self::_file_path($url)
		);
	}


	/**
	* Clear the cache
	*
	* @since   2.0
	* @change  2.0
	*/

	public static function clear_cache()
	{
		self::_clear_dir(
			CACHIFY_CACHE_DIR
		);
	}


	/**
	* Print the cache
	*
	* @since   2.0
	* @change  2.0
	*/

	public static function print_cache()
	{
		return;
	}


	/**
	* Get the cache size
	*
	* @since   2.0
	* @change  2.0
	*
	* @return  integer  Directory size
	*/

	public static function get_stats()
	{
		return self::_dir_size( CACHIFY_CACHE_DIR );
	}


	/**
	* Generate signature
	*
	* @since   2.0
	* @change  2.0.5
	*
	* @return  string  Signature string
	*/

	private static function _cache_signatur()
	{
		return sprintf(
			"\n\n<!-- %s\n%s @ %s -->",
			'Cachify | http://cachify.de',
			'HDD Cache',
			date_i18n(
				'd.m.Y H:i:s',
				current_time('timestamp')
			)
		);
	}


	/**
	* Initialize caching process
	*
	* @since   2.0
	* @change  2.0
	*
	* @param   string  $data  Cache content
	*/

	private static function _create_files($data)
	{
		/* Create directory */
		if ( ! wp_mkdir_p( self::_file_path() ) ) {
			wp_die('Unable to create directory.');
		}

		/* Write to file */
		self::_create_file( self::_file_html(), $data );
		self::_create_file( self::_file_gzip(), gzencode($data, 9) );
	}


	/**
	* Create cache file
	*
	* @since   2.0
	* @change  2.0
	*
	* @param   string  $file  Pfad der Cache-Datei
	* @param   string  $data  Cache-Inhalt
	*/

	private static function _create_file($file, $data)
	{
		/* Writable? */
		if ( ! $handle = @fopen($file, 'wb') ) {
			wp_die('Could not write file.');
		}

		/* Write */
		@fwrite($handle, $data);
		fclose($handle);
		clearstatcache();

		/* Permissions */
		$stat = @stat( dirname($file) );
		$perms = $stat['mode'] & 0007777;
		$perms = $perms & 0000666;
		@chmod($file, $perms);
		clearstatcache();
	}


	/**
	* Clear directory recursively
	*
	* @since   2.0
	* @change  2.0.5
	*
	* @param   string  $dir  Directory path
	*/

	private static function _clear_dir($dir) {
		/* Remote training slash */
		$dir = untrailingslashit($dir);

		/* Is directory? */
		if ( ! is_dir($dir) ) {
			return;
		}

		/* Read */
		$objects = array_diff(
			scandir($dir),
			array('..', '.')
		);

		/* Empty? */
		if ( empty($objects) ) {
			return;
		}

		/* Loop over items */
		foreach ( $objects as $object ) {
			/* Expand path */
			$object = $dir. DIRECTORY_SEPARATOR .$object;

			/* Directory or file */
			if ( is_dir($object) ) {
				self::_clear_dir($object);
			} else {
				unlink($object);
			}
		}

		/* Remove directory */
		@rmdir($dir);

		/* CleanUp */
		clearstatcache();
	}


	/**
	* Get directory size
	*
	* @since   2.0
	* @change  2.0
	*
	* @param   string  $dir   Directory path
	* @return  mixed          Directory size
	*/

	public static function _dir_size($dir = '.')
	{
		/* Is directory? */
		if ( ! is_dir($dir) ) {
			return;
		}

		/* Read */
		$objects = array_diff(
			scandir($dir),
			array('..', '.')
		);

		/* Empty? */
		if ( empty($objects) ) {
			return;
		}

		/* Init */
		$size = 0;

		/* Loop over items */
		foreach ( $objects as $object ) {
			/* Expand path */
			$object = $dir. DIRECTORY_SEPARATOR .$object;

			/* Directory or file */
			if ( is_dir($object) ) {
				$size += self::_dir_size($object);
			} else {
				$size += filesize($object);
			}
		}

		return $size;
	}


	/**
	* Path to cache file
	*
	* @since   2.0
	* @change  2.0
	*
	* @param   string  $path  Request-URI or Permalink [optional]
	* @return  string         Path to cache file
	*/

	private static function _file_path($path = NULL)
	{
		$prefix = is_ssl() ? 'https-' : '';

		$path = sprintf(
			'%s%s%s%s%s',
			CACHIFY_CACHE_DIR,
			DIRECTORY_SEPARATOR,
			$prefix,
			parse_url(
				'http://' .strtolower($_SERVER['HTTP_HOST']),
				PHP_URL_HOST
			),
			parse_url(
				( $path ? $path : $_SERVER['REQUEST_URI'] ),
				PHP_URL_PATH
			)
		);

		if ( validate_file($path) > 0 ) {
			wp_die('Invalide file path.');
		}

		return trailingslashit($path);
	}


	/**
	* Path to HTML file
	*
	* @since   2.0
	* @change  2.0
	*
	* @return  string  Path to HTML file
	*/

	private static function _file_html()
	{
		return self::_file_path(). 'index.html';
	}


	/**
	* Path to GZIP file
	*
	* @since   2.0
	* @change  2.0
	*
	* @return  string  Path to GZIP file
	*/

	private static function _file_gzip()
	{
		return self::_file_path(). 'index.html.gz';
	}
}
