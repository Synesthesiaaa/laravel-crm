<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class EmailCampaignDocumentService
{
    public function renderPdf(EmailTemplate $template, string $recipientName, string $recipientEmail): string
    {
        if (! class_exists(\TCPDF::class)) {
            throw new \RuntimeException('PDF generation requires the tecnickcom/tcpdf package.');
        }

        $body = $this->personalize((string) $template->pdf_body, $recipientName, $recipientEmail);
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name'));
        $pdf->SetTitle($template->name);
        $pdf->SetMargins(18, 20, 18);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        // AES-256 PDF encryption; recipients must receive passwords separately.
        $pdf->SetProtection([], (string) $template->pdf_password, null, 3);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->writeHTML('<div style="font-size:11pt;line-height:1.6">'.nl2br(e($body)).'</div>', true, false, true, false, '');

        return $pdf->Output('attachment.pdf', 'S');
    }

    public function personalize(string $text, string $name, string $email): string
    {
        return str_replace(['{{name}}', '{{email}}'], [$name, $email], $text);
    }

    public function extractText(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());
        $text = '';

        if ($extension === 'pdf') {
            $text = $this->run([config('email_campaigns.pdftotext'), '-layout', '-f', '1', '-l', '5', $path, '-']) ?? '';
        }

        if (trim($text) === '') {
            if ($extension !== 'pdf') {
                $text = $this->run([config('email_campaigns.tesseract'), $path, 'stdout', '-l', 'eng']) ?? '';
            } else {
                $folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm-ocr-'.Str::uuid();
                if (! mkdir($folder, 0700) && ! is_dir($folder)) {
                    throw new \RuntimeException('Unable to prepare OCR workspace.');
                }

                try {
                    $outputPrefix = $folder.DIRECTORY_SEPARATOR.'page';
                    $rasterized = $this->run([config('email_campaigns.pdftoppm'), '-f', '1', '-l', '5', '-r', '150', '-png', $path, $outputPrefix]);
                    if ($rasterized === null) {
                        throw ValidationException::withMessages(['document' => 'Scanning PDFs requires pdftoppm (Poppler) and Tesseract OCR on the server.']);
                    }

                    foreach (glob($outputPrefix.'-*.png') ?: [] as $image) {
                        $page = $this->run([config('email_campaigns.tesseract'), $image, 'stdout', '-l', 'eng']);
                        if ($page === null) {
                            throw ValidationException::withMessages(['document' => 'OCR requires Tesseract with English language data on the server.']);
                        }
                        $text .= "\n".$page;
                    }
                } finally {
                    foreach (glob($folder.DIRECTORY_SEPARATOR.'*') ?: [] as $image) {
                        @unlink($image);
                    }
                    @rmdir($folder);
                }
            }
        }

        $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '');
        if ($text === '') {
            throw ValidationException::withMessages(['document' => 'No readable text was found. Install OCR tools or provide a clearer image/PDF.']);
        }

        return Str::limit($text, 30000, '');
    }

    private function run(array $command): ?string
    {
        try {
            $process = new Process($command);
            $process->setTimeout(75);
            $process->run();

            return $process->isSuccessful() ? $process->getOutput() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
