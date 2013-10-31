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
 * dflydev MarkdownExtraParser
 */
$app['dflydev.markdown'] = $app->share(function ()
{
	return new dflydev\markdown\MarkdownExtraParser;
});

/**
 * Symfony Yaml
 */
$app['symfony.parser'] = $app->share(function ()
{
	return new Symfony\Component\Yaml\Parser;
});

/**
 * Twig
 */
$app->register(new Silex\Provider\TwigServiceProvider, array(
	'twig.path' => __DIR__.'/../views',
));

/**
 * Main route
 */
$app->get('{path}', function($path) use($app)
{
	$file = __DIR__.'/../content/'.$path.'.md';

	if( ! is_file($file)) {
		return $app['twig']->render('error.html.twig', array(
			'code' => 404,
			'msg'  => 'Page "'.$path.'" not found'
		));
	}

	$data = file_get_contents($file);

	list($metadata, $content) = preg_split('/\s+-{3,}\s+/', $data, 2, PREG_SPLIT_NO_EMPTY);

	$metadata = $app['symfony.parser']->parse($metadata);

	$template = isset($metadata->template) ? $metadata.'.html.twig' : 'page.html.twig';

	return $app['twig']->render($template, array(
		'metadata' => $metadata,
		'content'  => $app['dflydev.markdown']->transformMarkdown($content)
	));

})->value('path', 'index')->assert('path', '.+');

/**
 * Run Application
 */
$app->run();