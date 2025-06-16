<?php

use CodeIgniter\Router\RouteCollection;
use App\Controllers\Mods;
use App\Controllers\UUIDContentUIDGen;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('mods', 'Home::index');
$routes->get('mods/(:segment)/', [Mods::class, 'index/$1/']);
$routes->get('mods/(:segment)/(:segment)/', [Mods::class, 'index/$1/$2/']);
$routes->get('mods/(:segment)/(:segment)/(:any)', [Mods::class, 'index/$1/$2/$3']);
$routes->get('display/(:segment)/(:segment)/(:any)', [Mods::class, 'display/$1/$2/$3/']);
$routes->get('search/(:segment)/(:segment)/(:any)', [Mods::class, 'search/$1/$2/$3']);
$routes->get('replace/(:segment)/(:segment)/(:segment)/(:any)', [Mods::class, 'replace/$1/$2/$3/$4']);
$routes->get('uuidcontentuidgen', [UUIDContentUIDGen::class, 'index']);
$routes->get('uuidcontentuidgen/(:any)', [UUIDContentUIDGen::class, 'index/$1']);

$routes->post('save', [Mods::class, 'save']);
