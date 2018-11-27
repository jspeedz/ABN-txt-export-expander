<?php
/**
 * Quick and dirty script to explode the ABN exported TXT file into more usable columns when importing into other software
 */
ini_set('upload_tmp_dir', '/tmp');
ini_set('post_max_size', '5M');

if(!is_dir(__DIR__ . '/tmp')) {
    mkdir(__DIR__ . '/tmp');
}

if($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    trigger_error('Upload fail, code: ' . $_FILES['file']['error'], E_USER_WARNING);
    exit;
}

if(is_uploaded_file($_FILES['file']['tmp_name'])) {
    $filePath = __DIR__ . '/tmp/file.csv';
    if(!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
        die('File unmovable');
    }
    if(file_exists($filePath)) {
        $file = array_map(function($line) {
            $line = str_getcsv($line, '	', '', '\\');
            $payeeAccountHolder = $payeeAccount = $remark = $description = $transactionType = $reference = '';
            switch($line[7]) {
                case preg_match('/\/(TRTP|RTYP)\//', $line[7]) === 1:
                    if(preg_match('/\/TRTP\/([^\/]+)/', $line[7], $matches) === 1) {
                        $transactionType = trim($matches[1]);
                    }
                    elseif(preg_match('/\/RTYP\/([^\/]+)/', $line[7], $matches) === 1) {
                        $transactionType = trim($matches[1]);
                    }
                    if(preg_match('/\/IBAN\/([^\/]+)/', $line[7], $matches) === 1) {
                        $payeeAccount = trim($matches[1]);
                    }
                    if(preg_match('/\/BIC\/([^\/]+)/', $line[7], $matches) === 1) {
                        $payeeBankBIC = trim($matches[1]);
                    }
                    if(preg_match('/\/NAME\/([^\/]+)/', $line[7], $matches) === 1) {
                        $payeeAccountHolder = trim($matches[1]);
                    }
                    if(preg_match('/\/REMI\/([^\/]+)/', $line[7], $matches) === 1) {
                        $remark = trim($matches[1]);
                    }
                    if(preg_match('/\/EREF\/([^\/]+)/', $line[7], $matches) === 1) {
                        if(trim($matches[1]) !== 'NOTPROVIDED') {
                            $reference = trim($matches[1]);
                        }
                    }
                    $transactionType = transactionTypeToReadable(trim($transactionType));
                    $description = $transactionType . ': ' . $payeeAccountHolder;
                    if(!empty($reference)) {
                        $description .= ' (referentie: ' . trim($reference) . ')';
                    }
                    break;
                case preg_match('/([a-zA-Z]{3})\s+(NR:[A-Z0-9]+)\s+([0-9\.\/]{14})\s+((.+)),PAS[0-9]+/i', $line[7], $matches) === 1:
                    $transactionType = transactionTypeToReadable(trim($matches[1]));
                    $description = trim($matches[5]);
                    $remark = trim($matches[3] . ' - ' . $matches[2]);
                    break;
                case stripos($line[7], 'ABN AMRO Bank N.V.               Rekening') !== false:
                    $description = 'ABN AMRO Bank N.V. kosten betaalrekening';
                    break;
                default:
                    $description = trim($line[7]);
                    break;
            }

            $outputLine = [
                'transactietype' => $transactionType,
                'rekening' => trim($line[0]),
                'payee' => $payeeAccount,
                'payeeName' => $payeeAccountHolder,
                'valuta' => trim($line[1]),
                'datum' => trim($line[2]),
                'beginsaldo' => trim($line[3]),
                'eindsaldo' => trim($line[4]),
                'uitvoerdatum' => trim($line[5]),
                'bedrag' => trim($line[6]),
                'beschrijving' => $description,
                'memo' => $remark,
            ];

            return $outputLine;
        }, file($filePath));

        array_unshift($file, array_keys($file[0]));

        $newFileName = $_FILES['file']['name'];
        $newFileName = str_replace('.txt', '', $newFileName);
        $newFileName = str_replace('.csv', '', $newFileName);
        $newFileName .= '_converted.csv';

        unlink($filePath);

        ob_clean();
        $fp = fopen('php://output', 'w');
        array_map(function($line) use ($fp) {
            fputcsv($fp, $line);
        }, $file);
        fclose($fp);
        $file = ob_get_contents();
        ob_end_clean();

        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Transfer-Encoding: binary');
        header('Content-Type: text/csv');
        header('content-type: text/csv');
        header('X-test: text/csv');
        header('Content-Length: ' . strlen($file));
        header('Content-Disposition: attachment; filename=' . $newFileName);
        echo $file;

    }
    else {
        die('File not there');
    }
}

function transactionTypeToReadable(string $transactionType): string {
    switch($transactionType) {
        case 'BEA':
            $transactionType = 'BETAALAUTOMAAT';
            break;
        case 'GEA':
            $transactionType = 'GELDAUTOMAAT';
            break;
    }
    return $transactionType;
}