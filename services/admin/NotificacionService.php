<?php

require_once __DIR__ . '/../../repositories/NotificacionRepository.php';

class NotificacionService {
    private NotificacionRepository $repo;

    public function __construct() {
        $this->repo = new NotificacionRepository();
    }

    public function obtenerParaCampana(): array {
        // Este es el "disparador" de los eventos por tiempo: se revisa
        // cada vez que alguien abre la campanita (ver nota en el Repository).
        $this->repo->revisarEventosDeTiempo();

        return [
            'noLeidas' => $this->repo->contarNoLeidas(),
            'recientes' => $this->repo->listarRecientes(20),
        ];
    }

    public function marcarLeida(int $id): void {
        $this->repo->marcarLeida($id);
    }

    public function marcarTodasLeidas(): void {
        $this->repo->marcarTodasLeidas();
    }
}
