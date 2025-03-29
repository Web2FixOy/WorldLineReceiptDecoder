<?php
$_debug = false; ### set this to true from here or add &debug=true into your URL
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