<?php
/**
 * Idempotent patch for eurooffice EditorApiController::getFile().
 *
 * Bug: empty($fileId) treats the literal fileId=0 (used by the Files-app
 * inline Viewer preview, which relies on $filePath instead) as missing,
 * so it never reaches the filePath lookup and preview fails with
 * "FileId is empty". AUTOMATIC_UPDATES=1 replaces this file on every
 * eurooffice app update, so this script re-applies the fix and is safe
 * to run repeatedly.
 */

$file = '/var/www/html/custom_apps/eurooffice/lib/Controller/EditorApiController.php';

if (!is_file($file)) {
    fwrite(STDERR, "SKIP: $file not found (eurooffice not installed?)\n");
    exit(0);
}

$src = file_get_contents($file);

$old = <<<'OLD'
        if (empty($fileId)) {
            return [null, $this->trans->t("FileId is empty"), null];
        }

        try {
            $folder = $template ? TemplateManager::getGlobalTemplateDir() : $this->root->getUserFolder($userId);
            $files = $folder->getById($fileId);
        } catch (\Exception $e) {
            $this->logger->error("getFile: $fileId", ["exception" => $e]);
            return [null, $this->trans->t("Invalid request"), null];
        }
OLD;

$new = <<<'NEW'
        if (empty($fileId)) {
            if (empty($filePath)) {
                return [null, $this->trans->t("FileId is empty"), null];
            }

            try {
                $folder = $template ? TemplateManager::getGlobalTemplateDir() : $this->root->getUserFolder($userId);
                $file = $folder->get($filePath);
            } catch (\Exception $e) {
                $this->logger->error("getFile by path: $filePath", ["exception" => $e]);
                return [null, $this->trans->t("File not found"), null];
            }

            if (!$file->isReadable()) {
                return [null, $this->trans->t("You do not have enough permissions to view the file"), null];
            }

            return [$file, null, null];
        }

        try {
            $folder = $template ? TemplateManager::getGlobalTemplateDir() : $this->root->getUserFolder($userId);
            $files = $folder->getById($fileId);
        } catch (\Exception $e) {
            $this->logger->error("getFile: $fileId", ["exception" => $e]);
            return [null, $this->trans->t("Invalid request"), null];
        }
NEW;

if (str_contains($src, $new)) {
    echo "ALREADY PATCHED: skipping\n";
    exit(0);
}

$occurrences = substr_count($src, $old);
if ($occurrences !== 1) {
    fwrite(STDERR, "ABORT: expected exactly 1 occurrence of the unpatched block, found $occurrences. "
        . "eurooffice likely changed this file's structure — needs manual review.\n");
    exit(1);
}

$patched = str_replace($old, $new, $src);

$tmp = tempnam(sys_get_temp_dir(), 'lint');
file_put_contents($tmp, $patched);
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lintOut, $lintCode);
unlink($tmp);

if ($lintCode !== 0) {
    fwrite(STDERR, "ABORT: patched content failed lint check:\n" . implode("\n", $lintOut) . "\n");
    exit(1);
}

copy($file, $file . '.bak-' . date('Ymd-His'));
file_put_contents($file, $patched);
echo "PATCHED OK\n";
