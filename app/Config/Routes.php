<?php

use CodeIgniter\Router\RouteCollection;
use App\Controllers\Mods;
use App\Controllers\Home;
use App\Controllers\Settings;
use App\Controllers\UUIDContentUIDGen;

/**
 * @var RouteCollection $routes
 */
$routes->get('/',              [Home::class, 'index']);

// HTML + JSON (content-negotiated)
$routes->get('mods',                                    [Mods::class, 'list']);       // roots
$routes->get('mods/(:segment)',                         [Mods::class, 'listRoot']);   // mods in root
$routes->get('mods/(:segment)/(:segment)',              [Mods::class, 'mod']);        // browse mod root
$routes->get('mods/(:segment)/(:segment)/(.+)',         [Mods::class, 'view']);       // browse inside mod (dirs/files)

// JSON-only save endpoint (editor POSTS here)
$routes->post('mods/(:segment)/(:segment)/file/(.+)',   [Mods::class, 'save']);       // write file

$routes->get('display/(:segment)',          'Display::view/$1');        // root only -> path empty
$routes->get('display/(:segment)/(.+)',     'Display::view/$1/$2');     // view file
$routes->post('display/(:segment)/(.+)',    'Display::save/$1/$2');     // save file
$routes->get('browse/(:segment)',           'Display::browse/$1');      // list root
$routes->get('browse/(:segment)/(.+)',      'Display::browse/$1/$2');   // list subdir

$routes->get('search/(:segment)/(:segment)/(:any)', [Mods::class, 'search']);
$routes->get('replace/(:segment)/(:segment)/(:segment)/(:any)', [Mods::class, 'replace']);

$routes->get('uuid',       'UUIDContentUIDGen::index/UUID');
$routes->get('contentuid', 'UUIDContentUIDGen::index/ContentUID');

$routes->get ('mods/localizations/manifest', 'ModsLocalization::manifest');
$routes->post('mods/localizations/parse',    'ModsLocalization::parse');

$routes->get('settings',       [Settings::class, 'index']);
$routes->post('settings',      [Settings::class, 'save']);

$routes->post('save', [Mods::class, 'save']);

$routes->get('search/mongo', 'SearchMongo::index');
$routes->get('search/mongo-filters', 'SearchMongo::filters');

