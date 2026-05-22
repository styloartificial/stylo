<?php

namespace App\Helpers;

use Kreait\Firebase\Database;

use function Symfony\Component\Clock\now;

class FirebaseLogHelper {
    protected static function start(
        Database $database,
        string $ticketId,
        string $title,
        string $description
    ): void {
        $logsRef = $database
            ->getReference("tickets/{$ticketId}/logs");
        
        $logs = $logsRef->getValue() ?? [];

        $lastId = 0;
        if (!empty($logs)) {
            $lastLog = end($logs);
            $lastId = $lastLog['id'] ?? 0;
        }

        $newLog = [
            'id' => $lastId + 1,
            'title' => $title,
            'description' => $description,
            'created_at' => now()->format('Y-m-d h:i:s')
        ];
        $logsRef->push($newLog);
    }

    public static function logTicketQueued(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Mendaftarkan data ke antrean tiket',
            'Menyimpan data permintaan awal ke dalam antrean tiket untuk diproses.'
        );
    }

    public static function logPromptBuild(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Membuat prompt',
            'Membuat prompt untuk AI berdasarkan data yang diinput.'
        );
    }

    public static function logPromptSent(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Mengirim prompt ke AI',
            'Mengirim prompt ke AI service untuk diproses.'
        );
    }

    public static function logPromptCompleted(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Mendapatkan hasil dari prompt',
            'Menerima dan memproses hasil respons yang dihasilkan oleh AI.'
        );
    }

    public static function logScrapPrepared(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Menyiapkan data scraping',
            'Menyiapkan dan mengumpulkan data untuk proses scraping.'
        );
    }

    public static function logScrapQueued(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Mendaftarkan data ke antrean scraping',
            'Menambahkan tugas scraping ke antrean untuk dijalankan.'
        );
    }

    public static function logScrapProcess(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Mendapatkan hasil scraping',
            'Mendapatkan dan memproses hasil scraping dari AI.'
        );
    }

    public static function logGenerationCompleted(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Pembuatan berhasil selesai',
            'Menyelesaikan proses dan mengfirmasi pembuatan berhasil.'
        );
    }
}