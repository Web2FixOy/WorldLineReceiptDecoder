<?php
class ReceiptDecoder {
    public static function decodeAndDisplayReceipt($escposEncoded) {
        // Decode the base64-encoded ESC/POS data
        $escposDecoded = base64_decode($escposEncoded);

        // Convert non-UTF-8 characters to a readable format for debugging
        $rawText = preg_replace_callback('/[\x00-\x1F\x7F-\xFF]/', function($matches) {
            return '\\x' . str_pad(dechex(ord($matches[0])), 2, '0', STR_PAD_LEFT);
        }, $escposDecoded);

        // Split the decoded string into sections based on markers
        $parts = [];
        $currentIndex = 0;
        
        // Find all section markers
        while ($currentIndex < strlen($escposDecoded)) {
            // Check for section markers
            if (substr($escposDecoded, $currentIndex, 2) === "\x1b!") {
                $parts[] = ["type" => "header", "start" => $currentIndex];
                $currentIndex += 2; // Skip the marker
            } elseif (substr($escposDecoded, $currentIndex, 2) === "\x1b&") {
                $parts[] = ["type" => "content", "start" => $currentIndex];
                $currentIndex += 2; // Skip the marker
            } elseif (substr($escposDecoded, $currentIndex, 2) === "\x1b$") {
                $parts[] = ["type" => "footer", "start" => $currentIndex];
                $currentIndex += 2; // Skip the marker
            } else {
                $currentIndex++;
            }
        }
        
        // Calculate end positions for each section
        for ($i = 0; $i < count($parts); $i++) {
            if ($i < count($parts) - 1) {
                $parts[$i]["end"] = $parts[$i + 1]["start"];
            } else {
                $parts[$i]["end"] = strlen($escposDecoded);
            }
            
            // Extract the actual content (without the marker)
            $start = $parts[$i]["start"] + 2; // Skip the 2-byte marker
            $length = $parts[$i]["end"] - $start;
            $parts[$i]["content"] = substr($escposDecoded, $start, $length);
        }
        
        // Group by section type
        $headers = [];
        $contents = [];
        $footers = [];
        
        foreach ($parts as $part) {
            if ($part["type"] === "header") {
                $headers[] = self::cleanSection($part["content"]);
            } elseif ($part["type"] === "content") {
                $contents[] = self::cleanSection($part["content"]);
            } elseif ($part["type"] === "footer") {
                $footers[] = self::cleanSection($part["content"]);
            }
        }

        // Join multiple headers, contents, and footers into single strings
        $header = implode('', $headers);
        $content = implode('', $contents);
        $footer = implode('', $footers);

        // Function to format a section
        $formatSection = function($section) {
            // Replace alignment commands
            $section = str_replace("\x1b@", '<div style="text-align:left;">', $section); // Left align
            $section = str_replace("\x1bA", '<div style="text-align:center;">', $section); // Center align
            $section = str_replace("\x1bB", '<div style="text-align:right;">', $section); // Right align

            // Replace styling commands
            $section = str_replace("\x1bP", '<span style="font-weight:normal;">', $section); // Normal text
            $section = str_replace("\x1bQ", '<span style="font-style:italic;">', $section); // Italic text
            $section = str_replace("\x1bR", '<span style="font-weight:bold;">', $section); // Bold text
            $section = str_replace("\x1bS", '<span style="font-size:2em;">', $section); // Double height
            $section = str_replace("\x1bT", '<span style="font-size:1.5em;">', $section); // 1.5x height

            // Replace decorations
            $section = str_replace("\x1bp", '<hr style="border-top:1px solid black;">', $section); // Horizontal separator
            $section = preg_replace_callback('/\x1bq(.)/', function($matches) {
                return '<div style="width:100%; text-align:center;">' . str_repeat($matches[1], 48) . '</div>';
            }, $section);

            // Replace barcode commands (placeholder for barcode rendering)
            $section = str_replace("\x1b`", '<div class="barcode-ean8-start">', $section);
            $section = str_replace("\x1ba", '</div>', $section);
            $section = str_replace("\x1bb", '<div class="barcode-ean13-start">', $section);
            $section = str_replace("\x1bc", '</div>', $section);

            // Replace newlines with <br>
            $section = str_replace("\x0a", '<br>', $section);

            // Close any open divs and spans
            $openDivs = substr_count($section, '<div');
            $closedDivs = substr_count($section, '</div>');
            $openSpans = substr_count($section, '<span');
            $closedSpans = substr_count($section, '</span>');
            
            // Add missing closing tags
            for ($i = 0; $i < ($openDivs - $closedDivs); $i++) {
                $section .= '</div>';
            }
            
            for ($i = 0; $i < ($openSpans - $closedSpans); $i++) {
                $section .= '</span>';
            }

            return $section;
        };

        // Format each section
        $formattedHeader = $formatSection($header);
        $formattedContent = $formatSection($content);
        $formattedFooter = $formatSection($footer);

        // Create a plain text version for each section (for debugging)
        $plainHeader = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $header);
        $plainContent = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $content);
        $plainFooter = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $footer);

        // Create an associative array for JSON encoding
        $receiptArray = [
            'rawText' => $rawText,
            'headerText' => $formattedHeader,
            'receiptText' => $formattedContent,
            'footerText' => $formattedFooter,
            'plainHeader' => $plainHeader,
            'plainContent' => $plainContent,
            'plainFooter' => $plainFooter
        ];

        // Encode the array as a JSON string and return it
        $jsonEncoded = json_encode($receiptArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Check for errors in json_encode
        if ($jsonEncoded === false) {
            // If json_encode fails, sanitize the data and try again
            array_walk_recursive($receiptArray, function(&$value) {
                if (is_string($value)) {
                    // Remove non-UTF-8 characters
                    $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                }
            });
            $jsonEncoded = json_encode($receiptArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return $jsonEncoded;
    }

    /**
     * Clean a section by removing noise and garbage data
     * 
     * @param string $section The raw section data
     * @return string Cleaned section
     */
    private static function cleanSection($section) {
        $length = strlen($section);
        $cleanedSection = '';
        $validCommandStart = false;
        $inCommand = false;
        $currentCommand = '';
        $escSequences = [
            '@', 'A', 'B', 'P', 'Q', 'R', 'S', 'T', 'p', 'q', 
            '`', 'a', 'b', 'c', 'd', 'e'
        ];
        
        // Scanner to process the section character by character
        for ($i = 0; $i < $length; $i++) {
            $char = $section[$i];
            $ord = ord($char);
            
            // Handling ESC sequences
            if ($ord === 0x1B) {
                $validCommandStart = true;
                $inCommand = true;
                $currentCommand = $char;
                continue;
            }
            
            if ($inCommand) {
                $currentCommand .= $char;
                $inCommand = false; // ESC/POS commands are typically 2 bytes
                
                // Check if this is a valid command
                if ($validCommandStart && in_array($char, $escSequences)) {
                    $cleanedSection .= $currentCommand;
                }
                
                $validCommandStart = false;
                continue;
            }
            
            // Handle printable characters and newlines
            if (($ord >= 32 && $ord <= 126) || $ord === 0x0A) {
                $cleanedSection .= $char;
                continue;
            }
            
            // Special case handling for known special characters in your specific format
            // Add other special characters if needed
            if ($ord === 0x09) { // Tab
                $cleanedSection .= $char;
                continue;
            }
            
            // Skip garbage characters
            // This skips non-printable, non-command characters
            // which are likely garbage or binary data
        }
        
        return $cleanedSection;
    }
}
// Example usage
// $encodedReceipt = "GyEbQVdlYjJGaXggLSBERU1PClN0dXJlbmthdHUgMjYKMDA1MTAgSGVsc2lua2kKVGVsLjogMDEyMzQ1NgpPUkcuTlI6IDIzODUzNjEyChtAChskG0FTQVZFIFJFQ0VJUFQsIENVU1RPTUVSJ1MgQ09QWQobQBsmG0AbQBtCG0AbQhtAG0IbQFRFUk1JTkFMOhtCMjNDTktSNTA4NjgwChtATUVSQ0hBTlQ6G0I2NTg1NzE1MyA0MzY0NTI5NAobQENBU0hJRVI6G0IxMjM0NTYKG0BEQVRFOjIwMjQtMTEtMTgbQlRJTUU6MTM6MzUKG0AKGyYbQRtSU0FMRQpBUFBST1ZFRAobUBtAChtSG0EbQRtQGyYbQEFNT1VOVBtCRVVSIDMuMzYKG0AbQhtAG1JUT1RBTFtCRVVSIDMuMzYKG1AbQAobQBtCG0AbQhtSG0AbQhtAG0IbUBtBG0AbQBsmVklTQSBDT05UQUNUTEVTUwpWSVNBIERlQkJpdAobAKioqKioqKioqNjEwNxtCUFNOOiAwMgobQAobQRtAG0EbQBsmG0FXTE4gSy8xIDMgMDAwIFdMTiAwMDggMzcyMDE3ChtAUkVDRUlQVDowMDAwMzcbQlJFRjozMDgwMjQyNTQwMTUKG0AKGyZBVEM6MDQxQyAKQUlEOkEwMDAwMDAwMDMxMDEwClRWUjowMDAwMDAwMDAwClRTSTowMDAwCkFSUUM6QzVBMjJCMjBBREY2MjUwNwoKGyc=";

// Example usage
// // $encodedReceipt = "GyEbQVdlYjJGaXggLSBERU1PClN0dXJlbmthdHUgMjYKMDA1MTAgSGVsc2lua2kKUHVoOiAwMTIzNDU2ClktdHVubnVzOiAyMzg1MzYxMgobQAobJBtBU8RJTFlUxCBLVUlUTVEsIEFTSUFLS0FBTiBLQVBQQUxFChtAGyYbQBtAG0IbQBtCG0AbQhtAUMTEVEU6G0IyM0NOS1I1MDg2ODAKG0BMSVVJRTobQjY1ODU3MTUzIDQzNjQ1Mjk0ChtATVlZSsQ6G0IwChtAUFZNOjIwMjUtMDMtMDEbQkFJS0E6MDc6NTYKG0AKGyYbQRtSSFlWSVRZUwpIWVbES1NZVFRZChtQG0AKG1IbQRtBG1AbJhtAU1VNTUEbQkVVUiAwLDMwChtAG0IbQBtSWUhURUVOU8QbQkVVUiAwLDMwChtQG0AKG0AbQhtAG0IbUhtAG0IbQBtCG1AbQRtAG0AbJlZJU0EgQ09OVEFDVExFU1MKVmlzYSBEZWJpdAobQCoqKioqKioqKioqKjYxMDcbQlBTTjogMDMKG0AKG0EbQBsmG0FIWVbES1NZTiBUxFTEIEtPUlRUSUEgSFlWSVRFVFTEVsROIFlMTMQgRVJJVEVMTFlOIFNVTU1BTgoKG0BNeXlq5G4gbmltaToKChtwTXl5auRuIGFsbGVraXJqb2l0dXM6CgobcAobJhtBV0xOIEstMSAzIDAwMCBXTE4gMDQ2IDczODQ0NAobQEtVSVRUSTowMDA1NzcbQlJFRjozMDgxNTMzNzA1OTcKG0AKGyZBVEM6MDNFMSAKQUlEOkEwMDAwMDAwMDMxMDEwClRWUjowMDAwMDAwMDAwCkFBQzo5RDkwMDI1NkI4RkMzMzNECgobJw==";
// $decoded = ReceiptDecoder::decodeAndDisplayReceipt($encodedReceipt);
// echo $decoded;
?>