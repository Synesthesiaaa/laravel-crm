<?php

return [
    // These executables must be available on the queue/web server for OCR.
    'tesseract' => env('OCR_TESSERACT_BINARY', 'tesseract'),
    'pdftotext' => env('OCR_PDFTOTEXT_BINARY', 'pdftotext'),
    'pdftoppm' => env('OCR_PDFTOPPM_BINARY', 'pdftoppm'),
    'queue' => env('EMAIL_CAMPAIGN_QUEUE', 'email-campaigns'),
];
