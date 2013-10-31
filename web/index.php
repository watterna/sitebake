<?php

/**
 * Composer
 */
require __DIR__.'/../vendor/autoload.php';

/**
 * Silex
 */
use Silex\Application;

/**
 * Application
 */
$app = new Application;

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
 * Symfony Yaml
 */
$app['symfony.parser'] = $app->share(function () {
	return new Symfony\Component\Yaml\Parser;
});

/**
 * Twig
 */
$app->register(new Silex\Provider\TwigServiceProvider, [
	'twig.path' => __DIR__.'/../views',
]);

/**
 * Settings
 */
$app['settings'] = [
	'path'        => 'index',
	'content_ext' => '.md',
	'tpl_ext'     => '.html.twig',
	'tpl_std'     => 'page',
	'cache'       => 60
];

/**
 * Main route
 */
$app->get('{path}', function($path) use($app)
{
	// Generate key for cache storage
	$key = md5($path);

	// Check cache for Response object
	$response = $app['memcache.store']->get($key);

	// No cached Response found
	if(empty($response))
	{
		// Make path to file on disk
		$file = __DIR__.'/../content/'.$path.$app['settings']['content_ext'];

		// Return error if file is not found
		if( ! is_file($file)) {
			return $app['twig']->render('error'.$app['settings']['tpl_ext'], [
				'code' => 404,
				'msg'  => 'Page "'.$path.'" not found'
			]);
		}

		// Read contents of file
		$data = file_get_contents($file);

		// Split file into metadata and content
		list($metadata, $content) = preg_split('/\s+-{3,}\s+/', $data, 2, PREG_SPLIT_NO_EMPTY);

		// Parse metadata to array
		$metadata = $app['symfony.parser']->parse($metadata);

		// Parse content to HTML
		$content  = $app['dflydev.markdown']->transformMarkdown($content);

		// Check if template is set in metadata, fallback to default if not
		$template = isset($metadata->template) ? $metadata.$app['settings']['tpl_ext'] : $app['settings']['tpl_std'].$app['settings']['tpl_ext'];

		// Create Response object with template and data
		$response = $app['twig']->render($template, compact('metadata', 'content'));

		// Store Response object in cache by key
		$app['memcache.store']->set($key, $response, MEMCACHE_COMPRESSED, ($app['debug'] ? 1 : $app['settings']['cache']));
	}

	// Return Response object
	return $response;


})->value('path', $app['settings']['path'])->assert('path', '.+');

/**
 * Run Application
 */
$app->run();