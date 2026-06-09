<?php

/**
 * Middleware simple de autenticación por Bearer Token.
 * Termina la ejecución con 401 si el token no es válido.
 */
function verifyBearerToken(): void
{
    $expectedToken = getenv('MAIL_API_TOKEN');

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Token no proporcionado.']);
        exit;
    }

    $token = substr($authHeader, 7);

    if ($token !== $expectedToken) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Token inválido.']);
        exit;
    }
}
