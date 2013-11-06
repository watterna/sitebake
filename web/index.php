<?php

/**
 * Composer
 */
require __DIR__.'/../vendor/autoload.php';

/**
 * Application
 */
$app = new Silex\Application;

/**
 * Debug
 */
$app['debug'] = false;

/**
 * Settings
 */
$app['settings'] = [
	
	// Default page
	'path'        => 'index',
	
	// Extention of content files
	'content_ext' => '.md',
	
	// Template file extention
	'tpl_ext'     => '.html.twig',
	
	// Default template filename
	'tpl_std'     => 'page',
	
	// Default settings to cache content
	'cache'       => 60,

	// Cache key prefix
	'prefix'      => 'sb:',
	
	// View directory location
	'views'       => __DIR__.'/../views',

	//Content directory location
	'content'     => __DIR__.'/../content'
];

/**
 * Memcache
 */
$app['memcache.store'] = $app->share(function() {
	$memcache = new Memcache;
	$memcache->connect('localhost', 11211) or die ("Could not connect");

	return $memcache;
});

/**
 * dflydev MarkdownExtraParser
 */
$app['dflydev.markdown'] = $app->share(function () {
	return new dflydev\markdown\MarkdownExtraParser;
});

/**
 * Symfony Yaml Parser
 */
$app['symfony.yaml.parser'] = $app->share(function () {
	return new Symfony\Component\Yaml\Parser;
});

/**
 * Twig Loader File
 */
$app->register(new Silex\Provider\TwigServiceProvider, [
	'twig.path' => [
		$app['settings']['views']
	],
]);

/**
 * Twig Loader String
 */
$app['twig.string'] = $app->share(function() {
	return new Twig_Environment(new Twig_Loader_String);
});

/**
 * Main route
 */
$app->get('{path}', function($path) use($app)
{
	// Generate key for cache storage
	$key = md5($path);

	// Check page is cached
	$html = $app['memcache.store']->get($key);

	// No cached html found
	if(empty($html))
	{
		// Make path to file on disk
		$file = $app['settings']['content'].'/'.$path.$app['settings']['content_ext'];

		// Return error if file is not found
		if( ! is_file($file)) {
			return $app['twig']->render('error'.$app['settings']['tpl_ext'], [
				'code' => 404,
				'msg'  => 'Page "'.$path.'" not found'
			]);
		}

		// Split file into metadata and content
		list($metadata, $content) = preg_split('/\s+-{3,}\s+/', file_get_contents($file), 2, PREG_SPLIT_NO_EMPTY);

		// Parse metadata to array
		$metadata = $app['symfony.yaml.parser']->parse($metadata);

		// Create html object with template and data
		$html = $app['twig']->render(

			// Check is template is set, load default if missing
			(isset($metadata['template']) ? $metadata['template'].$app['settings']['tpl_ext'] : $app['settings']['tpl_std'].$app['settings']['tpl_ext']), [

				// Pass metadata to template
				'metadata' => $metadata,

				// Render content with Twig Loader String and parse to HTML with MarkdownExtraParser
				'content' => $app['dflydev.markdown']->transformMarkdown($app['twig.string']->render($content, compact('metadata')))
			]
		);

		// Store html in cache by key
		$app['memcache.store']->set($app['settings']['prefix'].$key, $html, MEMCACHE_COMPRESSED, ($app['debug'] ? 1 : $app['settings']['cache']));
	}

	// Return html
	return $html;


})->value('path', $app['settings']['path'])->assert('path', '.+');

/**
 * Run Application
 */
$app->run();