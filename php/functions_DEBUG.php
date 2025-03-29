<?php
class ReceiptDecoder {
    // Constants
    const LINE_WIDTH = 64; // Total characters per line

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

        // Function to format a section with dynamic alignment
        $formatSection = function($section) {
            $lines = explode("\x0a", $section); // Split into lines
            $formattedLines = [];

            foreach ($lines as $line) {
                $line = self::applyAlignment($line); // Apply alignment logic
                $formattedLines[] = $line;
            }

            return implode('<br>', $formattedLines); // Join lines with <br>
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
     * Apply alignment logic to a line
     * 
     * @param string $line The line to format
     * @return string Formatted line with alignment
     */
    private static function applyAlignment($line) {
        $formattedLine = '';
        $currentPosition = 0; // Track the current position in the line

        // Split the line into segments based on alignment commands
        $segments = preg_split('/(\x1b[@AB])/', $line, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($segments as $segment) {
            if ($segment === "\x1b@") {
                // Left align
                $formattedLine .= '<span style="text-align:left;">';
            } elseif ($segment === "\x1bA") {
                // Center align
                $formattedLine .= '<span style="text-align:center;">';
            } elseif ($segment === "\x1bB") {
                // Right align
                $formattedLine .= '<span style="text-align:right;">';
            } else {
                // Calculate remaining space and apply alignment
                $segmentLength = strlen($segment);
                $remainingSpace = self::LINE_WIDTH - $currentPosition;

                if ($remainingSpace > 0) {
                    $formattedLine .= str_repeat('&nbsp;', $remainingSpace) . $segment;
                    $currentPosition += $remainingSpace + $segmentLength;
                } else {
                    $formattedLine .= $segment;
                    $currentPosition += $segmentLength;
                }
            }
        }

        return $formattedLine;
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