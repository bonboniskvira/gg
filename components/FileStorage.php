<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
use Kreait\Firebase\Factory;

final class FileStorage
{
    private const SEPARATED_MODULES = [
        'doc', 'videos', 'marketing', 'pdf', 'other', 'links',
        'podcast', 'podcasts', 'edo', 'seznam', 'test',
        'agreements', 'courses',
    ];

    private readonly \Kreait\Firebase\Contract\Database $database;

    public function __construct(
        private readonly string $documentRoot,
        private readonly string $role
    ) {
        // Inicializace Firebase hned při startu
        $factory = (new Factory)
            ->withServiceAccount(__DIR__ . '/../secret/firebase-auth.json')
            ->withDatabaseUri('https://edosystest-default-rtdb.europe-west1.firebasedatabase.app/');

        $this->database = $factory->createDatabase();
    }

    public static function fromSession(): self
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $role = (string)($_SESSION['role'] ?? '');
        return new self($root, $role);
    }

    /**
     * Získá cestu ke složce a zároveň to LOGNE do Firebase
     */
    public function getRoot(string $type): string
    {
        $folder = $type;

        if ($this->role === 'poradce' && in_array($type, self::SEPARATED_MODULES, true)) {
            $folder .= '-poradce';
        }

        // --- FIREBASE LOG ---
        $this->logAction("access_folder", [
            'type' => $type,
            'real_folder' => $folder,
            'user' => $_SESSION['username'] ?? 'unknown'
        ]);

        return $this->documentRoot . '/' . $folder . '/';
    }

    private function logAction(string $action, array $data): void
    {
        try {
            $this->database->getReference('logs/' . date('Y-m-d'))
                ->push([
                    'action' => $action,
                    'data' => $data,
                    'time' => date('H:i:s'),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli'
                ]);
        } catch (\Exception $e) {
            // Pokud Firebase selže, web musí jet dál
        }
    }
}