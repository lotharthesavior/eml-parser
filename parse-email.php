<?php

require_once __DIR__.'/vendor/autoload.php';

use PhpMimeMailParser\Parser;
use Dompdf\Dompdf;
use Dompdf\Options;
use Spatie\Browsershot\Browsershot;
// use Exception;

/** @var string */
const OPT_HTML = 'html';

/** @var string */
const OPT_PDF = 'pdf';

/** @var string */
const FILE_NAME = 'parse-email.php';

// run to generate pdf: php parse-email.php {pdf|html}
$option = array_filter($argv, function($item){
    return $item !== FILE_NAME;
});
if (count($option) < 1 || !in_array(current($option), [OPT_HTML, OPT_PDF])) {
    echo "Not accepted option passed!" . PHP_EOL;
    echo "Available options: html, pdf." . PHP_EOL;
    exit;
}
$option = current($option);

/**
 * Helper for slug from string.
 *
 * @param string $text
 * @return string
 */
function slugify(string $text): string
{
    // replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // trim
    $text = trim($text, '-');

    // remove duplicate -
    $text = preg_replace('~-+~', '-', $text);

    // lowercase
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}

/**
 * Helper to list files from directory.
 *
 * @param string $directory
 * @return $directory
 */
function list_files(string $directory) {
    $fileList = glob($directory . '/*');
    return array_map(function($filename){
        return $filename;
    }, $fileList);
}

/**
 * Parse email to html.
 *
 * @see https://github.com/php-mime-mail-parser/php-mime-mail-parser
 *
 * @param Parser $parser Parser for email content.
 * @param string $path Path for email file.
 * @return array [ string $subject, string $html ]
 */
function parse_email_from_path(Parser $parser, string $path): array {
    // display email html
    // $path = './emails/email1.eml';
    // $parser = new Parser();
    $parser->setPath($path);
    $subject = $parser->getHeader('subject');
    $return_path = htmlentities($parser->getHeader('return-path'));
    $delivered_to = htmlentities($parser->getHeader('delivered-to'));
    $from = htmlentities($parser->getHeader('from'));
    $to = htmlentities($parser->getHeader('to'));
    $date = htmlentities($parser->getHeader('date'));
    $html = $parser->getMessageBody('html');
    $html = '<div>'
        . '<h3>Subject: ' . $subject . '</h3>'
        . '<p><strong>Return path</strong>: ' . $return_path . '</p>'
        . '<p><strong>Delivered To</strong>: ' . $delivered_to . '</p>'
        . '<p><strong>From</strong>: ' . $from . '</p>'
        . '<p><strong>To</strong>: ' . $to . '</p>'
        . '<p><strong>Date</strong>: ' . $date . '</p>'
        . '<h3>Body:</h3>'
        . '<div>'
        . $html
        . '</div>'
        . '</div>';

    return [$subject, $html];
}

/**
 * Generate PDF using chrome browser.
 * This function saves the content to a file.
 *
 * @see https://github.com/spatie/browsershot
 *
 * @param string $html HTML content.
 * @param string $path Path for pdf file.
 * @return void
 */
function pdf_via_browsershot(string $html, string $path): void {
    Browsershot::html($html)
        ->setNodeBinary('/home/savior/.nvm/versions/node/v12.18.4/bin/node')
        ->setNpmBinary('/home/savior/.nvm/versions/node/v12.18.4/bin/npm')
        ->save($path);
}

/**
 * Generate PDF using DOMPDF.
 * This function saves the content to a file.
 *
 * @see https://github.com/dompdf/dompdf
 *
 * @param string $html HTML content.
 * @param string $path Path for pdf file.
 * @return void
 */
function pdf_via_dompdf(string $html, string $path): void {
    $options = new Options();
    $options->set( 'isRemoteEnabled', true );
    $options->set( 'isHtml5ParserEnabled', true );
    $dompdf = new Dompdf( $options );
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    // $dompdf->stream();
    $output = $dompdf->output();
    file_put_contents($path, $output);
}

/**
 * Log text into log file.
 *
 * @param string $logfile Log file.
 * @param string $text Content.
 * @return void
 */
function log_action(string $logfile, string $text): void {
    $existent_log = '';
    if (file_exists($logfile)) {
        $existent_log = file_get_contents($logfile) . PHP_EOL;
    }
    file_put_contents($logfile, $existent_log . $text);
}

$parser = new Parser();
$output_directory = './output/';
$logfile = './result.log';
unlink($logfile);

log_action($logfile, 'Processing emails to ' . $option . '.');

echo "Executing..." . PHP_EOL;
foreach (list_files('./emails') as $key => $path) {

    try {
        [$subject, $html] = parse_email_from_path($parser, $path);
        echo "Processing '" . $subject . "' ...";

        log_action($logfile, 'Preparing email: ' . trim($subject));

        $result = '';
        // saving html to file
        if ($option === OPT_HTML) {
            $result = file_put_contents($output_directory . slugify(trim($subject)) . '.html', $html);
        } else if ($option === OPT_PDF) {
            $file_name = $output_directory . slugify(trim($subject)) . '.pdf';
            // saving html to pdf
            pdf_via_browsershot($html, $file_name);
            // pdf_via_dompdf($html, $file_name);
            $result = file_exists($file_name);
        }

        log_action($logfile, 'Result email: ' . $result);
        echo 'Result email: ' . $result . PHP_EOL;

    } catch (Exception $e) {
        echo 'failed: ' . $subject . PHP_EOL;
        // var_dump(
        //     $subject,
        //     $html,
        //     $e->getMessage()
        // );
        // exit;
    }

}