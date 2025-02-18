<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

use session\Session;

class RolesOfMiddleware
{
    // Define as permissões para cada tipo de usuário
    private const PERMISSION_RULES = [
        "user" => [
            '/^\/user\/edit/' => ['PATCH'],
            '/^\/user\/delete/' => ['DELETE'],
            '/^\/lobby/' => ['POST', 'PATCH','DELETE', 'GET']
        ],
        "admin" => [
            '/^\/user\/edit/' => ['PATCH'],
            '/^\/user\/delete/' => ['DELETE'],
            '/^\/adm\/decks/' => ['POST', 'PATCH', 'DELETE', 'GET'],
            '/^\/adm\/letter/' => ['POST', 'PATCH', 'DELETE', 'GET'],
            '/^\/lobby/' => ['POST', 'PATCH','DELETE', 'GET']
        ]
    ];

    public function __invoke(Request $request, RequestHandler $handler)
    {
        $userType = Session::getUserType(); // Obtem o tipo de usuário logado

        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();

        if (!$this->isRequestAllowed($userType, $uri, $method)) {
            return $this->denyAccess($request);
        }

        // Continua com a requisição caso permitido
        return $handler->handle($request);
    }

    // Verifica se o tipo de usuário tem permissão para a URI
    private function isRequestAllowed($userType, $uri, $method)
    {
        if (!isset(self::PERMISSION_RULES[$userType])) {
            return false;
        }

        foreach (self::PERMISSION_RULES[$userType] as $pattern => $methods) {
            if (preg_match($pattern, $uri) && in_array($method, $methods)) {
                return true;
            }
        }

        return false;
    }

    // Responde com erro de acesso negado
    private function denyAccess(Request $request)
    {
        $response = new \Slim\Psr7\Response();
        $response = $response->withStatus(403)
            ->withHeader('Content-Type', 'application/json');

        $response->getBody()->write(json_encode([
            'error' => 'Acesso negado. Você não tem acesso a essa rota.'
        ]));
        return $response;
    }
}
