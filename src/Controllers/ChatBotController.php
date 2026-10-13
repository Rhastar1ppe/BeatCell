<?php
require_once __DIR__ . '/../Services/ChatBotService.php';

class ChatBotController
{
    private ChatBotService $servicio;

    public function __construct(PDO $db, int $idUsuario)
    {
        $this->servicio = new ChatBotService($db, $idUsuario);
    }

    public function saludoInicial(): array
    {
        return $this->servicio->saludoInicial();
    }

    public function responder(string $mensaje): array
    {
        return $this->servicio->responder($mensaje);
    }

    public function contarAvisos(): int
    {
        return $this->servicio->contarAvisos();
    }
}
