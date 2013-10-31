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

$app['settings'] = [
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
	$key = md5($path);

	$response = $app['memcache.store']->get($key);

	if(empty($response))
	{
		$file = __DIR__.'/../content/'.$path.$app['settings']['content_ext'];

		if( ! is_file($file)) {
			return $app['twig']->render('error'.$app['settings']['tpl_ext'], [
				'code' => 404,
				'msg'  => 'Page "'.$path.'" not found'
			]);
		}

		$data = file_get_contents($file);

		list($metadata, $content) = preg_split('/\s+-{3,}\s+/', $data, 2, PREG_SPLIT_NO_EMPTY);

		$metadata = $app['symfony.parser']->parse($metadata);

		$content  = $app['dflydev.markdown']->transformMarkdown($content);

		$template = isset($metadata->template) ? $metadata.$app['settings']['tpl_ext'] : $app['settings']['tpl_std'].$app['settings']['tpl_ext'];

		$response = $app['twig']->render($template, compact('metadata', 'content'));

		$app['memcache.store']->set($key, $response, MEMCACHE_COMPRESSED, ($app['debug'] ? 1 : $app['settings']['cache']));
	}

	return $response;


})->value('path', 'index')->assert('path', '.+');

/**
 * Run Application
 */
$app->run();