<?php

namespace AIO\Data;

class ContainerEventsLog {
    readonly public string $filename;

    public function __construct()
    {
        $this->filename = DataConst::GetDataDirectory() . "/container_events.log";
        if (file_exists($this->filename)) {
            $this->pruneFileIfTooLarge();
        } else {
            touch($this->filename);
        }
    }

    public function lastModified() : int|false {
        return filemtime($this->filename);
    }

    public function add(string $id, string $message) : void
    {
        $json = json_encode(['time' => time(), 'id' => $id, 'message' => $message]);

        // Append new event (atomic via LOCK_EX)
        file_put_contents($this->filename, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    // Truncate the file to keep only the last bytes, aligned to a newline boundary.
    protected function pruneFileIfTooLarge() : void {
        $maxBytes = 512 * 1024; // 512 KB
        $maxLines = 1000; // keep last 1000 events

        if (filesize($this->filename) <= $maxBytes) {
            return;
        }

        $lines = file($this->filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $total = count($lines);
            $start = max(0, $total - $maxLines);
            $keep = array_slice($lines, $start);
            // rewrite file with kept lines
            file_put_contents($this->filename, implode(PHP_EOL, $keep) . PHP_EOL, LOCK_EX);
        }
    }
}
