<?php

use CodeIgniter\Router\RouteCollection;
use App\Controllers\Mods;
use App\Controllers\UUIDContentUIDGen;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('mods', 'Home::index');

$routes->get('mods/(:segment)', [Mods::class, 'index']);
$routes->get('mods/(:segment)/(:segment)', [Mods::class, 'index']);
$routes->get('mods/(:segment)/(:segment)/(:any)', [Mods::class, 'index']);

$routes->get('display/(:segment)/(:segment)/(:any)', [Mods::class, 'display']);
$routes->get('search/(:segment)/(:segment)/(:any)', [Mods::class, 'search']);
$routes->get('replace/(:segment)/(:segment)/(:segment)/(:any)', [Mods::class, 'replace']);

$routes->get('uuidcontentuidgen', [UUIDContentUIDGen::class, 'index']);
$routes->get('uuidcontentuidgen/(:any)', [UUIDContentUIDGen::class, 'index']);

$routes->get ('settings',      'Settings::index');
$routes->post('settings/save', 'Settings::save');

$routes->post('save', [Mods::class, 'save']);

$routes->get('search/mongo', 'SearchMongo::index');
$routes->get('search/mongo-filters', 'SearchMongo::filters');

