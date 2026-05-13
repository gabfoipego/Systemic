<?php
require_once "./libs/router.php";
$router = new Router(__DIR__);

$homepage = function() {
    http_response_code(200);
    header("Content-Type: text/html; charset=UTF-8");
    include(__DIR__ . '/pages/homepage/index.html');
};

$router->get("/", $homepage);

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];


try {
    $router->dispatch($uri, $method);
} catch (Exception $e) {
    http_response_code(500);
    echo $e->getMessage();
}
?>