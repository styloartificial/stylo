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
            'Registering data in ticket queue',
            'Storing the initial request data into the ticket queue for processing.'
        );
    }

    public static function logPromptBuild(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Creating a prompt',
            'Generating an AI prompt based on the submitted data.'
        );
    }

    public static function logPromptSent(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Submitting the prompt',
            'Sending the generated prompt to the AI service for processing.'
        );
    }

    public static function logPromptCompleted(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Getting result from prompt',
            'Receiving and parsing the AI-generated response.'
        );
    }

    public static function logScrapPrepared(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Getting data for scraping',
            'Preparing and collecting the required data for the scraping process.'
        );
    }

    public static function logScrapQueued(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Registering data in scrap queue',
            'Adding scraping tasks to the scraping queue for execution.'
        );
    }

    public static function logScrapProcess(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Getting the scrap result',
            'Retrieving and processing the scraping results.'
        );
    }

    public static function logGenerationCompleted(Database $db, string $ticketId) {
        self::start(
            $db,
            $ticketId,
            'Successful generation',
            'Finalizing the process and confirming successful output generation.'
        );
    }
}