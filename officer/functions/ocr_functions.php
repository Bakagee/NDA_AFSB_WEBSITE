<?php
/**
 * OCR Utility Functions
 * 
 * This file contains shared OCR processing functions used across the application.
 */

if (!function_exists('processOCR')) {
    /**
     * Process OCR on uploaded image
     * 
     * @param string $image_path Path to the image to be processed
     * @return string The extracted text from the image
     */
    function processOCR($image_path) {
        // Validate that Tesseract is available
        if (!class_exists('TesseractOCR')) {
            return "OCR processing unavailable: TesseractOCR class not found. Please install the Tesseract OCR library.";
        }
        
        try {
            // Process image with Tesseract OCR
            $ocr = new TesseractOCR($image_path);
            $ocr->lang('eng'); // Set language to English
            $text = $ocr->run();
            
            return $text ?: "No text detected in the document.";
        } catch (Exception $e) {
            return "OCR processing error: " . $e->getMessage();
        }
    }
}

// Add any other OCR-related functions here 