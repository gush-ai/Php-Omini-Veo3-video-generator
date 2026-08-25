<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use GVid\Router;
use GVid\Controllers\VideoController;

$router = new Router();

$controller = new VideoController();

$router->post('/video/generate', [$controller, 'generate']);
$router->get('/video/status', [$controller, 'status']);
$router->get('/video/result', [$controller, 'result']);
$router->post('/video/cancel', [$controller, 'cancel']);
$router->get('/video/file', [$controller, 'file']);
$router->get('/health', [$controller, 'health']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
