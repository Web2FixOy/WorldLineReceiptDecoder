<?php
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(!empty($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(!empty($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(!empty($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(!empty($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}
// if(get_client_ip()!="192.168.50.1"){
//     exit("Nothing here to see! ".get_client_ip());
//     header("HTTP/1.1 403 Forbidden");
//     header("Location: /");
// }

$_debug = false;
if(isset($_GET['debug'])){
    $_debug = false;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terminal API</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<style>
    #receiptText, #headerText, #footerText  {
        font-family: 'Courier New', Courier, monospace;
        white-space: pre-wrap;
    }
    #responseText {
        word-break: break-all;
    }
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {

    let globalHeaders = {
        'Content-Type': 'application/json',
        'Authorization': '',
        'Custom-Header': '',
        'Integration-Key': '',
        'Secret-Key': ''
        // 'User-Agent': ''
    };

    // original
    // function decodeAndDisplayReceipt(escposEncoded) {
    //     let escposDecoded = atob(escposEncoded);

    //     // Log the original decoded content
    //     console.log("Original Decoded Content:", escposDecoded);
    //     $("#responseText").html(escposDecoded);

    //     // Find the start of header, content, and footer
    //     let headerIndex = escposDecoded.indexOf('\x1b!');
    //     let contentIndex = escposDecoded.indexOf('\x1b&');
    //     let footerIndex = escposDecoded.indexOf('\x1b$'); // First occurrence of footer marker

    //     console.log("Header Index:", headerIndex);
    //     console.log("Content Index:", contentIndex);
    //     console.log("Footer Index:", footerIndex);

    //     // If contentIndex and footerIndex are found, extract only the content section
    //     if (contentIndex !== -1 && footerIndex !== -1 && contentIndex < footerIndex) {
    //         escposDecoded = escposDecoded.substring(contentIndex, footerIndex);
    //     } else if (contentIndex !== -1) {
    //         // If footer not found but content marker exists, use content section until end
    //         escposDecoded = escposDecoded.substring(contentIndex);
    //     } else {
    //         console.warn("Content marker '\\x1b&' not found in receipt.");
    //         return;
    //     }

    //     // Function to create a line with the left component and right component aligned
    //     function alignText(leftText, rightText) {
    //         return `<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; width: 100%; margin-bottom: 5px;">
    //                     <span style="text-align: left;">${leftText}</span>
    //                     <span style="text-align: right;">${rightText}</span>
    //                 </div>`;
    //     }

    //     // Initialize a variable to track the current alignment
    //     let currentAlignment = 'left';

    //     // Function to start a new alignment div based on alignment type
    //     function startAlignmentDiv(alignment) {
    //         if (alignment === 'center') return `<div style="text-align:center; margin-bottom:10px;">`;
    //         return `<div style="position:relative; text-align:left; margin-bottom:10px;">`; // default to left
    //     }

    //     // Function to close the current alignment div
    //     function closeAlignmentDiv() {
    //         return '</div>';
    //     }

    //     // Step 1: Apply alignment divs and handle left-right alignment for specific lines
    //     let formattedContent = '';
    //     let lines = escposDecoded.split(/\x0a/);

    //     lines.forEach(line => {
    //         // Check for alignment commands
    //         if (line.includes('\x1b@')) {
    //             currentAlignment = 'left';
    //             line = line.replace(/\x1b@/g, ''); // Remove the command
    //             formattedContent += closeAlignmentDiv() + startAlignmentDiv(currentAlignment);
    //         }
    //         if (line.includes('\x1bA')) {
    //             currentAlignment = 'center';
    //             line = line.replace(/\x1bA/g, ''); // Remove the command
    //             formattedContent += closeAlignmentDiv() + startAlignmentDiv(currentAlignment);
    //         }

    //         // Handle left-right alignment in lines with specific markers (e.g., \x1bB)
    //         if (line.match(/(.*?)\x1bB(.*)/)) {
    //             const [_, left, right] = line.match(/(.*?)\x1bB(.*)/);
    //             formattedContent += alignText(left.trim(), right.trim());
    //         } else {
    //             // Otherwise, add the line content within the current alignment div
    //             formattedContent += line.trim() + '<br>';
    //         }

    //     });

    //     // Close any remaining open alignment div
    //     formattedContent += closeAlignmentDiv();

    //     // Step 2: Apply bold and other formatting on the aligned content
    //     formattedContent = formattedContent
    //         .replace(/\x1b\x52/g, '<b>')              // Bold on
    //         .replace(/\x1b\x50/g, '</b>')             // Bold off
    //         .replace(/[\x1b\x24\x21\x26]/g, '');      // Remove any stray section markers

    //     // Specific formatting for card number and PSN alignment
    //     formattedContent = formattedContent.replace(/(\*{12}\d{4})\s*(PSN:\d{2})/g, (match, cardNumber, psn) => {
    //         return alignText(cardNumber, psn.trim());
    //     });

    //     // Display formatted content in HTML and console
    //     $('#receiptText').html(formattedContent);
    //     console.log("Final formatted content:", formattedContent);
    // }

    // new 


    // Function to update #escposEncodedInput based on selected receipt type
    function receipt_type() {
        $('#receipt_type').on('change', function() {
            const receiptType = $(this).val();
            getEscposContent(receiptType)
                .then(response => {
                    $('#escposEncodedInput').val(response.escposContent);
                    // Call another function after the AJAX request is fully executed
                    // anotherFunction();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        });

        function getEscposContent(receiptType) {
            let debug = '';
            if (window.location.href.indexOf('?debug=1') !== -1 || window.location.href.indexOf('&debug=1') !== -1) {
                debug = '&debug=1';
            }
            console.log("debug: "+debug);
            
            return $.ajax({
                url: 'php/action.php',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                data: `action=receiptType${debug}&receiptType=${encodeURIComponent(receiptType)}`,
                dataType: 'json'
            })
            .done(function(response) {
                // Handle the successful response
                console.log('Success Response:', response);
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                // Handle errors
                console.error('AJAX error:', textStatus, errorThrown);

                if (jqXHR.responseText) {
                    try {
                        const errorResponse = JSON.parse(jqXHR.responseText);
                        console.error('Server Error Response:', errorResponse);
                    } catch (parseError) {
                        console.error('Failed to parse server response:', parseError);
                        console.error('Raw Server Response:', jqXHR.responseText);
                    }
                } else {
                    console.error('No server response received.');
                }
            });
        }

    }

    function decodeReceipt(escposEncoded) {
    return $.ajax({
        url: 'php/action.php',
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        data: `action=decodeReceipt&encodedReceipt=${encodeURIComponent(escposEncoded)}`,
        dataType: 'json' // Expect JSON response
    })
    .done(function(response) {
        // Handle the successful response
        console.log('Success Response:', response);

        // Update HTML elements based on the response
        $('#receiptText').html(response.receiptText || '');
        $('#responseText').html(response.rawText || ''); // Assuming you might want to render HTML content
        $('#headerText').html(response.headerText || '');
        $('#footerText').html(response.footerText || '');
    })
    .fail(function(jqXHR, textStatus, errorThrown) {
        // Handle errors
        console.error('AJAX error:', textStatus, errorThrown);
        
        if (jqXHR.responseText) {
            try {
                const errorResponse = JSON.parse(jqXHR.responseText);
                console.error('Server Error Response:', errorResponse);
            } catch (parseError) {
                console.error('Failed to parse server response:', parseError);
                console.error('Raw Server Response:', jqXHR.responseText);
            }
        } else {
            console.error(jqXHR);
            console.error('No server response received.');
        }

        // Optionally, you can update the HTML elements with an error message
        $('#receiptText').text('');
        $('#responseText').text('');
        $('#headerText').text('');
        $('#footerText').text('');
    });
}

// Example usage:
// Call decodeReceipt with your base64-encoded ESC/POS data
// decodeReceipt("BASE64_ENCODED_ESC_POS_DATA_HERE");

    function displayDecodedString(escposEncoded, outputElementId) {
        // Decode the base64-encoded ESC/POS data
        let escposDecoded = atob(escposEncoded);

        // Create a <pre> element to display the raw decoded string
        let outputElement = document.getElementById(outputElementId);
        if (!outputElement) {
            console.error("Output element not found!");
            return;
        }

        // Replace special characters with their code representation
        let rawText = escposDecoded.replace(/[\x00-\x1F\x7F-\xFF]/g, (char) => {
            // Convert the character to its hexadecimal code representation
            return `\\x${char.charCodeAt(0).toString(16).padStart(2, '0')}`;
        });

        // Display the raw text in the output element
        outputElement.textContent = rawText;
    }
    // Handle form submission
    $('form').on('submit', function(event) {
        event.preventDefault();
        let escposEncoded = $('#escposEncodedInput').val();
        decodeReceipt(escposEncoded);

        // displayDecodedString(escposEncoded, "responseText");
    });

    receipt_type();
});

</script>
</head>
<body class="container mt-5">
    <h1 class="mb-4">Payment Terminal Interface</h1>
    <span class="text-muted">Fill in the form below to interact with the payment terminal.</span>
    <div class="text-muted">Your IP address is recorded for legal purposes: <?=get_client_ip();?></div>   
    <hr>
    
    <!-- Form for inputting escposEncoded string -->
    <form class="mt-4">
        <div class="mb-3 bt">
            <label for="receipt_type" class="form-label">Receipt Type</label>
            <select id="receipt_type" name="receipt_type" class="form-select">
                <option value="">Select a receipt type</option>
                <option value="sales_c">Sales (Customer copy in Finnish)</option>
                <option value="sales_m">Sales (Merchant copy in English)</option>
                <option value="refund_c">Refund (Customer copy in Finnish)</option>
                <option value="refund_m">Refund (Merchant copy in English)</option>
                <option value="reversal_c">Reversal (Customer copy in Finnish)</option>
                <option value="reversal_m">Reversal (Merchant copy in English)</option>
                <option value="capture">Capture (Merchant copy in English)</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="escposEncodedInput" class="form-label">ESC/POS Encoded String</label>
            <textarea class="form-control" id="escposEncodedInput" rows="10"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Decode and Display Receipt</button>
    </form>

    <div id="receipt" class="mt-4 well">
        <h2>Decoded Receipt:</h2>
        <pre id="receiptText" class="p-3 bg-light border"></pre>
    </div>
    <div id="response" class="mt-4">
        <h2>Decoded string:</h2>
        <div id="responseText" class="p-3 bg-light border well"></div>
    </div>
    <div id="header" class="mt-4 well">
        <h2>Decoded header:</h2>
        <pre id="headerText" class="p-3 bg-light border"></pre>
    </div>
    <div id="footer" class="mt-4 well">
        <h2>Decoded footer:</h2>
        <pre id="footerText" class="p-3 bg-light border"></pre>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>